<?php
/**
 * Mass Mailer Campaign Manager - Segment Integration
 *
 * Updates the campaign management logic to use segments for targeting.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class, List Manager, Template Manager, and Segment Manager are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerListManager')) {
    require_once dirname(__FILE__) . '/list-manager.php';
}
if (!class_exists('MassMailerTemplateManager')) {
    require_once dirname(__FILE__) . '/template-manager.php';
}
// NEW: Include Segment Manager
if (!class_exists('MassMailerSegmentManager')) {
    require_once dirname(__FILE__) . '/segment-manager.php';
}

class MassMailerCampaignManager {
    private $db;
    private $table_name;
    private $list_manager;
    private $template_manager;
    private $segment_manager; // New segment manager instance

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->table_name = MM_TABLE_PREFIX . 'campaigns';
        $this->list_manager = new MassMailerListManager(); // Still useful for general list operations
        $this->template_manager = new MassMailerTemplateManager();
        $this->segment_manager = new MassMailerSegmentManager(); // Initialize segment manager
    }

    /**
     * Creates the mm_campaigns table if it doesn't exist.
     * Updated to use segment_id instead of list_id.
     */
    public function createCampaignTable() {
        // IMPORTANT: If you already have mm_campaigns table, you might need an ALTER TABLE statement
        // to add `segment_id` column and remove/make nullable `list_id`.
        // For a fresh install, this will create the table with segment_id.
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table_name}` (
            `campaign_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_name` VARCHAR(255) NOT NULL UNIQUE,
            `template_id` INT NOT NULL,
            `segment_id` INT NOT NULL, -- Changed from list_id to segment_id
            `subject` VARCHAR(255) NOT NULL,
            `status` ENUM('draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled') DEFAULT 'draft',
            `send_at` DATETIME NULL,
            `sent_count` INT DEFAULT 0,
            `total_recipients` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`template_id`) REFERENCES " . MM_TABLE_PREFIX . "templates(`template_id`) ON DELETE CASCADE,
            FOREIGN KEY (`segment_id`) REFERENCES " . MM_TABLE_PREFIX . "segments(`segment_id`) ON DELETE CASCADE -- New FK
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerCampaignManager: mm_campaigns table created/checked successfully (with segment_id).');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error creating mm_campaigns table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new email campaign.
     *
     * @param string $name The name of the campaign.
     * @param int $template_id The ID of the template to use.
     * @param int $segment_id The ID of the segment to target.
     * @param string $subject The campaign-specific subject line.
     * @param string|null $send_at The scheduled send date/time (YYYY-MM-DD HH:MM:SS), null for immediate/draft.
     * @return int|false The ID of the newly created campaign on success, false on failure.
     */
    public function createCampaign($name, $template_id, $segment_id, $subject, $send_at = null) {
        if (empty($name) || empty($template_id) || empty($segment_id) || empty($subject)) {
            error_log('MassMailerCampaignManager: Campaign name, template ID, segment ID, and subject cannot be empty.');
            return false;
        }

        // Validate template and segment existence
        if (!$this->template_manager->getTemplate($template_id)) {
            error_log('MassMailerCampaignManager: Invalid template ID provided.');
            return false;
        }
        if (!$this->segment_manager->getSegment($segment_id)) { // Validate segment
            error_log('MassMailerCampaignManager: Invalid segment ID provided.');
            return false;
        }

        // Check if campaign with this name already exists
        $existing_campaign = $this->db->fetch(
            "SELECT campaign_id FROM {$this->table_name} WHERE campaign_name = :campaign_name",
            [':campaign_name' => $name]
        );
        if ($existing_campaign) {
            error_log('MassMailerCampaignManager: Campaign with this name already exists.');
            return false;
        }

        $status = ($send_at && strtotime($send_at) > time()) ? 'scheduled' : 'draft';

        try {
            $sql = "INSERT INTO {$this->table_name} (campaign_name, template_id, segment_id, subject, status, send_at) VALUES (:campaign_name, :template_id, :segment_id, :subject, :status, :send_at)";
            $this->db->query($sql, [
                ':campaign_name' => $name,
                ':template_id' => $template_id,
                ':segment_id' => $segment_id, // Use segment_id
                ':subject' => $subject,
                ':status' => $status,
                ':send_at' => $send_at
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error creating campaign: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single campaign by ID.
     * Updated to join with segments table.
     *
     * @param int $campaign_id The ID of the campaign.
     * @return array|false The campaign data on success, false if not found.
     */
    public function getCampaign($campaign_id) {
        if (empty($campaign_id)) {
            return false;
        }
        $sql = "SELECT c.*, s.segment_name, t.template_name
                FROM {$this->table_name} c
                JOIN " . MM_TABLE_PREFIX . "segments s ON c.segment_id = s.segment_id -- Join segments
                JOIN " . MM_TABLE_PREFIX . "templates t ON c.template_id = t.template_id
                WHERE c.campaign_id = :campaign_id";
        try {
            return $this->db->fetch($sql, [':campaign_id' => $campaign_id]);
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error getting campaign: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all campaigns.
     * Updated to join with segments table.
     *
     * @return array An array of all campaign data, or an empty array.
     */
    public function getAllCampaigns() {
        $sql = "SELECT c.*, s.segment_name, t.template_name
                FROM {$this->table_name} c
                JOIN " . MM_TABLE_PREFIX . "segments s ON c.segment_id = s.segment_id -- Join segments
                JOIN " . MM_TABLE_PREFIX . "templates t ON c.template_id = t.template_id
                ORDER BY c.created_at DESC";
        try {
            return $this->db->fetchAll($sql);
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error getting all campaigns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update an existing email campaign.
     * Updated to use segment_id.
     *
     * @param int $campaign_id The ID of the campaign to update.
     * @param string $name The new name of the campaign.
     * @param int $template_id The new template ID.
     * @param int $segment_id The new segment ID.
     * @param string $subject The new subject.
     * @param string $status The new status.
     * @param string|null $send_at The new scheduled send date/time.
     * @return bool True on success, false on failure.
     */
    public function updateCampaign($campaign_id, $name, $template_id, $segment_id, $subject, $status, $send_at = null) {
        if (empty($campaign_id) || empty($name) || empty($template_id) || empty($segment_id) || empty($subject) || empty($status)) {
            error_log('MassMailerCampaignManager: Campaign ID, name, template ID, segment ID, subject, and status cannot be empty for update.');
            return false;
        }

        // Validate template and segment existence
        if (!$this->template_manager->getTemplate($template_id)) {
            error_log('MassMailerCampaignManager: Invalid template ID provided for update.');
            return false;
        }
        if (!$this->segment_manager->getSegment($segment_id)) { // Validate segment
            error_log('MassMailerCampaignManager: Invalid segment ID provided for update.');
            return false;
        }

        // Check if campaign name already exists for a different ID
        $existing_campaign = $this->db->fetch(
            "SELECT campaign_id FROM {$this->table_name} WHERE campaign_name = :campaign_name AND campaign_id != :campaign_id",
            [':campaign_name' => $name, ':campaign_id' => $campaign_id]
        );
        if ($existing_campaign) {
            error_log('MassMailerCampaignManager: Another campaign with this name already exists.');
            return false;
        }

        try {
            $sql = "UPDATE {$this->table_name} SET campaign_name = :campaign_name, template_id = :template_id, segment_id = :segment_id, subject = :subject, status = :status, send_at = :send_at WHERE campaign_id = :campaign_id";
            $stmt = $this->db->query($sql, [
                ':campaign_name' => $name,
                ':template_id' => $template_id,
                ':segment_id' => $segment_id, // Use segment_id
                ':subject' => $subject,
                ':status' => $status,
                ':send_at' => $send_at,
                ':campaign_id' => $campaign_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error updating campaign: ' . $e->getMessage());
            return false;
        }
    }

    // updateCampaignStatus, updateSentCount, setTotalRecipients, deleteCampaign remain the same
    // as they operate on campaign_id directly or update counts.
}
