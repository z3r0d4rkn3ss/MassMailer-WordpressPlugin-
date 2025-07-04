<?php
/**
 * Mass Mailer Segment Manager
 *
 * Manages the creation, retrieval, updating, and deletion of subscriber segments.
 * Also provides logic to retrieve subscribers belonging to a specific segment.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class and Subscriber Manager are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerSubscriberManager')) {
    require_once dirname(__FILE__) . '/subscriber-manager.php';
}
if (!class_exists('MassMailerListManager')) {
    require_once dirname(__FILE__) . '/list-manager.php';
}

class MassMailerSegmentManager {
    private $db;
    private $segments_table;
    private $subscriber_manager;
    private $list_manager;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->segments_table = MM_TABLE_PREFIX . 'segments'; // New table for segments
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->list_manager = new MassMailerListManager();
    }

    /**
     * Creates the mm_segments table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createSegmentsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->segments_table}` (
            `segment_id` INT AUTO_INCREMENT PRIMARY KEY,
            `segment_name` VARCHAR(255) NOT NULL UNIQUE,
            `segment_rules` JSON NOT NULL, -- JSON string defining the rules for this segment
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerSegmentManager: mm_segments table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error creating mm_segments table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new segment.
     *
     * @param string $name The name of the segment.
     * @param array $rules An associative array defining the segment rules.
     * @return int|false The ID of the newly created segment on success, false on failure.
     */
    public function createSegment($name, $rules) {
        if (empty($name) || !is_array($rules) || empty($rules)) {
            error_log('MassMailerSegmentManager: Segment name and rules cannot be empty.');
            return false;
        }

        // Check if segment with this name already exists
        $existing_segment = $this->db->fetch(
            "SELECT segment_id FROM {$this->segments_table} WHERE segment_name = :segment_name",
            [':segment_name' => $name]
        );
        if ($existing_segment) {
            error_log('MassMailerSegmentManager: Segment with this name already exists.');
            return false;
        }

        try {
            $sql = "INSERT INTO {$this->segments_table} (segment_name, segment_rules) VALUES (:segment_name, :segment_rules)";
            $this->db->query($sql, [
                ':segment_name' => $name,
                ':segment_rules' => json_encode($rules)
            ]);
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error creating segment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single segment by ID.
     *
     * @param int $segment_id The ID of the segment.
     * @return array|false The segment data on success, false if not found.
     */
    public function getSegment($segment_id) {
        if (empty($segment_id)) {
            return false;
        }
        $sql = "SELECT * FROM {$this->segments_table} WHERE segment_id = :segment_id";
        try {
            $segment = $this->db->fetch($sql, [':segment_id' => $segment_id]);
            if ($segment) {
                $segment['segment_rules'] = json_decode($segment['segment_rules'], true);
            }
            return $segment;
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error getting segment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all segments.
     *
     * @return array An array of all segment data, or an empty array.
     */
    public function getAllSegments() {
        $sql = "SELECT * FROM {$this->segments_table} ORDER BY segment_name ASC";
        try {
            $segments = $this->db->fetchAll($sql);
            foreach ($segments as &$segment) {
                $segment['segment_rules'] = json_decode($segment['segment_rules'], true);
            }
            return $segments;
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error getting all segments: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update an existing segment.
     *
     * @param int $segment_id The ID of the segment to update.
     * @param string $name The new name of the segment.
     * @param array $rules The new rules for the segment.
     * @return bool True on success, false on failure.
     */
    public function updateSegment($segment_id, $name, $rules) {
        if (empty($segment_id) || empty($name) || !is_array($rules) || empty($rules)) {
            error_log('MassMailerSegmentManager: Missing required fields for segment update.');
            return false;
        }

        // Check if segment name already exists for a different ID
        $existing_segment = $this->db->fetch(
            "SELECT segment_id FROM {$this->segments_table} WHERE segment_name = :segment_name AND segment_id != :segment_id",
            [':segment_name' => $name, ':segment_id' => $segment_id]
        );
        if ($existing_segment) {
            error_log('MassMailerSegmentManager: Another segment with this name already exists.');
            return false;
        }

        try {
            $sql = "UPDATE {$this->segments_table} SET segment_name = :segment_name, segment_rules = :segment_rules WHERE segment_id = :segment_id";
            $stmt = $this->db->query($sql, [
                ':segment_name' => $name,
                ':segment_rules' => json_encode($rules),
                ':segment_id' => $segment_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error updating segment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a segment.
     *
     * @param int $segment_id The ID of the segment to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteSegment($segment_id) {
        if (empty($segment_id)) {
            error_log('MassMailerSegmentManager: Segment ID cannot be empty for delete.');
            return false;
        }
        try {
            $sql = "DELETE FROM {$this->segments_table} WHERE segment_id = :segment_id";
            $stmt = $this->db->query($sql, [':segment_id' => $segment_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error deleting segment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all subscribers that match a given segment's rules.
     * This is the core segmentation logic.
     *
     * @param int $segment_id The ID of the segment.
     * @return array An array of subscriber data (including subscriber_id, email, etc.).
     */
    public function getSubscribersInSegment($segment_id) {
        $segment = $this->getSegment($segment_id);
        if (!$segment) {
            error_log('MassMailerSegmentManager: Segment ' . $segment_id . ' not found.');
            return [];
        }

        $rules = $segment['segment_rules'];
        $where_clauses = [];
        $params = [];
        $join_list_rel = false;

        // Start with a base query for all subscribers
        $sql = "SELECT s.* FROM " . MM_TABLE_PREFIX . "subscribers s";

        foreach ($rules as $rule) {
            $field = $rule['field'] ?? null;
            $operator = $rule['operator'] ?? null;
            $value = $rule['value'] ?? null;

            if (!$field || !$operator || $value === null) {
                error_log('MassMailerSegmentManager: Invalid rule format encountered.');
                continue;
            }

            switch ($field) {
                case 'list_id':
                    $join_list_rel = true;
                    if ($operator === 'IN' && is_array($value)) {
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $where_clauses[] = "lsr.list_id IN ({$placeholders})";
                        $params = array_merge($params, $value);
                    } elseif ($operator === '=' && is_numeric($value)) {
                        $where_clauses[] = "lsr.list_id = ?";
                        $params[] = $value;
                    }
                    break;
                case 'status':
                    if ($operator === '=' || $operator === '!=') {
                        $where_clauses[] = "s.status {$operator} ?";
                        $params[] = $value;
                    } elseif ($operator === 'IN' && is_array($value)) {
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $where_clauses[] = "s.status IN ({$placeholders})";
                        $params = array_merge($params, $value);
                    }
                    break;
                case 'subscribed_at':
                    // Example for date range: {"field": "subscribed_at", "operator": "BETWEEN", "value": ["2023-01-01", "2023-12-31"]}
                    if ($operator === 'BETWEEN' && is_array($value) && count($value) === 2) {
                        $where_clauses[] = "s.subscribed_at BETWEEN ? AND ?";
                        $params[] = $value[0];
                        $params[] = $value[1];
                    } elseif ($operator === '>' || $operator === '<' || $operator === '>=' || $operator === '<=') {
                        $where_clauses[] = "s.subscribed_at {$operator} ?";
                        $params[] = $value;
                    }
                    break;
                case 'first_name':
                case 'last_name':
                case 'email':
                    if ($operator === 'LIKE' || $operator === 'NOT LIKE') {
                        $where_clauses[] = "s.{$field} {$operator} ?";
                        $params[] = '%' . $value . '%';
                    } elseif ($operator === '=' || $operator === '!=') {
                        $where_clauses[] = "s.{$field} {$operator} ?";
                        $params[] = $value;
                    }
                    break;
                // Add more fields and operators as needed
                default:
                    error_log('MassMailerSegmentManager: Unhandled segment field or operator: ' . $field . ' ' . $operator);
                    break;
            }
        }

        if ($join_list_rel) {
            $sql .= " JOIN " . MM_TABLE_PREFIX . "list_subscriber_rel lsr ON s.subscriber_id = lsr.subscriber_id";
        }

        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(' AND ', $where_clauses);
        }

        $sql .= " GROUP BY s.subscriber_id"; // Ensure unique subscribers if joining list_rel

        try {
            return $this->db->fetchAll($sql, $params);
        } catch (PDOException $e) {
            error_log('MassMailerSegmentManager: Error getting subscribers in segment: ' . $e->getMessage() . ' SQL: ' . $sql . ' Params: ' . json_encode($params));
            return [];
        }
    }
}
