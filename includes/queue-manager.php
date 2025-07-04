<?php
/**
 * Mass Mailer Queue Manager - Segment Integration
 *
 * Updates the queue population logic to retrieve subscribers from a segment.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class, Mailer, Subscriber, Campaign, and Segment Managers are loaded
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
// NEW: Include Segment Manager
if (!class_exists('MassMailerSegmentManager')) {
    require_once dirname(__FILE__) . '/segment-manager.php';
}

class MassMailerQueueManager {
    private $db;
    private $queue_table;
    private $mailer;
    private $subscriber_manager;
    private $campaign_manager;
    private $segment_manager; // New segment manager instance

    // Configuration for throttling
    const MAX_EMAILS_PER_BATCH = 50; // Number of emails to process in one cron run
    const BATCH_PROCESSING_INTERVAL_SECONDS = 60; // Minimum seconds between batch runs (conceptual for cron)

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->queue_table = MM_TABLE_PREFIX . 'queue';
        $this->mailer = new MassMailerMailer();
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->campaign_manager = new MassMailerCampaignManager();
        $this->segment_manager = new MassMailerSegmentManager(); // Initialize segment manager
    }

    // createQueueTable remains the same as it uses campaign_id and subscriber_id

    /**
     * Adds subscribers from a campaign's target segment to the email queue.
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

        // Get all subscribers for the campaign's segment
        $subscribers = $this->segment_manager->getSubscribersInSegment($campaign['segment_id']); // Use segment_manager
        if (empty($subscribers)) {
            error_log('MassMailerQueueManager: No subscribers found for segment ' . $campaign['segment_id'] . '.');
            return 0;
        }

        $added_count = 0;
        foreach ($subscribers as $subscriber) {
            // Only add if subscriber is 'subscribed'
            // The segment manager should ideally already filter by active status, but a final check is good.
            if ($subscriber['status'] === 'subscribed') {
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
                error_log('MassMailerQueueManager: Skipping subscriber ' . $subscriber['email'] . ' (ID: ' . $subscriber['subscriber_id'] . ') due to status: ' . $subscriber['status']);
            }
        }

        // Update total recipients for the campaign
        $this->campaign_manager->setTotalRecipients($campaign_id, $added_count);
        error_log('MassMailerQueueManager: Added ' . $added_count . ' subscribers to queue for campaign ' . $campaign_id);
        return $added_count;
    }

    // getEmailsForSending, processQueueBatch, updateQueueItemStatus remain the same
}
