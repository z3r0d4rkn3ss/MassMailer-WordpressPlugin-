<?php
/**
 * Mass Mailer Queue Manager - A/B Test Integration
 *
 * Adds a new method to add specific subscribers to the queue,
 * which will be used by the A/B Test Manager.
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
if (!class_exists('MassMailerSegmentManager')) {
    require_once dirname(__FILE__) . '/segment-manager.php';
}

class MassMailerQueueManager {
    private $db;
    private $queue_table;
    private $mailer;
    private $subscriber_manager;
    private $campaign_manager;
    private $segment_manager;

    // Configuration for throttling
    const MAX_EMAILS_PER_BATCH = 50;
    const BATCH_PROCESSING_INTERVAL_SECONDS = 60;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->queue_table = MM_TABLE_PREFIX . 'queue';
        $this->mailer = new MassMailerMailer();
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->campaign_manager = new MassMailerCampaignManager();
        $this->segment_manager = new MassMailerSegmentManager();
    }

    // createQueueTable remains the same

    /**
     * Adds subscribers from a campaign's target segment to the email queue.
     * This method is for regular campaigns. For A/B test variants,
     * addSubscriberToQueue should be used directly by ABTestManager.
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

        // If this campaign is part of an A/B test, this method should not be called directly
        // The A/B test manager will handle the population for variants and winners.
        if (!empty($campaign['ab_test_id'])) {
            error_log('MassMailerQueueManager: Campaign ' . $campaign_id . ' is part of an A/B test. Use ABTestManager to populate queue.');
            return false;
        }

        $subscribers = $this->segment_manager->getSubscribersInSegment($campaign['segment_id']);
        if (empty($subscribers)) {
            error_log('MassMailerQueueManager: No subscribers found for segment ' . $campaign['segment_id'] . '.');
            return 0;
        }

        $added_count = 0;
        foreach ($subscribers as $subscriber) {
            if ($subscriber['status'] === 'subscribed') {
                if ($this->addSubscriberToQueue($campaign_id, $subscriber['subscriber_id'])) {
                    $added_count++;
                }
            } else {
                error_log('MassMailerQueueManager: Skipping subscriber ' . $subscriber['email'] . ' (ID: ' . $subscriber['subscriber_id'] . ') due to status: ' . $subscriber['status']);
            }
        }

        $this->campaign_manager->setTotalRecipients($campaign_id, $added_count);
        error_log('MassMailerQueueManager: Added ' . $added_count . ' subscribers to queue for campaign ' . $campaign_id);
        return $added_count;
    }

    /**
     * Adds a single subscriber to the email queue for a specific campaign.
     * This method is now public and can be used by ABTestManager.
     *
     * @param int $campaign_id The ID of the campaign.
     * @param int $subscriber_id The ID of the subscriber.
     * @return bool True on success, false on failure.
     */
    public function addSubscriberToQueue($campaign_id, $subscriber_id) {
        try {
            // Use INSERT IGNORE to prevent duplicates if already in queue for this campaign
            $sql = "INSERT IGNORE INTO {$this->queue_table} (campaign_id, subscriber_id, status) VALUES (:campaign_id, :subscriber_id, 'pending')";
            $stmt = $this->db->query($sql, [
                ':campaign_id' => $campaign_id,
                ':subscriber_id' => $subscriber_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerQueueManager: Error adding subscriber ' . $subscriber_id . ' to queue for campaign ' . $campaign_id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all queue entries for a given campaign.
     * Useful for A/B testing to identify which subscribers were part of the test group.
     *
     * @param int $campaign_id
     * @return array
     */
    public function getQueueEntriesForCampaign($campaign_id) {
        $sql = "SELECT * FROM {$this->queue_table} WHERE campaign_id = :campaign_id";
        try {
            return $this->db->fetchAll($sql, [':campaign_id' => $campaign_id]);
        } catch (PDOException $e) {
            error_log('MassMailerQueueManager: Error getting queue entries for campaign ' . $campaign_id . ': ' . $e->getMessage());
            return [];
        }
    }

    // getEmailsForSending, processQueueBatch, updateQueueItemStatus remain mostly the same
    // processQueueBatch will now use the updated mailer which gets settings from DB.
}
