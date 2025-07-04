<?php
/**
 * Mass Mailer List Manager
 *
 * Provides functions for managing mailing lists (CRUD operations).
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}

class MassMailerListManager {
    private $db;
    private $table_name;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->table_name = MM_TABLE_PREFIX . 'lists';
    }

    /**
     * Create a new mailing list.
     *
     * @param string $name The name of the list.
     * @param string $description The description of the list (optional).
     * @return int|false The ID of the newly created list on success, false on failure.
     */
    public function createList($name, $description = '') {
        if (empty($name)) {
            error_log('MassMailerListManager: List name cannot be empty.');
            return false;
        }

        // Check if list with this name already exists
        $existing_list = $this->db->fetch(
            "SELECT list_id FROM {$this->table_name} WHERE list_name = :list_name",
            [':list_name' => $name]
        );
        if ($existing_list) {
            error_log('MassMailerListManager: List with this name already exists.');
            return false;
        }

        try {
            $sql = "INSERT INTO {$this->table_name} (list_name, list_description) VALUES (:list_name, :list_description)";
            $this->db->query($sql, [
                ':list_name' => $name,
                ':list_description' => $description
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerListManager: Error creating list: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single mailing list by ID or name.
     *
     * @param int|string $identifier The list ID or list name.
     * @return array|false The list data on success, false if not found.
     */
    public function getList($identifier) {
        $field = is_numeric($identifier) ? 'list_id' : 'list_name';
        $sql = "SELECT * FROM {$this->table_name} WHERE {$field} = :identifier";
        try {
            return $this->db->fetch($sql, [':identifier' => $identifier]);
        } catch (PDOException $e) {
            error_log('MassMailerListManager: Error getting list: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all mailing lists.
     *
     * @return array An array of all list data, or an empty array.
     */
    public function getAllLists() {
        $sql = "SELECT * FROM {$this->table_name} ORDER BY list_name ASC";
        try {
            return $this->db->fetchAll($sql);
        } catch (PDOException $e) {
            error_log('MassMailerListManager: Error getting all lists: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update an existing mailing list.
     *
     * @param int $list_id The ID of the list to update.
     * @param string $name The new name of the list.
     * @param string $description The new description of the list.
     * @return bool True on success, false on failure.
     */
    public function updateList($list_id, $name, $description = '') {
        if (empty($list_id) || empty($name)) {
            error_log('MassMailerListManager: List ID and name cannot be empty for update.');
            return false;
        }

        // Check if list with this name already exists for a different ID
        $existing_list = $this->db->fetch(
            "SELECT list_id FROM {$this->table_name} WHERE list_name = :list_name AND list_id != :list_id",
            [':list_name' => $name, ':list_id' => $list_id]
        );
        if ($existing_list) {
            error_log('MassMailerListManager: Another list with this name already exists.');
            return false;
        }

        try {
            $sql = "UPDATE {$this->table_name} SET list_name = :list_name, list_description = :list_description WHERE list_id = :list_id";
            $stmt = $this->db->query($sql, [
                ':list_name' => $name,
                ':list_description' => $description,
                ':list_id' => $list_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerListManager: Error updating list: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a mailing list.
     *
     * @param int $list_id The ID of the list to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteList($list_id) {
        if (empty($list_id)) {
            error_log('MassMailerListManager: List ID cannot be empty for delete.');
            return false;
        }
        try {
            // Deleting a list will automatically delete associated entries in mm_list_subscriber_rel due to ON DELETE CASCADE
            $sql = "DELETE FROM {$this->table_name} WHERE list_id = :list_id";
            $stmt = $this->db->query($sql, [':list_id' => $list_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerListManager: Error deleting list: ' . $e->getMessage());
            return false;
        }
    }
}
