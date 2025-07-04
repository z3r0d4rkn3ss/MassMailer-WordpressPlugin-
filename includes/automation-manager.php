<?php
/**
 * Mass Mailer Automation Manager
 *
 * Manages the creation, retrieval, updating, and deletion of automation rules.
 * Also includes the logic for processing and executing these automations.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class, List Manager, Template Manager, Subscriber Manager, and Mailer are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerListManager')) {
    require_once dirname(__FILE__) . '/list-manager.php';
}
if (!class_exists('MassMailerTemplateManager')) {
    require_once dirname(__FILE__) . '/template-manager.php';
}
if (!class_exists('MassMailerSubscriberManager')) {
    require_once dirname(__FILE__) . '/subscriber-manager.php';
}
if (!class_exists('MassMailerMailer')) {
    require_once dirname(__FILE__) . '/mailer.php';
}
if (!class_exists('MassMailerQueueManager')) {
    require_once dirname(__FILE__) . '/queue-manager.php';
}
if (!class_exists('MassMailerTracker')) {
    require_once dirname(__FILE__) . '/tracker.php';
}

class MassMailerAutomationManager {
    private $db;
    private $automations_table;
    private $list_manager;
    private $template_manager;
    private $subscriber_manager;
    private $mailer;
    private $queue_manager;
    private $tracker;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->automations_table = MM_TABLE_PREFIX . 'automations'; // New table for automations
        $this->list_manager = new MassMailerListManager();
        $this->template_manager = new MassMailerTemplateManager();
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->mailer = new MassMailerMailer();
        $this->queue_manager = new MassMailerQueueManager();
        $this->tracker = new MassMailerTracker();
    }

    /**
     * Creates the mm_automations table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createAutomationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->automations_table}` (
            `automation_id` INT AUTO_INCREMENT PRIMARY KEY,
            `automation_name` VARCHAR(255) NOT NULL UNIQUE,
            `trigger_type` VARCHAR(50) NOT NULL, -- e.g., 'subscriber_added', 'campaign_opened', 'campaign_clicked'
            `trigger_config` JSON NULL,          -- JSON string for trigger details (e.g., list_id, campaign_id, link_url)
            `action_type` VARCHAR(50) NOT NULL,  -- e.g., 'send_email', 'add_to_list', 'remove_from_list'
            `action_config` JSON NULL,           -- JSON string for action details (e.g., template_id, target_list_id)
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerAutomationManager: mm_automations table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerAutomationManager: Error creating mm_automations table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new automation rule.
     *
     * @param string $name The name of the automation.
     * @param string $trigger_type The type of trigger.
     * @param array $trigger_config Configuration for the trigger.
     * @param string $action_type The type of action.
     * @param array $action_config Configuration for the action.
     * @param string $status Initial status ('active' or 'inactive').
     * @return int|false The ID of the new automation on success, false on failure.
     */
    public function createAutomation($name, $trigger_type, $trigger_config, $action_type, $action_config, $status = 'active') {
        if (empty($name) || empty($trigger_type) || empty($action_type)) {
            error_log('MassMailerAutomationManager: Name, trigger type, and action type cannot be empty.');
            return false;
        }

        // Check if automation with this name already exists
        $existing_automation = $this->db->fetch(
            "SELECT automation_id FROM {$this->automations_table} WHERE automation_name = :automation_name",
            [':automation_name' => $name]
        );
        if ($existing_automation) {
            error_log('MassMailerAutomationManager: Automation with this name already exists.');
            return false;
        }

        try {
            $sql = "INSERT INTO {$this->automations_table} (automation_name, trigger_type, trigger_config, action_type, action_config, status) VALUES (:name, :trigger_type, :trigger_config, :action_type, :action_config, :status)";
            $this->db->query($sql, [
                ':name' => $name,
                ':trigger_type' => $trigger_type,
                ':trigger_config' => json_encode($trigger_config),
                ':action_type' => $action_type,
                ':action_config' => json_encode($action_config),
                ':status' => $status
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerAutomationManager: Error creating automation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get an automation rule by ID.
     *
     * @param int $automation_id The ID of the automation.
     * @return array|false The automation data on success, false if not found.
     */
    public function getAutomation($automation_id) {
        if (empty($automation_id)) {
            return false;
        }
        $sql = "SELECT * FROM {$this->automations_table} WHERE automation_id = :automation_id";
        try {
            $automation = $this->db->fetch($sql, [':automation_id' => $automation_id]);
            if ($automation) {
                $automation['trigger_config'] = json_decode($automation['trigger_config'], true);
                $automation['action_config'] = json_decode($automation['action_config'], true);
            }
            return $automation;
        } catch (PDOException $e) {
            error_log('MassMailerAutomationManager: Error getting automation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all automation rules.
     *
     * @return array An array of all automation data, or an empty array.
     */
    public function getAllAutomations() {
        $sql = "SELECT * FROM {$this->automations_table} ORDER BY created_at DESC";
        try {
            $automations = $this->db->fetchAll($sql);
            foreach ($automations as &$automation) {
                $automation['trigger_config'] = json_decode($automation['trigger_config'], true);
                $automation['action_config'] = json_decode($automation['action_config'], true);
            }
            return $automations;
        } catch (PDOException $e) {
            error_log('MassMailerAutomationManager: Error getting all automations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update an existing automation rule.
     *
     * @param int $automation_id The ID of the automation to update.
     * @param string $name The new name of the automation.
     * @param string $trigger_type The new trigger type.
     * @param array $trigger_config New trigger configuration.
     * @param string $action_type The new action type.
     * @param array $action_config New action configuration.
     * @param string $status New status ('active' or 'inactive').
     * @return bool True on success, false on failure.
     */
    public function updateAutomation($automation_id, $name, $trigger_type, $trigger_config, $action_type, $action_config, $status) {
        if (empty($automation_id) || empty($name) || empty($trigger_type) || empty($action_type) || empty($status)) {
            error_log('MassMailerAutomationManager: Missing required fields for automation update.');
            return false;
        }

        // Check if automation name already exists for a different ID
        $existing_automation = $this->db->fetch(
            "SELECT automation_id FROM {$this->automations_table} WHERE automation_name = :automation_name AND automation_id != :automation_id",
            [':automation_name' => $name, ':automation_id' => $automation_id]
        );
        if ($existing_automation) {
            error_log('MassMailerAutomationManager: Another automation with this name already exists.');
            return false;
        }

        try {
            $sql = "UPDATE {$this->automations_table} SET automation_name = :name, trigger_type = :trigger_type, trigger_config = :trigger_config, action_type = :action_type, action_config = :action_config, status = :status WHERE automation_id = :automation_id";
            $stmt = $this->db->query($sql, [
                ':name' => $name,
                ':trigger_type' => $trigger_type,
                ':trigger_config' => json_encode($trigger_config),
                ':action_type' => $action_type,
                ':action_config' => json_encode($action_config),
                ':status' => $status,
                ':automation_id' => $automation_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerAutomationManager: Error updating automation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an automation rule.
     *
     * @param int $automation_id The ID of the automation to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteAutomation($automation_id) {
        if (empty($automation_id)) {
            error_log('MassMailerAutomationManager: Automation ID cannot be empty for delete.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->automations_table} WHERE automation_id = :automation_id";
            $stmt = $this->db->query($sql, [':automation_id' => $automation_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerAutomationManager: Error deleting automation: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes active automations based on recent events.
     * This function is intended to be called by a cron job.
     *
     * @param string $event_type The type of event to check for (e.g., 'subscriber_added', 'campaign_opened', 'campaign_clicked').
     * @param array $event_data Data related to the event (e.g., ['subscriber_id' => 1, 'list_id' => 2]).
     */
    public function processAutomations($event_type, $event_data) {
        error_log("MassMailerAutomationManager: Processing automations for event type: {$event_type}");

        $active_automations = $this->db->fetchAll(
            "SELECT * FROM {$this->automations_table} WHERE status = 'active' AND trigger_type = :event_type",
            [':event_type' => $event_type]
        );

        if (empty($active_automations)) {
            error_log("MassMailerAutomationManager: No active automations found for trigger type: {$event_type}.");
            return;
        }

        foreach ($active_automations as $automation) {
            $trigger_config = json_decode($automation['trigger_config'], true);
            $action_config = json_decode($automation['action_config'], true);

            // Check if the event data matches the automation's trigger config
            $trigger_matches = true;
            foreach ($trigger_config as $key => $value) {
                if (!isset($event_data[$key]) || $event_data[$key] != $value) {
                    $trigger_matches = false;
                    break;
                }
            }

            if ($trigger_matches) {
                error_log("MassMailerAutomationManager: Trigger matched for automation '{$automation['automation_name']}' (ID: {$automation['automation_id']}). Executing action.");
                $this->executeAutomationAction($automation['action_type'], $action_config, $event_data);
            }
        }
    }

    /**
     * Executes the action defined by an automation rule.
     *
     * @param string $action_type The type of action to perform.
     * @param array $action_config Configuration for the action.
     * @param array $event_data Data from the triggering event (e.g., subscriber_id).
     * @return bool True on success, false on failure.
     */
    private function executeAutomationAction($action_type, $action_config, $event_data) {
        $subscriber_id = $event_data['subscriber_id'] ?? null;
        if (!$subscriber_id) {
            error_log('MassMailerAutomationManager: Action execution failed, subscriber ID not provided in event data.');
            return false;
        }

        $subscriber = $this->subscriber_manager->getSubscriber($subscriber_id);
        if (!$subscriber) {
            error_log('MassMailerAutomationManager: Action execution failed, subscriber ' . $subscriber_id . ' not found.');
            return false;
        }

        switch ($action_type) {
            case 'send_email':
                $template_id = $action_config['template_id'] ?? null;
                $campaign_id = $action_config['campaign_id'] ?? null; // Use a dummy campaign ID for automation emails if needed
                $subject = $action_config['subject'] ?? 'Automated Email';

                if ($template_id && $campaign_id) {
                    // For automated emails, we might create a temporary campaign or use a specific "Automation" campaign
                    // For simplicity, we'll assume a campaign_id is provided in action_config or use a default.
                    // If no campaign_id is provided, we can log it as a generic automated email.
                    // Here, we'll use the provided campaign_id from action_config.
                    $this->mailer->sendTemplateEmailToSubscriber($campaign_id, $template_id, $subscriber_id, $subject);
                    error_log("MassMailerAutomationManager: Sent automated email to {$subscriber['email']} using template {$template_id}.");
                    return true;
                } else {
                    error_log('MassMailerAutomationManager: Send email action config missing template_id or campaign_id.');
                    return false;
                }
                break;

            case 'add_to_list':
                $target_list_id = $action_config['list_id'] ?? null;
                if ($target_list_id) {
                    $this->subscriber_manager->addSubscriberToList($subscriber_id, $target_list_id, 'active');
                    error_log("MassMailerAutomationManager: Added subscriber {$subscriber['email']} to list {$target_list_id}.");
                    return true;
                } else {
                    error_log('MassMailerAutomationManager: Add to list action config missing list_id.');
                    return false;
                }
                break;

            case 'remove_from_list':
                $target_list_id = $action_config['list_id'] ?? null;
                if ($target_list_id) {
                    $this->subscriber_manager->removeSubscriberFromList($subscriber_id, $target_list_id);
                    error_log("MassMailerAutomationManager: Removed subscriber {$subscriber['email']} from list {$target_list_id}.");
                    return true;
                } else {
                    error_log('MassMailerAutomationManager: Remove from list action config missing list_id.');
                    return false;
                }
                break;

            // Add more action types as needed (e.g., 'update_subscriber_status', 'webhook')
            default:
                error_log('MassMailerAutomationManager: Unknown automation action type: ' . $action_type);
                return false;
        }
    }
}
