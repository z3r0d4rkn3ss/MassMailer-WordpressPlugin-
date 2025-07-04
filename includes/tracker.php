<?php
/**
 * Mass Mailer Tracker - A/B Test Integration
 *
 * Adds a method to retrieve aggregated campaign statistics needed for A/B test winner determination.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}

class MassMailerTracker {
    private $db;
    private $opens_table;
    private $clicks_table;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->opens_table = MM_TABLE_PREFIX . 'opens';
        $this->clicks_table = MM_TABLE_PREFIX . 'clicks';
    }

    // createTrackingTables, logOpen, logClick, getCampaignOpenCount, getCampaignClickCount, getCampaignUniqueClickCount, getCampaignOpens, getCampaignClicks remain the same

    /**
     * Retrieves aggregated statistics for a given campaign.
     *
     * @param int $campaign_id The ID of the campaign.
     * @return array An associative array of stats (sent_count, open_count, unique_click_count, etc.), or empty array.
     */
    public function getCampaignStats($campaign_id) {
        // Need to get sent_count from mm_campaigns and then opens/clicks from tracking tables
        // Assuming CampaignManager is available or we fetch it directly.
        // For simplicity, we'll fetch sent_count from queue table where status='sent' for this campaign
        // or from campaign table's sent_count. Let's use campaign table's sent_count for simplicity.
        // And total_recipients from campaign table.

        $stats = [
            'total_recipients' => 0,
            'sent_count' => 0,
            'open_count' => 0,
            'unique_click_count' => 0,
            'click_count' => 0,
        ];

        try {
            // Get sent_count and total_recipients from campaigns table
            $campaign_data = $this->db->fetch(
                "SELECT sent_count, total_recipients FROM " . MM_TABLE_PREFIX . "campaigns WHERE campaign_id = :campaign_id",
                [':campaign_id' => $campaign_id]
            );
            if ($campaign_data) {
                $stats['sent_count'] = $campaign_data['sent_count'];
                $stats['total_recipients'] = $campaign_data['total_recipients'];
            }

            // Get open count
            $open_count_data = $this->db->fetch(
                "SELECT COUNT(DISTINCT subscriber_id) AS open_count FROM {$this->opens_table} WHERE campaign_id = :campaign_id",
                [':campaign_id' => $campaign_id]
            );
            $stats['open_count'] = $open_count_data ? $open_count_data['open_count'] : 0;

            // Get unique click count
            $unique_click_count_data = $this->db->fetch(
                "SELECT COUNT(DISTINCT subscriber_id) AS unique_click_count FROM {$this->clicks_table} WHERE campaign_id = :campaign_id",
                [':campaign_id' => $campaign_id]
            );
            $stats['unique_click_count'] = $unique_click_count_data ? $unique_click_count_data['unique_click_count'] : 0;

            // Get total click count
            $total_click_count_data = $this->db->fetch(
                "SELECT COUNT(*) AS click_count FROM {$this->clicks_table} WHERE campaign_id = :campaign_id",
                [':campaign_id' => $campaign_id]
            );
            $stats['click_count'] = $total_click_count_data ? $total_click_count_data['click_count'] : 0;

            return $stats;

        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting campaign stats for ' . $campaign_id . ': ' . $e->getMessage());
            return $stats; // Return default empty stats on error
        }
    }
}
