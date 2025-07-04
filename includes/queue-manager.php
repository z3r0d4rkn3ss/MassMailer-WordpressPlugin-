<?php
/**
 * Mass Mailer Queue Manager
 *
 * Manages the email sending queue, adding emails, retrieving them for sending,
 * and updating their status. Also incorporates basic throttling.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class, Mailer, Subscriber, and Campaign Managers are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerMailer')) {
    require_once dirname(__FILE__) . '/mailer.php';
}
if (!class_exists('MassMailerSubscriberManager')) {
    require_once dirname(__FILE__) . '/subscriber-manager.php';
}
if (!class_exists('MassMailerCampaignManager')) {
    require_once dirname(__FILE__) . '/campaign-manager.php';
}

class MassMailerQueueManager {
    private $db;
    private $queue_table;
    private $mailer;
    private $subscriber_manager;
    private $campaign_manager;

    // Configuration for throttling
    const MAX_EMAILS_PER_BATCH = 50; // Number of emails to process in one cron run
    const BATCH_PROCESSING_INTERVAL_SECONDS = 60; // Minimum seconds between batch runs (conceptual for cron)

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->queue_table = MM_TABLE_PREFIX . 'queue'; // Assuming a new 'mm_queue' table
        $this->mailer = new MassMailerMailer();
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->campaign_manager = new MassMailerCampaignManager();
    }

    /**
     * Creates the mm_queue table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createQueueTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->queue_table}` (
            `queue_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `subscriber_id` INT NOT NULL,
            `status` ENUM('pending', 'processing', 'sent', 'failed', 'skipped') DEFAULT 'pending',
            `attempts` INT DEFAULT 0,
            `last_attempt_at` TIMESTAMP NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `sent_at` TIMESTAMP NULL,
            UNIQUE KEY `campaign_subscriber_idx` (`campaign_id`, `subscriber_id`), -- Ensure unique entry per campaign-subscriber
            FOREIGN KEY (`campaign_id`) REFERENCES " . MM_TABLE_PREFIX . "campaigns(`campaign_id`) ON DELETE CASCADE,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerQueueManager: mm_queue table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerQueueManager: Error creating mm_queue table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Adds subscribers from a campaign's target list to the email queue.
     *
     * @param int $campaign_id The ID of the campaign.
     * @return int The number of subscribers added to the queue, or false on failure.
     */
    public function populateQueueFromCampaign($campaign_id) {
        $campaign = $this->campaign_manager->getCampaign($campaign_id);
        if (!$campaign) {
            error_log('MassMailerQueueManager: Campaign ' . $campaign_id . ' not found for queue population.');
            return false;
        }

        // Get all active subscribers for the campaign's list
        // Assuming getAllSubscribers can filter by list_id and status 'active'
        $subscribers = $this->subscriber_manager->getAllSubscribers($campaign['list_id']);
        if (empty($subscribers)) {
            error_log('MassMailerQueueManager: No active subscribers found for list ' . $campaign['list_id'] . '.');
            return 0;
        }

        $added_count = 0;
        foreach ($subscribers as $subscriber) {
            // Only add if subscriber is 'subscribed' and their list_status is 'active'
            // The list_status check is important if getAllSubscribers joins mm_list_subscriber_rel
            if ($subscriber['status'] === 'subscribed' && (isset($subscriber['list_status']) && $subscriber['list_status'] === 'active')) {
                try {
                    // Use INSERT IGNORE to prevent duplicates if already in queue for this campaign
                    $sql = "INSERT IGNORE INTO {$this->queue_table} (campaign_id, subscriber_id, status) VALUES (:campaign_id, :subscriber_id, 'pending')";
                    $stmt = $this->db->query($sql, [
                        ':campaign_id' => $campaign_id,
                        ':subscriber_id' => $subscriber['subscriber_id']
                    ]);
                    if ($stmt->rowCount() > 0) {
                        $added_count++;
                    }
                } catch (PDOException $e) {
                    error_log('MassMailerQueueManager: Error adding subscriber ' . $subscriber['subscriber_id'] . ' to queue for campaign ' . $campaign_id . ': ' . $e->getMessage());
                }
            } else {
                error_log('MassMailerQueueManager: Skipping subscriber ' . $subscriber['email'] . ' (ID: ' . $subscriber['subscriber_id'] . ') due to status: ' . $subscriber['status'] . ' or list status: ' . ($subscriber['list_status'] ?? 'N/A'));
            }
        }

        // Update total recipients for the campaign
        $this->campaign_manager->setTotalRecipients($campaign_id, $added_count);
        error_log('MassMailerQueueManager: Added ' . $added_count . ' subscribers to queue for campaign ' . $campaign_id);
        return $added_count;
    }

    /**
     * Retrieves a batch of pending emails from the queue for processing.
     * Marks them as 'processing' to prevent other processes from picking them up.
     *
     * @return array An array of queue items ready for sending.
     */
    public function getEmailsForSending() {
        // Select emails that are 'pending' or 'failed' (and haven't been attempted recently)
        // Order by attempts (fewer attempts first) and then by created_at (oldest first)
        // Limit to MAX_EMAILS_PER_BATCH for throttling
        $sql = "SELECT * FROM {$this->queue_table}
                WHERE status IN ('pending', 'failed')
                AND (last_attempt_at IS NULL OR last_attempt_at < NOW() - INTERVAL 10 MINUTE) -- Simple retry delay
                ORDER BY attempts ASC, created_at ASC
                LIMIT " . self::MAX_EMAILS_PER_BATCH;

        $emails_to_send = $this->db->fetchAll($sql);

        // Mark these emails as 'processing' to avoid race conditions
        if (!empty($emails_to_send)) {
            $ids = array_column($emails_to_send, 'queue_id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $update_sql = "UPDATE {$this->queue_table} SET status = 'processing', attempts = attempts + 1, last_attempt_at = NOW() WHERE queue_id IN ({$placeholders})";
            try {
                $this->db->query($update_sql, $ids);
            } catch (PDOException $e) {
                error_log('MassMailerQueueManager: Error marking emails as processing: ' . $e->getMessage());
                // If update fails, these might be picked up again, but it's better than not processing.
            }
        }
        return $emails_to_send;
    }

    /**
     * Processes a batch of emails from the queue.
     * This is the core sending logic triggered by a cron job.
     */
    public function processQueueBatch() {
        error_log('MassMailerQueueManager: Starting queue batch processing.');

        // Check if a batch was processed very recently to enforce throttling
        // This is a simple check; a more robust solution might use a lock file or a dedicated "worker_status" table
        // For simplicity, we'll assume cron runs are spaced out.
        // If this were a continuous worker, you'd implement sleep/delays.

        $emails_to_process = $this->getEmailsForSending();
        $mailer = new MassMailerMailer();

        if (empty($emails_to_process)) {
            error_log('MassMailerQueueManager: No pending emails in queue to process.');
            return;
        }

        foreach ($emails_to_process as $queue_item) {
            $campaign_id = $queue_item['campaign_id'];
            $subscriber_id = $queue_item['subscriber_id'];
            $queue_id = $queue_item['queue_id'];

            $campaign = $this->campaign_manager->getCampaign($campaign_id);
            $subscriber = $this->subscriber_manager->getSubscriber($subscriber_id);

            if (!$campaign || !$subscriber || $subscriber['status'] !== 'subscribed') {
                // Skip if campaign or subscriber no longer exists or subscriber is not active
                $this->updateQueueItemStatus($queue_id, 'skipped', 'Campaign or subscriber not found/active.');
                $this->campaign_manager->updateSentCount($campaign_id, 0); // Don't increment sent count for skipped
                continue;
            }

            // Attempt to send the email
            $sent = $mailer->sendTemplateEmailToSubscriber($campaign['template_id'], $subscriber_id, $campaign['subject']);

            if ($sent) {
                $this->updateQueueItemStatus($queue_id, 'sent');
                $this->campaign_manager->updateSentCount($campaign_id, 1); // Increment sent count
            } else {
                $error_msg = 'Email sending failed for ' . $subscriber['email'];
                $this->updateQueueItemStatus($queue_id, 'failed', $error_msg);
                error_log('MassMailerQueueManager: ' . $error_msg);
            }
        }
        error_log('MassMailerQueueManager: Finished queue batch processing. Processed ' . count($emails_to_process) . ' emails.');

        // After processing, check if the campaign is fully sent
        $campaign_status_check = $this->campaign_manager->getCampaign($campaign_id);
        if ($campaign_status_check && $campaign_status_check['sent_count'] >= $campaign_status_check['total_recipients']) {
            $this->campaign_manager->updateCampaignStatus($campaign_id, 'sent');
            error_log('MassMailerQueueManager: Campaign ' . $campaign_id . ' marked as SENT.');
        }
    }

    /**
     * Updates the status of a single queue item.
     *
     * @param int $queue_id The ID of the queue item.
     * @param string $status The new status (e.g., 'sent', 'failed', 'skipped').
     * @param string $error_message Optional error message.
     * @return bool True on success, false on failure.
     */
    public function updateQueueItemStatus($queue_id, $status, $error_message = null) {
        try {
            $sql = "UPDATE {$this->queue_table} SET status = :status, error_message = :error_message, sent_at = NOW() WHERE queue_id = :queue_id";
            if ($status === 'failed' || $status === 'skipped') {
                 $sql = "UPDATE {$this->queue_table} SET status = :status, error_message = :error_message WHERE queue_id = :queue_id";
            }
            $stmt = $this->db->query($sql, [
                ':status' => $status,
                ':error_message' => $error_message,
                ':queue_id' => $queue_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerQueueManager: Error updating queue item status ' . $queue_id . ': ' . $e->getMessage());
            return false;
        }
    }
}
