<?php
/**
 * Mass Mailer Settings Manager
 *
 * Manages the storage and retrieval of global application settings.
 * Settings are stored in the database.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}

class MassMailerSettingsManager {
    private $db;
    private $settings_table;
    private $settings_cache = [];

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->settings_table = MM_TABLE_PREFIX . 'settings'; // New table for settings
        $this->loadSettingsIntoCache();
    }

    /**
     * Creates the mm_settings table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createSettingsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->settings_table}` (
            `setting_id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerSettingsManager: mm_settings table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerSettingsManager: Error creating mm_settings table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Loads all settings from the database into a local cache.
     */
    private function loadSettingsIntoCache() {
        try {
            $settings = $this->db->fetchAll("SELECT setting_key, setting_value FROM {$this->settings_table}");
            foreach ($settings as $setting) {
                $this->settings_cache[$setting['setting_key']] = $setting['setting_value'];
            }
        } catch (PDOException $e) {
            error_log('MassMailerSettingsManager: Error loading settings into cache: ' . $e->getMessage());
            // This might happen if the table doesn't exist yet, which is fine during initial setup.
        }
    }

    /**
     * Get a single setting value by its key.
     *
     * @param string $key The setting key.
     * @param mixed $default The default value to return if the setting is not found.
     * @return mixed The setting value, or the default value.
     */
    public function getSetting($key, $default = null) {
        return $this->settings_cache[$key] ?? $default;
    }

    /**
     * Set (add or update) a setting value.
     *
     * @param string $key The setting key.
     * @param mixed $value The setting value.
     * @return bool True on success, false on failure.
     */
    public function setSetting($key, $value) {
        if (empty($key)) {
            error_log('MassMailerSettingsManager: Setting key cannot be empty.');
            return false;
        }

        try {
            // Check if setting exists
            $existing_setting = $this->db->fetch(
                "SELECT setting_id FROM {$this->settings_table} WHERE setting_key = :setting_key",
                [':setting_key' => $key]
            );

            if ($existing_setting) {
                // Update existing setting
                $sql = "UPDATE {$this->settings_table} SET setting_value = :setting_value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :setting_key";
                $stmt = $this->db->query($sql, [
                    ':setting_value' => $value,
                    ':setting_key' => $key
                ]);
            } else {
                // Insert new setting
                $sql = "INSERT INTO {$this->settings_table} (setting_key, setting_value) VALUES (:setting_key, :setting_value)";
                $stmt = $this->db->query($sql, [
                    ':setting_key' => $key,
                    ':setting_value' => $value
                ]);
            }

            // Update cache
            $this->settings_cache[$key] = $value;
            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            error_log('MassMailerSettingsManager: Error setting setting ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings as an associative array.
     *
     * @return array All settings.
     */
    public function getAllSettings() {
        return $this->settings_cache;
    }
}
