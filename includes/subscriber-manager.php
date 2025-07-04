<?php
/**
 * Mass Mailer Subscriber Manager - Reporting Updates
 *
 * Adds a method to retrieve the distribution of subscriber statuses.
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

    // All existing methods (addOrUpdateSubscriber, getSubscriber, getSubscribersByList, removeSubscriberFromList, etc.) remain the same.

    /**
     * Retrieves the count of subscribers for each status.
     *
     * @return array An associative array with status counts and total subscribers.
     */
    public function getSubscriberStatusDistribution() {
        $distribution = [
            'total_subscribers' => 0,
            'subscribed' => 0,
            'unsubscribed' => 0,
            'pending' => 0,
            'bounced' => 0,
            // Add other statuses if they exist in your ENUM
        ];

        try {
            $sql = "SELECT status, COUNT(subscriber_id) AS count FROM {$this->subscribers_table} GROUP BY status";
            $results = $this->db->fetchAll($sql);

            foreach ($results as $row) {
                $status = $row['status'];
                $count = $row['count'];
                if (isset($distribution[$status])) {
                    $distribution[$status] = $count;
                }
                $distribution['total_subscribers'] += $count;
            }
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error getting subscriber status distribution: ' . $e->getMessage());
        }
        return $distribution;
    }
}
