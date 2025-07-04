<?php
/**
 * Mass Mailer Tracker
 *
 * Handles the logging of email opens and clicks.
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
        $this->opens_table = MM_TABLE_PREFIX . 'opens';   // New table for opens
        $this->clicks_table = MM_TABLE_PREFIX . 'clicks'; // New table for clicks
    }

    /**
     * Creates the mm_opens and mm_clicks tables if they don't exist.
     * This would typically be called during plugin activation.
     */
    public function createTrackingTables() {
        $sql_opens = "CREATE TABLE IF NOT EXISTS `{$this->opens_table}` (
            `open_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `subscriber_id` INT NOT NULL,
            `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            UNIQUE KEY `campaign_subscriber_open_idx` (`campaign_id`, `subscriber_id`), -- One unique open per campaign per subscriber
            FOREIGN KEY (`campaign_id`) REFERENCES " . MM_TABLE_PREFIX . "campaigns(`campaign_id`) ON DELETE CASCADE,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $sql_clicks = "CREATE TABLE IF NOT EXISTS `{$this->clicks_table}` (
            `click_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `subscriber_id` INT NOT NULL,
            `original_url` TEXT NOT NULL,
            `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            FOREIGN KEY (`campaign_id`) REFERENCES " . MM_TABLE_PREFIX . "campaigns(`campaign_id`) ON DELETE CASCADE,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        try {
            $this->db->query($sql_opens);
            $this->db->query($sql_clicks);
            error_log('MassMailerTracker: mm_opens and mm_clicks tables created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error creating tracking tables: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Logs an email open event.
     *
     * @param int $campaign_id The ID of the campaign.
     * @param int $subscriber_id The ID of the subscriber.
     * @param string $ip_address The IP address of the opener.
     * @param string $user_agent The user agent string of the opener.
     * @return bool True on success, false on failure (e.g., already logged).
     */
    public function logOpen($campaign_id, $subscriber_id, $ip_address = null, $user_agent = null) {
        if (empty($campaign_id) || empty($subscriber_id)) {
            error_log('MassMailerTracker: Campaign ID or Subscriber ID missing for open log.');
            return false;
        }

        try {
            // Use INSERT IGNORE to only log the first open for a given campaign/subscriber pair
            $sql = "INSERT IGNORE INTO {$this->opens_table} (campaign_id, subscriber_id, ip_address, user_agent) VALUES (:campaign_id, :subscriber_id, :ip_address, :user_agent)";
            $stmt = $this->db->query($sql, [
                ':campaign_id' => $campaign_id,
                ':subscriber_id' => $subscriber_id,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error logging open: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Logs an email click event.
     *
     * @param int $campaign_id The ID of the campaign.
     * @param int $subscriber_id The ID of the subscriber.
     * @param string $original_url The original URL that was clicked.
     * @param string $ip_address The IP address of the clicker.
     * @param string $user_agent The user agent string of the clicker.
     * @return int|false The ID of the new click record on success, false on failure.
     */
    public function logClick($campaign_id, $subscriber_id, $original_url, $ip_address = null, $user_agent = null) {
        if (empty($campaign_id) || empty($subscriber_id) || empty($original_url)) {
            error_log('MassMailerTracker: Missing data for click log.');
            return false;
        }

        try {
            $sql = "INSERT INTO {$this->clicks_table} (campaign_id, subscriber_id, original_url, ip_address, user_agent) VALUES (:campaign_id, :subscriber_id, :original_url, :ip_address, :user_agent)";
            $this->db->query($sql, [
                ':campaign_id' => $campaign_id,
                ':subscriber_id' => $subscriber_id,
                ':original_url' => $original_url,
                ':ip_address' => $ip_address,
                ':user_agent' => $user_agent
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error logging click: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get open count for a campaign.
     *
     * @param int $campaign_id The ID of the campaign.
     * @return int The number of unique opens.
     */
    public function getCampaignOpenCount($campaign_id) {
        $sql = "SELECT COUNT(DISTINCT subscriber_id) FROM {$this->opens_table} WHERE campaign_id = :campaign_id";
        try {
            return $this->db->fetch($sql, [':campaign_id' => $campaign_id])['COUNT(DISTINCT subscriber_id)'];
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting campaign open count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get click count for a campaign.
     *
     * @param int $campaign_id The ID of the campaign.
     * @return int The total number of clicks.
     */
    public function getCampaignClickCount($campaign_id) {
        $sql = "SELECT COUNT(*) FROM {$this->clicks_table} WHERE campaign_id = :campaign_id";
        try {
            return $this->db->fetch($sql, [':campaign_id' => $campaign_id])['COUNT(*)'];
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting campaign click count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get unique click count for a campaign.
     *
     * @param int $campaign_id The ID of the campaign.
     * @return int The number of unique subscribers who clicked.
     */
    public function getCampaignUniqueClickCount($campaign_id) {
        $sql = "SELECT COUNT(DISTINCT subscriber_id) FROM {$this->clicks_table} WHERE campaign_id = :campaign_id";
        try {
            return $this->db->fetch($sql, [':campaign_id' => $campaign_id])['COUNT(DISTINCT subscriber_id)'];
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting campaign unique click count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get all opens for a campaign.
     *
     * @param int $campaign_id
     * @return array
     */
    public function getCampaignOpens($campaign_id) {
        $sql = "SELECT o.*, s.email FROM {$this->opens_table} o
                JOIN " . MM_TABLE_PREFIX . "subscribers s ON o.subscriber_id = s.subscriber_id
                WHERE o.campaign_id = :campaign_id ORDER BY o.opened_at DESC";
        try {
            return $this->db->fetchAll($sql, [':campaign_id' => $campaign_id]);
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting campaign opens: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all clicks for a campaign.
     *
     * @param int $campaign_id
     * @return array
     */
    public function getCampaignClicks($campaign_id) {
        $sql = "SELECT c.*, s.email FROM {$this->clicks_table} c
                JOIN " . MM_TABLE_PREFIX . "subscribers s ON c.subscriber_id = s.subscriber_id
                WHERE c.campaign_id = :campaign_id ORDER BY c.clicked_at DESC";
        try {
            return $this->db->fetchAll($sql, [':campaign_id' => $campaign_id]);
        } catch (PDOException $e) {
            error_log('MassMailerTracker: Error getting campaign clicks: ' . $e->getMessage());
            return [];
        }
    }
}
