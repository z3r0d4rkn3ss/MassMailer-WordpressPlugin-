<?php
/**
 * Mass Mailer API Manager
 *
 * Manages the creation, validation, and retrieval of API keys for external integration.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class and Auth class are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerAuth')) {
    require_once dirname(__FILE__) . '/auth.php';
}

class MassMailerAPIManager {
    private $db;
    private $api_keys_table;
    private $auth;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->api_keys_table = MM_TABLE_PREFIX . 'api_keys'; // New table for API keys
        $this->auth = new MassMailerAuth();
    }

    /**
     * Creates the mm_api_keys table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createApiKeysTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->api_keys_table}` (
            `api_key_id` INT AUTO_INCREMENT PRIMARY KEY,
            `api_key` VARCHAR(64) NOT NULL UNIQUE, -- SHA256 hash length
            `user_id` INT NULL, -- Link to the user who created/owns the key
            `description` VARCHAR(255) NULL, -- e.g., "CRM Integration", "Website Form"
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES " . MM_TABLE_PREFIX . "users(`user_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerAPIManager: mm_api_keys table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error creating mm_api_keys table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generates a cryptographically secure random API key.
     *
     * @return string The generated API key.
     */
    private function generateUniqueApiKey() {
        return bin2hex(random_bytes(32)); // Generates a 64-character hex string
    }

    /**
     * Creates a new API key for a user.
     *
     * @param int $user_id The ID of the user creating the key.
     * @param string $description A description for the API key.
     * @return string|false The new API key string on success, false on failure.
     */
    public function createApiKey($user_id, $description = '') {
        if (empty($user_id)) {
            error_log('MassMailerAPIManager: User ID is required to create an API key.');
            return false;
        }

        $api_key = $this->generateUniqueApiKey();
        try {
            $sql = "INSERT INTO {$this->api_keys_table} (api_key, user_id, description) VALUES (:api_key, :user_id, :description)";
            $this->db->query($sql, [
                ':api_key' => $api_key,
                ':user_id' => $user_id,
                ':description' => $description
            ]);
            return $api_key;
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error creating API key: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a single API key by its ID.
     *
     * @param int $api_key_id The ID of the API key.
     * @return array|false API key data on success, false if not found.
     */
    public function getApiKey($api_key_id) {
        if (empty($api_key_id)) {
            return false;
        }
        $sql = "SELECT ak.*, u.username FROM {$this->api_keys_table} ak LEFT JOIN " . MM_TABLE_PREFIX . "users u ON ak.user_id = u.user_id WHERE ak.api_key_id = :api_key_id";
        try {
            return $this->db->fetch($sql, [':api_key_id' => $api_key_id]);
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error getting API key by ID: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all API keys.
     *
     * @return array An array of all API key data, or an empty array.
     */
    public function getAllApiKeys() {
        $sql = "SELECT ak.*, u.username FROM {$this->api_keys_table} ak LEFT JOIN " . MM_TABLE_PREFIX . "users u ON ak.user_id = u.user_id ORDER BY ak.created_at DESC";
        try {
            return $this->db->fetchAll($sql);
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error getting all API keys: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates an existing API key's description or status.
     *
     * @param int $api_key_id The ID of the API key to update.
     * @param string $description The new description.
     * @param string $status The new status ('active' or 'inactive').
     * @return bool True on success, false on failure.
     */
    public function updateApiKey($api_key_id, $description, $status) {
        if (empty($api_key_id) || empty($description) || !in_array($status, ['active', 'inactive'])) {
            error_log('MassMailerAPIManager: Missing or invalid parameters for API key update.');
            return false;
        }
        try {
            $sql = "UPDATE {$this->api_keys_table} SET description = :description, status = :status WHERE api_key_id = :api_key_id";
            $stmt = $this->db->query($sql, [
                ':description' => $description,
                ':status' => $status,
                ':api_key_id' => $api_key_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error updating API key: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an API key.
     *
     * @param int $api_key_id The ID of the API key to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteApiKey($api_key_id) {
        if (empty($api_key_id)) {
            error_log('MassMailerAPIManager: API Key ID cannot be empty for delete.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->api_keys_table} WHERE api_key_id = :api_key_id";
            $stmt = $this->db->query($sql, [':api_key_id' => $api_key_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error deleting API key: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validates an API key and updates its last_used_at timestamp.
     *
     * @param string $api_key The API key string to validate.
     * @return array|false API key data if valid and active, false otherwise.
     */
    public function validateApiKey($api_key) {
        if (empty($api_key)) {
            return false;
        }
        $sql = "SELECT * FROM {$this->api_keys_table} WHERE api_key = :api_key AND status = 'active'";
        try {
            $key_data = $this->db->fetch($sql, [':api_key' => $api_key]);
            if ($key_data) {
                // Update last_used_at
                $update_sql = "UPDATE {$this->api_keys_table} SET last_used_at = CURRENT_TIMESTAMP WHERE api_key_id = :api_key_id";
                $this->db->query($update_sql, [':api_key_id' => $key_data['api_key_id']]);
                return $key_data;
            }
            return false;
        } catch (PDOException $e) {
            error_log('MassMailerAPIManager: Error validating API key: ' . $e->getMessage());
            return false;
        }
    }
}
