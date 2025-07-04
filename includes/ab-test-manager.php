<?php
/**
 * Mass Mailer A/B Test Manager
 *
 * Manages the creation, execution, and analysis of A/B tests for campaigns.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure core dependencies are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerCampaignManager')) {
    require_once dirname(__FILE__) . '/campaign-manager.php';
}
if (!class_exists('MassMailerSegmentManager')) {
    require_once dirname(__FILE__) . '/segment-manager.php';
}
if (!class_exists('MassMailerQueueManager')) {
    require_once dirname(__FILE__) . '/queue-manager.php';
}
if (!class_exists('MassMailerTracker')) {
    require_once dirname(__FILE__) . '/tracker.php';
}

class MassMailerABTestManager {
    private $db;
    private $ab_tests_table;
    private $campaign_manager;
    private $segment_manager;
    private $queue_manager;
    private $tracker;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->ab_tests_table = MM_TABLE_PREFIX . 'ab_tests'; // New table for A/B tests
        $this->campaign_manager = new MassMailerCampaignManager();
        $this->segment_manager = new MassMailerSegmentManager();
        $this->queue_manager = new MassMailerQueueManager();
        $this->tracker = new MassMailerTracker();
    }

    /**
     * Creates the mm_ab_tests table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createABTestsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->ab_tests_table}` (
            `ab_test_id` INT AUTO_INCREMENT PRIMARY KEY,
            `test_name` VARCHAR(255) NOT NULL UNIQUE,
            `test_type` ENUM('subject_line', 'content') NOT NULL, -- Type of test
            `variant_campaign_ids` JSON NOT NULL, -- JSON array of campaign IDs (e.g., [1, 2])
            `audience_split_percentage` INT DEFAULT 10, -- Percentage of total segment to use for testing (e.g., 10 for 10%)
            `winner_criteria` ENUM('opens', 'clicks') DEFAULT 'clicks', -- Metric to determine winner
            `status` ENUM('draft', 'running', 'completed', 'cancelled') DEFAULT 'draft',
            `winner_campaign_id` INT NULL, -- ID of the winning campaign variant
            `remaining_audience_sent` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerABTestManager: mm_ab_tests table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerABTestManager: Error creating mm_ab_tests table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new A/B test.
     *
     * @param string $name The name of the A/B test.
     * @param string $type The type of test ('subject_line' or 'content').
     * @param array $variant_campaign_ids An array of campaign IDs to use as variants (must be 2).
     * @param int $audience_split_percentage Percentage of the segment to test (e.g., 10 for 10%).
     * @param string $winner_criteria Metric for winner ('opens' or 'clicks').
     * @return int|false The ID of the new A/B test on success, false on failure.
     */
    public function createABTest($name, $type, $variant_campaign_ids, $audience_split_percentage, $winner_criteria) {
        if (empty($name) || empty($type) || !is_array($variant_campaign_ids) || count($variant_campaign_ids) !== 2 || empty($audience_split_percentage) || empty($winner_criteria)) {
            error_log('MassMailerABTestManager: Missing or invalid parameters for A/B test creation.');
            return false;
        }

        // Validate variant campaigns exist and belong to the same segment
        $campaign1 = $this->campaign_manager->getCampaign($variant_campaign_ids[0]);
        $campaign2 = $this->campaign_manager->getCampaign($variant_campaign_ids[1]);

        if (!$campaign1 || !$campaign2) {
            error_log('MassMailerABTestManager: One or both variant campaigns not found.');
            return false;
        }
        if ($campaign1['segment_id'] !== $campaign2['segment_id']) {
            error_log('MassMailerABTestManager: Variant campaigns must target the same segment.');
            return false;
        }

        // Check if test name already exists
        $existing_test = $this->db->fetch(
            "SELECT ab_test_id FROM {$this->ab_tests_table} WHERE test_name = :test_name",
            [':test_name' => $name]
        );
        if ($existing_test) {
            error_log('MassMailerABTestManager: A/B test with this name already exists.');
            return false;
        }

        try {
            $sql = "INSERT INTO {$this->ab_tests_table} (test_name, test_type, variant_campaign_ids, audience_split_percentage, winner_criteria, status) VALUES (:name, :type, :variant_campaign_ids, :audience_split_percentage, :winner_criteria, 'draft')";
            $this->db->query($sql, [
                ':name' => $name,
                ':type' => $type,
                ':variant_campaign_ids' => json_encode($variant_campaign_ids),
                ':audience_split_percentage' => $audience_split_percentage,
                ':winner_criteria' => $winner_criteria
            ]);
            $ab_test_id = $this->db->lastInsertId();

            // Update variant campaigns to link to this A/B test
            $this->campaign_manager->updateCampaignABTestInfo($variant_campaign_ids[0], $ab_test_id, 'A');
            $this->campaign_manager->updateCampaignABTestInfo($variant_campaign_ids[1], $ab_test_id, 'B');

            return $ab_test_id;
        } catch (PDOException $e) {
            error_log('MassMailerABTestManager: Error creating A/B test: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single A/B test by ID.
     *
     * @param int $ab_test_id The ID of the A/B test.
     * @return array|false The A/B test data on success, false if not found.
     */
    public function getABTest($ab_test_id) {
        if (empty($ab_test_id)) {
            return false;
        }
        $sql = "SELECT * FROM {$this->ab_tests_table} WHERE ab_test_id = :ab_test_id";
        try {
            $test = $this->db->fetch($sql, [':ab_test_id' => $ab_test_id]);
            if ($test) {
                $test['variant_campaign_ids'] = json_decode($test['variant_campaign_ids'], true);
            }
            return $test;
        } catch (PDOException $e) {
            error_log('MassMailerABTestManager: Error getting A/B test: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all A/B tests.
     *
     * @return array An array of all A/B test data, or an empty array.
     */
    public function getAllABTests() {
        $sql = "SELECT * FROM {$this->ab_tests_table} ORDER BY created_at DESC";
        try {
            $tests = $this->db->fetchAll($sql);
            foreach ($tests as &$test) {
                $test['variant_campaign_ids'] = json_decode($test['variant_campaign_ids'], true);
            }
            return $tests;
        } catch (PDOException $e) {
            error_log('MassMailerABTestManager: Error getting all A/B tests: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update an existing A/B test.
     *
     * @param int $ab_test_id The ID of the A/B test to update.
     * @param string $name The new name.
     * @param string $type The new type.
     * @param array $variant_campaign_ids New variant campaign IDs.
     * @param int $audience_split_percentage New split percentage.
     * @param string $winner_criteria New winner criteria.
     * @param string $status New status.
     * @param int|null $winner_campaign_id New winner campaign ID.
     * @param bool $remaining_audience_sent Whether remaining audience has been sent.
     * @return bool True on success, false on failure.
     */
    public function updateABTest($ab_test_id, $name, $type, $variant_campaign_ids, $audience_split_percentage, $winner_criteria, $status, $winner_campaign_id = null, $remaining_audience_sent = false) {
        if (empty($ab_test_id) || empty($name) || empty($type) || !is_array($variant_campaign_ids) || count($variant_campaign_ids) !== 2 || empty($audience_split_percentage) || empty($winner_criteria) || empty($status)) {
            error_log('MassMailerABTestManager: Missing or invalid parameters for A/B test update.');
            return false;
        }

        // Validate variant campaigns exist and belong to the same segment
        $campaign1 = $this->campaign_manager->getCampaign($variant_campaign_ids[0]);
        $campaign2 = $this->campaign_manager->getCampaign($variant_campaign_ids[1]);

        if (!$campaign1 || !$campaign2) {
            error_log('MassMailerABTestManager: One or both variant campaigns not found for update.');
            return false;
        }
        if ($campaign1['segment_id'] !== $campaign2['segment_id']) {
            error_log('MassMailerABTestManager: Variant campaigns must target the same segment for update.');
            return false;
        }

        // Check if test name already exists for a different ID
        $existing_test = $this->db->fetch(
            "SELECT ab_test_id FROM {$this->ab_tests_table} WHERE test_name = :test_name AND ab_test_id != :ab_test_id",
            [':test_name' => $name, ':ab_test_id' => $ab_test_id]
        );
        if ($existing_test) {
            error_log('MassMailerABTestManager: Another A/B test with this name already exists.');
            return false;
        }

        try {
            $sql = "UPDATE {$this->ab_tests_table} SET test_name = :name, test_type = :type, variant_campaign_ids = :variant_campaign_ids, audience_split_percentage = :audience_split_percentage, winner_criteria = :winner_criteria, status = :status, winner_campaign_id = :winner_campaign_id, remaining_audience_sent = :remaining_audience_sent WHERE ab_test_id = :ab_test_id";
            $stmt = $this->db->query($sql, [
                ':name' => $name,
                ':type' => $type,
                ':variant_campaign_ids' => json_encode($variant_campaign_ids),
                ':audience_split_percentage' => $audience_split_percentage,
                ':winner_criteria' => $winner_criteria,
                ':status' => $status,
                ':winner_campaign_id' => $winner_campaign_id,
                ':remaining_audience_sent' => $remaining_audience_sent,
                ':ab_test_id' => $ab_test_id
            ]);

            // Update variant campaigns to link to this A/B test (if they changed)
            $this->campaign_manager->updateCampaignABTestInfo($variant_campaign_ids[0], $ab_test_id, 'A');
            $this->campaign_manager->updateCampaignABTestInfo($variant_campaign_ids[1], $ab_test_id, 'B');

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerABTestManager: Error updating A/B test: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an A/B test.
     *
     * @param int $ab_test_id The ID of the A/B test to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteABTest($ab_test_id) {
        if (empty($ab_test_id)) {
            error_log('MassMailerABTestManager: A/B Test ID cannot be empty for delete.');
            return false;
        }
        try {
            // Clear AB test info from associated campaigns first
            $test = $this->getABTest($ab_test_id);
            if ($test && !empty($test['variant_campaign_ids'])) {
                foreach ($test['variant_campaign_ids'] as $campaign_id) {
                    $this->campaign_manager->updateCampaignABTestInfo($campaign_id, null, null);
                }
            }

            $sql = "DELETE FROM {$this->ab_tests_table} WHERE ab_test_id = :ab_test_id";
            $stmt = $this->db->query($sql, [':ab_test_id' => $ab_test_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerABTestManager: Error deleting A/B test: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Starts an A/B test by populating the queue with a subset of subscribers
     * for each variant campaign.
     *
     * @param int $ab_test_id The ID of the A/B test to start.
     * @return bool True on success, false on failure.
     */
    public function startABTest($ab_test_id) {
        $test = $this->getABTest($ab_test_id);
        if (!$test || $test['status'] !== 'draft') {
            error_log('MassMailerABTestManager: Cannot start A/B test. Test not found or not in draft status.');
            return false;
        }

        $campaign1_id = $test['variant_campaign_ids'][0];
        $campaign2_id = $test['variant_campaign_ids'][1];

        $campaign1 = $this->campaign_manager->getCampaign($campaign1_id);
        $campaign2 = $this->campaign_manager->getCampaign($campaign2_id);

        if (!$campaign1 || !$campaign2 || $campaign1['segment_id'] !== $campaign2['segment_id']) {
            error_log('MassMailerABTestManager: Invalid variant campaigns or segments for A/B test ' . $ab_test_id);
            return false;
        }

        $segment_id = $campaign1['segment_id'];
        $all_subscribers = $this->segment_manager->getSubscribersInSegment($segment_id);

        if (empty($all_subscribers)) {
            error_log('MassMailerABTestManager: No subscribers in segment ' . $segment_id . ' for A/B test ' . $ab_test_id);
            return false;
        }

        // Shuffle subscribers to ensure random distribution
        shuffle($all_subscribers);

        $test_audience_size = ceil(count($all_subscribers) * ($test['audience_split_percentage'] / 100));
        $variant_audience_size = floor($test_audience_size / 2); // Split evenly between 2 variants

        $subscribers_for_variant1 = array_slice($all_subscribers, 0, $variant_audience_size);
        $subscribers_for_variant2 = array_slice($all_subscribers, $variant_audience_size, $variant_audience_size);

        // Store the IDs of subscribers used in the test, so we don't send to them again for the winner
        // This is a simplified approach. In a real system, you'd have a separate table
        // to track which subscriber received which variant for a given test.
        // For now, we'll rely on the queue's unique key to prevent re-adding.

        $total_added_to_queue = 0;

        // Populate queue for Variant A
        foreach ($subscribers_for_variant1 as $subscriber) {
            if ($this->queue_manager->addSubscriberToQueue($campaign1_id, $subscriber['subscriber_id'])) {
                $total_added_to_queue++;
            }
        }
        // Populate queue for Variant B
        foreach ($subscribers_for_variant2 as $subscriber) {
            if ($this->queue_manager->addSubscriberToQueue($campaign2_id, $subscriber['subscriber_id'])) {
                $total_added_to_queue++;
            }
        }

        // Update campaign total recipients for the test phase
        $this->campaign_manager->setTotalRecipients($campaign1_id, count($subscribers_for_variant1));
        $this->campaign_manager->setTotalRecipients($campaign2_id, count($subscribers_for_variant2));

        // Update A/B test status to 'running'
        $this->updateABTest($ab_test_id, $test['test_name'], $test['test_type'], $test['variant_campaign_ids'], $test['audience_split_percentage'], $test['winner_criteria'], 'running', null, false);

        // Update campaign statuses to 'sending'
        $this->campaign_manager->updateCampaignStatus($campaign1_id, 'sending');
        $this->campaign_manager->updateCampaignStatus($campaign2_id, 'sending');

        error_log('MassMailerABTestManager: A/B test ' . $ab_test_id . ' started. ' . $total_added_to_queue . ' subscribers added to queue for variants.');
        return true;
    }

    /**
     * Determines the winner of an A/B test based on the defined criteria.
     *
     * @param int $ab_test_id The ID of the A/B test.
     * @return int|false The ID of the winning campaign, or false if no winner can be determined.
     */
    public function determineWinner($ab_test_id) {
        $test = $this->getABTest($ab_test_id);
        if (!$test || $test['status'] !== 'running') {
            error_log('MassMailerABTestManager: Cannot determine winner. Test not found or not in running status.');
            return false;
        }

        $campaign1_id = $test['variant_campaign_ids'][0];
        $campaign2_id = $test['variant_campaign_ids'][1];

        $campaign1_stats = $this->tracker->getCampaignStats($campaign1_id); // Assuming getCampaignStats exists
        $campaign2_stats = $this->tracker->getCampaignStats($campaign2_id);

        if (!$campaign1_stats || !$campaign2_stats) {
            error_log('MassMailerABTestManager: Could not retrieve stats for variant campaigns of A/B test ' . $ab_test_id);
            return false;
        }

        $winner_id = false;
        $metric1 = 0;
        $metric2 = 0;

        if ($test['winner_criteria'] === 'opens') {
            $metric1 = $campaign1_stats['open_count'] ?? 0;
            $metric2 = $campaign2_stats['open_count'] ?? 0;
        } elseif ($test['winner_criteria'] === 'clicks') {
            $metric1 = $campaign1_stats['unique_click_count'] ?? 0; // Use unique clicks for fairness
            $metric2 = $campaign2_stats['unique_click_count'] ?? 0;
        }

        if ($metric1 > $metric2) {
            $winner_id = $campaign1_id;
        } elseif ($metric2 > $metric1) {
            $winner_id = $campaign2_id;
        } else {
            // Tie-breaker: default to first variant or random, or require manual decision
            error_log('MassMailerABTestManager: A/B test ' . $ab_test_id . ' resulted in a tie. No automatic winner.');
            return false; // No clear winner
        }

        if ($winner_id) {
            $this->updateABTest($ab_test_id, $test['test_name'], $test['test_type'], $test['variant_campaign_ids'], $test['audience_split_percentage'], $test['winner_criteria'], 'completed', $winner_id, false);
            error_log('MassMailerABTestManager: Winner determined for A/B test ' . $ab_test_id . ': Campaign ' . $winner_id);
        }
        return $winner_id;
    }

    /**
     * Sends the winning campaign to the remaining audience.
     *
     * @param int $ab_test_id The ID of the A/B test.
     * @return bool True on success, false on failure.
     */
    public function sendWinnerToRemainingAudience($ab_test_id) {
        $test = $this->getABTest($ab_test_id);
        if (!$test || $test['status'] !== 'completed' || !$test['winner_campaign_id'] || $test['remaining_audience_sent']) {
            error_log('MassMailerABTestManager: Cannot send winner. Test not completed, no winner, or already sent.');
            return false;
        }

        $winner_campaign = $this->campaign_manager->getCampaign($test['winner_campaign_id']);
        if (!$winner_campaign) {
            error_log('MassMailerABTestManager: Winner campaign ' . $test['winner_campaign_id'] . ' not found.');
            return false;
        }

        $segment_id = $winner_campaign['segment_id'];
        $all_subscribers = $this->segment_manager->getSubscribersInSegment($segment_id);

        // Get subscribers who were part of the initial test group for *any* variant
        // This is a simplified way to get the tested group. A more robust solution
        // would involve logging which subscribers were part of the test group.
        $tested_subscriber_ids = [];
        foreach ($test['variant_campaign_ids'] as $variant_campaign_id) {
            $queue_entries = $this->queue_manager->getQueueEntriesForCampaign($variant_campaign_id); // Assuming this method exists
            foreach ($queue_entries as $entry) {
                $tested_subscriber_ids[$entry['subscriber_id']] = true;
            }
        }

        $remaining_subscribers = [];
        foreach ($all_subscribers as $subscriber) {
            if (!isset($tested_subscriber_ids[$subscriber['subscriber_id']])) {
                $remaining_subscribers[] = $subscriber;
            }
        }

        if (empty($remaining_subscribers)) {
            error_log('MassMailerABTestManager: No remaining audience for A/B test ' . $ab_test_id);
            $this->updateABTest($ab_test_id, $test['test_name'], $test['test_type'], $test['variant_campaign_ids'], $test['audience_split_percentage'], $test['winner_criteria'], 'completed', $test['winner_campaign_id'], true);
            return true;
        }

        $total_added_to_queue = 0;
        foreach ($remaining_subscribers as $subscriber) {
            // Add winner campaign to queue for remaining audience
            if ($this->queue_manager->addSubscriberToQueue($winner_campaign['campaign_id'], $subscriber['subscriber_id'])) {
                $total_added_to_queue++;
            }
        }

        // Update winner campaign's total recipients to include remaining audience
        $this->campaign_manager->setTotalRecipients($winner_campaign['campaign_id'], $total_added_to_queue + $winner_campaign['total_recipients']);
        $this->campaign_manager->updateCampaignStatus($winner_campaign['campaign_id'], 'sending');

        // Mark A/B test as remaining audience sent
        $this->updateABTest($ab_test_id, $test['test_name'], $test['test_type'], $test['variant_campaign_ids'], $test['audience_split_percentage'], $test['winner_criteria'], 'completed', $test['winner_campaign_id'], true);

        error_log('MassMailerABTestManager: Winner campaign ' . $winner_campaign['campaign_id'] . ' sent to ' . $total_added_to_queue . ' remaining subscribers.');
        return true;
    }
}
