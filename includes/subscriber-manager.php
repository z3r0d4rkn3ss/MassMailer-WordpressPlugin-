<?php
/**
 * Mass Mailer Subscriber Manager
 *
 * Provides functions for managing subscribers (CRUD operations)
 * and their relationships with mailing lists.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}

class MassMailerSubscriberManager {
    private $db;
    private $subscribers_table;
    private $list_subscriber_rel_table;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->subscribers_table = MM_TABLE_PREFIX . 'subscribers';
        $this->list_subscriber_rel_table = MM_TABLE_PREFIX . 'list_subscriber_rel';
    }

    /**
     * Add a new subscriber or update an existing one.
     *
     * @param string $email The subscriber's email address.
     * @param string $first_name The subscriber's first name (optional).
     * @param string $last_name The subscriber's last name (optional).
     * @param string $status The subscriber's status (e.g., 'pending', 'subscribed').
     * @return int|false The subscriber ID on success, false on failure.
     */
    public function addOrUpdateSubscriber($email, $first_name = null, $last_name = null, $status = 'pending') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('MassMailerSubscriberManager: Invalid email format.');
            return false;
        }

        // Check if subscriber already exists
        $existing_subscriber = $this->db->fetch(
            "SELECT subscriber_id FROM {$this->subscribers_table} WHERE email = :email",
            [':email' => $email]
        );

        try {
            if ($existing_subscriber) {
                // Update existing subscriber
                $subscriber_id = $existing_subscriber['subscriber_id'];
                $sql = "UPDATE {$this->subscribers_table} SET first_name = :first_name, last_name = :last_name, status = :status WHERE subscriber_id = :subscriber_id";
                $this->db->query($sql, [
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':status' => $status,
                    ':subscriber_id' => $subscriber_id
                ]);
                return $subscriber_id;
            } else {
                // Add new subscriber
                $sql = "INSERT INTO {$this->subscribers_table} (email, first_name, last_name, status) VALUES (:email, :first_name, :last_name, :status)";
                $this->db->query($sql, [
                    ':email' => $email,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':status' => $status
                ]);
                return $this->db->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error adding/updating subscriber: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a subscriber by ID or email.
     *
     * @param int|string $identifier The subscriber ID or email address.
     * @return array|false The subscriber data on success, false if not found.
     */
    public function getSubscriber($identifier) {
        $field = is_numeric($identifier) ? 'subscriber_id' : 'email';
        $sql = "SELECT * FROM {$this->subscribers_table} WHERE {$field} = :identifier";
        try {
            return $this->db->fetch($sql, [':identifier' => $identifier]);
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error getting subscriber: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all subscribers, optionally filtered by list.
     *
     * @param int|null $list_id Optional. The ID of the list to filter by.
     * @return array An array of subscriber data.
     */
    public function getAllSubscribers($list_id = null) {
        if ($list_id) {
            $sql = "SELECT s.*, r.status AS list_status FROM {$this->subscribers_table} s
                    JOIN {$this->list_subscriber_rel_table} r ON s.subscriber_id = r.subscriber_id
                    WHERE r.list_id = :list_id ORDER BY s.email ASC";
            return $this->db->fetchAll($sql, [':list_id' => $list_id]);
        } else {
            $sql = "SELECT * FROM {$this->subscribers_table} ORDER BY email ASC";
            return $this->db->fetchAll($sql);
        }
    }

    /**
     * Delete a subscriber.
     * This will also remove them from all lists due to CASCADE DELETE.
     *
     * @param int $subscriber_id The ID of the subscriber to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteSubscriber($subscriber_id) {
        if (empty($subscriber_id)) {
            error_log('MassMailerSubscriberManager: Subscriber ID cannot be empty for delete.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->subscribers_table} WHERE subscriber_id = :subscriber_id";
            $stmt = $this->db->query($sql, [':subscriber_id' => $subscriber_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error deleting subscriber: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a subscriber to a specific list.
     *
     * @param int $subscriber_id The ID of the subscriber.
     * @param int $list_id The ID of the list.
     * @param string $status Status within this specific list (e.g., 'active', 'inactive').
     * @return bool True on success, false if already in list or on failure.
     */
    public function addSubscriberToList($subscriber_id, $list_id, $status = 'active') {
        if (empty($subscriber_id) || empty($list_id)) {
            error_log('MassMailerSubscriberManager: Subscriber ID and List ID cannot be empty.');
            return false;
        }

        // Check if already in list
        $existing_rel = $this->db->fetch(
            "SELECT * FROM {$this->list_subscriber_rel_table} WHERE list_id = :list_id AND subscriber_id = :subscriber_id",
            [':list_id' => $list_id, ':subscriber_id' => $subscriber_id]
        );

        if ($existing_rel) {
            // Already in list, perhaps update status
            $sql = "UPDATE {$this->list_subscriber_rel_table} SET status = :status WHERE list_id = :list_id AND subscriber_id = :subscriber_id";
            $stmt = $this->db->query($sql, [
                ':status' => $status,
                ':list_id' => $list_id,
                ':subscriber_id' => $subscriber_id
            ]);
            return $stmt->rowCount() > 0; // Return true if updated
        }

        try {
            $sql = "INSERT INTO {$this->list_subscriber_rel_table} (list_id, subscriber_id, status) VALUES (:list_id, :subscriber_id, :status)";
            $this->db->query($sql, [
                ':list_id' => $list_id,
                ':subscriber_id' => $subscriber_id,
                ':status' => $status
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error adding subscriber to list: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a subscriber from a specific list.
     *
     * @param int $subscriber_id The ID of the subscriber.
     * @param int $list_id The ID of the list.
     * @return bool True on success, false on failure.
     */
    public function removeSubscriberFromList($subscriber_id, $list_id) {
        if (empty($subscriber_id) || empty($list_id)) {
            error_log('MassMailerSubscriberManager: Subscriber ID and List ID cannot be empty for removal.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->list_subscriber_rel_table} WHERE list_id = :list_id AND subscriber_id = :subscriber_id";
            $stmt = $this->db->query($sql, [
                ':list_id' => $list_id,
                ':subscriber_id' => $subscriber_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error removing subscriber from list: ' . $e->getMessage());
            return false;
        }
    }
}
