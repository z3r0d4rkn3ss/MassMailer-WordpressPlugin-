<?php
/**
 * Mass Mailer Campaign Manager
 *
 * Provides functions for managing email campaigns (CRUD operations).
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class, List Manager, and Template Manager are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerListManager')) {
    require_once dirname(__FILE__) . '/list-manager.php';
}
if (!class_exists('MassMailerTemplateManager')) {
    require_once dirname(__FILE__) . '/template-manager.php';
}

class MassMailerCampaignManager {
    private $db;
    private $table_name;
    private $list_manager;
    private $template_manager;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->table_name = MM_TABLE_PREFIX . 'campaigns'; // Assuming a new 'mm_campaigns' table
        $this->list_manager = new MassMailerListManager();
        $this->template_manager = new MassMailerTemplateManager();
    }

    /**
     * Creates the mm_campaigns table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createCampaignTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table_name}` (
            `campaign_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_name` VARCHAR(255) NOT NULL UNIQUE,
            `template_id` INT NOT NULL,
            `list_id` INT NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `status` ENUM('draft', 'scheduled', 'sending', 'sent', 'paused', 'cancelled') DEFAULT 'draft',
            `send_at` DATETIME NULL,
            `sent_count` INT DEFAULT 0,
            `total_recipients` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`template_id`) REFERENCES " . MM_TABLE_PREFIX . "templates(`template_id`) ON DELETE CASCADE,
            FOREIGN KEY (`list_id`) REFERENCES " . MM_TABLE_PREFIX . "lists(`list_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerCampaignManager: mm_campaigns table created/checked successfully.');
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
     * @param int $list_id The ID of the list to target.
     * @param string $subject The campaign-specific subject line.
     * @param string|null $send_at The scheduled send date/time (YYYY-MM-DD HH:MM:SS), null for immediate/draft.
     * @return int|false The ID of the newly created campaign on success, false on failure.
     */
    public function createCampaign($name, $template_id, $list_id, $subject, $send_at = null) {
        if (empty($name) || empty($template_id) || empty($list_id) || empty($subject)) {
            error_log('MassMailerCampaignManager: Campaign name, template ID, list ID, and subject cannot be empty.');
            return false;
        }

        // Validate template and list existence
        if (!$this->template_manager->getTemplate($template_id)) {
            error_log('MassMailerCampaignManager: Invalid template ID provided.');
            return false;
        }
        if (!$this->list_manager->getList($list_id)) {
            error_log('MassMailerCampaignManager: Invalid list ID provided.');
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
            $sql = "INSERT INTO {$this->table_name} (campaign_name, template_id, list_id, subject, status, send_at) VALUES (:campaign_name, :template_id, :list_id, :subject, :status, :send_at)";
            $this->db->query($sql, [
                ':campaign_name' => $name,
                ':template_id' => $template_id,
                ':list_id' => $list_id,
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
     *
     * @param int $campaign_id The ID of the campaign.
     * @return array|false The campaign data on success, false if not found.
     */
    public function getCampaign($campaign_id) {
        if (empty($campaign_id)) {
            return false;
        }
        $sql = "SELECT c.*, l.list_name, t.template_name
                FROM {$this->table_name} c
                JOIN " . MM_TABLE_PREFIX . "lists l ON c.list_id = l.list_id
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
     *
     * @return array An array of all campaign data, or an empty array.
     */
    public function getAllCampaigns() {
        $sql = "SELECT c.*, l.list_name, t.template_name
                FROM {$this->table_name} c
                JOIN " . MM_TABLE_PREFIX . "lists l ON c.list_id = l.list_id
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
     *
     * @param int $campaign_id The ID of the campaign to update.
     * @param string $name The new name of the campaign.
     * @param int $template_id The new template ID.
     * @param int $list_id The new list ID.
     * @param string $subject The new subject.
     * @param string $status The new status.
     * @param string|null $send_at The new scheduled send date/time.
     * @return bool True on success, false on failure.
     */
    public function updateCampaign($campaign_id, $name, $template_id, $list_id, $subject, $status, $send_at = null) {
        if (empty($campaign_id) || empty($name) || empty($template_id) || empty($list_id) || empty($subject) || empty($status)) {
            error_log('MassMailerCampaignManager: Campaign ID, name, template ID, list ID, subject, and status cannot be empty for update.');
            return false;
        }

        // Validate template and list existence
        if (!$this->template_manager->getTemplate($template_id)) {
            error_log('MassMailerCampaignManager: Invalid template ID provided for update.');
            return false;
        }
        if (!$this->list_manager->getList($list_id)) {
            error_log('MassMailerCampaignManager: Invalid list ID provided for update.');
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
            $sql = "UPDATE {$this->table_name} SET campaign_name = :campaign_name, template_id = :template_id, list_id = :list_id, subject = :subject, status = :status, send_at = :send_at WHERE campaign_id = :campaign_id";
            $stmt = $this->db->query($sql, [
                ':campaign_name' => $name,
                ':template_id' => $template_id,
                ':list_id' => $list_id,
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

    /**
     * Update the status of a campaign.
     *
     * @param int $campaign_id The ID of the campaign.
     * @param string $status The new status.
     * @return bool True on success, false on failure.
     */
    public function updateCampaignStatus($campaign_id, $status) {
        if (empty($campaign_id) || empty($status)) {
            error_log('MassMailerCampaignManager: Campaign ID and status cannot be empty for status update.');
            return false;
        }
        try {
            $sql = "UPDATE {$this->table_name} SET status = :status WHERE campaign_id = :campaign_id";
            $stmt = $this->db->query($sql, [
                ':status' => $status,
                ':campaign_id' => $campaign_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error updating campaign status: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update the sent count for a campaign.
     *
     * @param int $campaign_id The ID of the campaign.
     * @param int $count_increment The number to increment the sent count by.
     * @return bool True on success, false on failure.
     */
    public function updateSentCount($campaign_id, $count_increment = 1) {
        if (empty($campaign_id)) {
            error_log('MassMailerCampaignManager: Campaign ID cannot be empty for sent count update.');
            return false;
        }
        try {
            $sql = "UPDATE {$this->table_name} SET sent_count = sent_count + :increment WHERE campaign_id = :campaign_id";
            $stmt = $this->db->query($sql, [
                ':increment' => $count_increment,
                ':campaign_id' => $campaign_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error updating sent count: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set the total recipients count for a campaign.
     * This is typically set when a campaign is created or scheduled.
     *
     * @param int $campaign_id The ID of the campaign.
     * @param int $count The total number of recipients.
     * @return bool True on success, false on failure.
     */
    public function setTotalRecipients($campaign_id, $count) {
        if (empty($campaign_id)) {
            error_log('MassMailerCampaignManager: Campaign ID cannot be empty for total recipients update.');
            return false;
        }
        try {
            $sql = "UPDATE {$this->table_name} SET total_recipients = :count WHERE campaign_id = :campaign_id";
            $stmt = $this->db->query($sql, [
                ':count' => $count,
                ':campaign_id' => $campaign_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error setting total recipients: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an email campaign.
     *
     * @param int $campaign_id The ID of the campaign to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteCampaign($campaign_id) {
        if (empty($campaign_id)) {
            error_log('MassMailerCampaignManager: Campaign ID cannot be empty for delete.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->table_name} WHERE campaign_id = :campaign_id";
            $stmt = $this->db->query($sql, [':campaign_id' => $campaign_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerCampaignManager: Error deleting campaign: ' . $e->getMessage());
            return false;
        }
    }
}
