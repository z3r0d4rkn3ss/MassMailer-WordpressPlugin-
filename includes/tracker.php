<?php
/**
 * Mass Mailer Tracker - Comprehensive Reporting Updates
 *
 * Adds methods to retrieve aggregated statistics for overall performance and trends.
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
    private $campaigns_table;
    private $subscribers_table;
    private $bounces_log_table;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->opens_table = MM_TABLE_PREFIX . 'opens';
        $this->clicks_table = MM_TABLE_PREFIX . 'clicks';
        $this->campaigns_table = MM_TABLE_PREFIX . 'campaigns';
        $this->subscribers_table = MM_TABLE_PREFIX . 'subscribers';
        $this->bounces_log_table = MM_TABLE_PREFIX . 'bounces_log';
    }

    // createTrackingTables, logOpen, logClick, getCampaignOpenCount, getCampaignClickCount, getCampaignUniqueClickCount, getCampaignOpens, getCampaignClicks, getCampaignStats remain the same.

    /**
     * Retrieves overall email performance statistics.
     *
     * @return array An associative array of overall stats.
     */
    public function getOverallEmailStats() {
        $stats = [
            'total_campaigns_sent' => 0,
            'total_emails_sent' => 0,
            'total_opens' => 0,
            'total_clicks' => 0,
            'total_unique_clicks' => 0,
        ];

        try {
            // Total campaigns sent (status 'sent' or 'sending' with sent_count > 0)
            $campaigns_sent_data = $this->db->fetch(
                "SELECT COUNT(campaign_id) AS count FROM {$this->campaigns_table} WHERE status = 'sent' OR (status = 'sending' AND sent_count > 0)"
            );
            $stats['total_campaigns_sent'] = $campaigns_sent_data ? $campaigns_sent_data['count'] : 0;

            // Total emails sent (sum of sent_count from campaigns)
            $emails_sent_data = $this->db->fetch(
                "SELECT SUM(sent_count) AS sum FROM {$this->campaigns_table} WHERE status = 'sent' OR (status = 'sending' AND sent_count > 0)"
            );
            $stats['total_emails_sent'] = $emails_sent_data ? $emails_sent_data['sum'] : 0;

            // Total opens (unique by subscriber_id and campaign_id)
            $total_opens_data = $this->db->fetch(
                "SELECT COUNT(DISTINCT campaign_id, subscriber_id) AS count FROM {$this->opens_table}"
            );
            $stats['total_opens'] = $total_opens_data ? $total_opens_data['count'] : 0;

            // Total clicks (raw count)
            $total_clicks_data = $this->db->fetch(
                "SELECT COUNT(*) AS count FROM {$this->clicks_table}"
            );
            $stats['total_clicks'] = $total_clicks_data ? $total_clicks_data['count'] : 0;

            // Total unique clicks (unique by subscriber_id and campaign_id)
            $total_unique_clicks_data = $this->db->fetch(
                "SELECT COUNT(DISTINCT campaign_id, subscriber_id) AS count FROM {$this->clicks_table}"
            );
            $stats['total_unique_clicks'] = $total_unique_clicks_data ? $total_unique_clicks_data['count'] : 0;

        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting overall email stats: ' . $e->getMessage());
        }
        return $stats;
    }

    /**
     * Retrieves subscriber growth data over time.
     *
     * @param string $period 'daily', 'weekly', 'monthly' (default: 'monthly')
     * @param int $limit Number of periods to retrieve (default: 12)
     * @return array An array of objects/arrays with 'date' and 'count' of new subscribers.
     */
    public function getSubscriberGrowthOverTime($period = 'monthly', $limit = 12) {
        $date_format = '%Y-%m'; // Default for monthly
        $group_by_clause = "DATE_FORMAT(subscribed_at, '%Y-%m')";

        switch ($period) {
            case 'daily':
                $date_format = '%Y-%m-%d';
                $group_by_clause = "DATE_FORMAT(subscribed_at, '%Y-%m-%d')";
                break;
            case 'weekly':
                $date_format = '%Y-%u'; // Year and week number
                $group_by_clause = "DATE_FORMAT(subscribed_at, '%Y-%u')";
                break;
            case 'monthly':
            default:
                // Already set
                break;
        }

        $sql = "SELECT {$group_by_clause} AS date, COUNT(subscriber_id) AS count
                FROM {$this->subscribers_table}
                WHERE subscribed_at IS NOT NULL
                GROUP BY date
                ORDER BY date DESC
                LIMIT :limit"; // Limit to recent periods

        try {
            $raw_data = $this->db->fetchAll($sql, [':limit' => $limit]);
            // Reverse to show oldest first
            return array_reverse($raw_data);
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting subscriber growth data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves top performing campaigns based on opens or clicks.
     *
     * @param string $metric 'opens' or 'clicks'
     * @param int $limit Number of campaigns to retrieve.
     * @return array An array of top campaigns with their stats.
     */
    public function getTopPerformingCampaigns($metric = 'opens', $limit = 5) {
        $order_by = '';
        $join_table = '';
        $count_field = '';

        if ($metric === 'opens') {
            $join_table = $this->opens_table;
            $count_field = 'COUNT(DISTINCT o.subscriber_id)'; // Unique opens
            $order_by = 'open_count';
        } elseif ($metric === 'clicks') {
            $join_table = $this->clicks_table;
            $count_field = 'COUNT(DISTINCT cl.subscriber_id)'; // Unique clicks
            $order_by = 'click_count';
        } else {
            return [];
        }

        $sql = "SELECT c.campaign_id, c.campaign_name, c.sent_count,
                       {$count_field} AS {$order_by}
                FROM {$this->campaigns_table} c
                JOIN {$join_table} " . ($metric === 'opens' ? 'o' : 'cl') . " ON c.campaign_id = " . ($metric === 'opens' ? 'o' : 'cl') . ".campaign_id
                WHERE c.status = 'sent'
                GROUP BY c.campaign_id, c.campaign_name, c.sent_count
                ORDER BY {$order_by} DESC
                LIMIT :limit";

        try {
            return $this->db->fetchAll($sql, [':limit' => $limit]);
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting top performing campaigns by ' . $metric . ': ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves overall bounce statistics.
     *
     * @return array An associative array of bounce stats.
     */
    public function getOverallBounceStats() {
        $stats = [
            'total_bounces' => 0,
            'hard_bounces' => 0,
            'soft_bounces' => 0,
            'complaints' => 0,
        ];

        try {
            $total_bounces_data = $this->db->fetch("SELECT COUNT(*) AS count FROM {$this->bounces_log_table}");
            $stats['total_bounces'] = $total_bounces_data ? $total_bounces_data['count'] : 0;

            $hard_bounces_data = $this->db->fetch("SELECT COUNT(*) AS count FROM {$this->bounces_log_table} WHERE bounce_type = 'hard'");
            $stats['hard_bounces'] = $hard_bounces_data ? $hard_bounces_data['count'] : 0;

            $soft_bounces_data = $this->db->fetch("SELECT COUNT(*) AS count FROM {$this->bounces_log_table} WHERE bounce_type = 'soft'");
            $stats['soft_bounces'] = $soft_bounces_data ? $soft_bounces_data['count'] : 0;

            $complaints_data = $this->db->fetch("SELECT COUNT(*) AS count FROM {$this->bounces_log_table} WHERE bounce_type = 'complaint'");
            $stats['complaints'] = $complaints_data ? $complaints_data['count'] : 0;

        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting overall bounce stats: ' . $e->getMessage());
        }
        return $stats;
    }
}
