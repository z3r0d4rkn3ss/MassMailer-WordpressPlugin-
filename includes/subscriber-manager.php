<?php
/**
 * Mass Mailer Subscriber Manager - GDPR/Privacy Features Update
 *
 * Adds functionality for double opt-in, data export, and data erasure.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class is loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
// Ensure Mailer is loaded for sending verification emails
if (!class_exists('MassMailerMailer')) {
    require_once dirname(__FILE__) . '/mailer.php';
}
// Ensure SettingsManager is loaded for tracking base URL
if (!class_exists('MassMailerSettingsManager')) {
    require_once dirname(__FILE__) . '/settings-manager.php';
}


class MassMailerSubscriberManager {
    private $db;
    private $subscribers_table;
    private $list_subscriber_rel_table;
    private $mailer;
    private $settings_manager;

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->subscribers_table = MM_TABLE_PREFIX . 'subscribers';
        $this->list_subscriber_rel_table = MM_TABLE_PREFIX . 'list_subscriber_rel';
        $this->mailer = new MassMailerMailer();
        $this->settings_manager = new MassMailerSettingsManager();
    }

    /**
     * Adds or updates a subscriber.
     * Modified for double opt-in: new subscribers are 'pending' and get a verification token.
     *
     * @param string $email The subscriber's email.
     * @param string|null $first_name The subscriber's first name.
     * @param string|null $last_name The subscriber's last name.
     * @param string $status The subscriber's status ('subscribed', 'unsubscribed', 'pending', 'bounced').
     * @return int|false The subscriber_id on success, false on failure.
     */
    public function addOrUpdateSubscriber($email, $first_name = null, $last_name = null, $status = 'pending') {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('MassMailerSubscriberManager: Invalid email format for addOrUpdateSubscriber: ' . $email);
            return false;
        }

        $existing_subscriber = $this->getSubscriber($email);

        try {
            if ($existing_subscriber) {
                // Update existing subscriber
                $sql = "UPDATE {$this->subscribers_table} SET first_name = :first_name, last_name = :last_name, status = :status, updated_at = CURRENT_TIMESTAMP WHERE subscriber_id = :subscriber_id";
                $params = [
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':status' => $status,
                    ':subscriber_id' => $existing_subscriber['subscriber_id']
                ];
                $this->db->query($sql, $params);
                return $existing_subscriber['subscriber_id'];
            } else {
                // Add new subscriber
                $verification_token = null;
                $opt_in_date = null;

                // If status is 'pending', generate a token and send verification email
                if ($status === 'pending') {
                    $verification_token = $this->generateVerificationToken();
                    // opt_in_date is set upon successful verification, not here.
                } else {
                    // If status is not pending (e.g., directly subscribed via admin), set opt_in_date
                    $opt_in_date = date('Y-m-d H:i:s');
                }

                $sql = "INSERT INTO {$this->subscribers_table} (email, first_name, last_name, status, verification_token, opt_in_date) VALUES (:email, :first_name, :last_name, :status, :verification_token, :opt_in_date)";
                $params = [
                    ':email' => $email,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':status' => $status,
                    ':verification_token' => $verification_token,
                    ':opt_in_date' => $opt_in_date
                ];
                $this->db->query($sql, $params);
                $subscriber_id = $this->db->lastInsertId();

                if ($subscriber_id && $status === 'pending' && $verification_token) {
                    $this->sendVerificationEmail($email, $verification_token);
                }

                return $subscriber_id;
            }
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error adding/updating subscriber: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generates a unique verification token.
     *
     * @return string The generated token.
     */
    private function generateVerificationToken() {
        return bin2hex(random_bytes(32)); // 64-character hex string
    }

    /**
     * Sends a double opt-in verification email.
     *
     * @param string $email The recipient's email.
     * @param string $token The verification token.
     * @return bool True on success, false on failure.
     */
    public function sendVerificationEmail($email, $token) {
        $tracking_base_url = $this->settings_manager->getSetting('tracking_base_url');
        if (empty($tracking_base_url)) {
            error_log('MassMailerSubscriberManager: Tracking base URL not set, cannot send verification email.');
            return false;
        }

        $verification_link = rtrim($tracking_base_url, '/') . '/verify.php?token=' . urlencode($token);

        $subject = "Please confirm your subscription to our newsletter";
        $body_html = "
            <p>Hi,</p>
            <p>Thank you for subscribing to our newsletter! To complete your subscription and start receiving our emails, please click the link below to confirm your email address:</p>
            <p><a href=\"{$verification_link}\">Confirm My Subscription</a></p>
            <p>If you did not subscribe to this newsletter, please ignore this email.</p>
            <p>Thanks,<br>The Team</p>
        ";
        $body_text = "
            Hi,

            Thank you for subscribing to our newsletter! To complete your subscription and start receiving our emails, please click the link below to confirm your email address:

            {$verification_link}

            If you did not subscribe to this newsletter, please ignore this email.

            Thanks,
            The Team
        ";

        $from_name = $this->settings_manager->getSetting('default_from_name', 'Mass Mailer');
        $from_email = $this->settings_manager->getSetting('default_from_email', 'noreply@example.com');
        $reply_to_email = $this->settings_manager->getSetting('reply_to_email', $from_email);

        return $this->mailer->sendEmail($from_email, $from_name, $email, $subject, $body_html, $body_text, $reply_to_email);
    }

    /**
     * Verifies a subscriber's email using a token and updates status to 'subscribed'.
     *
     * @param string $token The verification token.
     * @return bool True on successful verification, false otherwise.
     */
    public function verifySubscriber($token) {
        if (empty($token)) {
            return false;
        }

        try {
            $sql = "SELECT subscriber_id, status FROM {$this->subscribers_table} WHERE verification_token = :token AND status = 'pending'";
            $subscriber = $this->db->fetch($sql, [':token' => $token]);

            if ($subscriber) {
                // Update status to 'subscribed', clear token, and set opt_in_date
                $update_sql = "UPDATE {$this->subscribers_table} SET status = 'subscribed', verification_token = NULL, opt_in_date = CURRENT_TIMESTAMP WHERE subscriber_id = :subscriber_id";
                $stmt = $this->db->query($update_sql, [':subscriber_id' => $subscriber['subscriber_id']]);

                if ($stmt->rowCount() > 0) {
                    error_log('MassMailerSubscriberManager: Subscriber ' . $subscriber['subscriber_id'] . ' successfully verified.');
                    return true;
                }
            }
            error_log('MassMailerSubscriberManager: Verification failed for token: ' . $token);
            return false;
        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error verifying subscriber: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Exports all data associated with a specific subscriber.
     * Includes subscriber details, list memberships, opens, clicks, and bounce logs.
     *
     * @param string $email The email of the subscriber to export.
     * @return array|false An associative array of all subscriber data, or false if not found.
     */
    public function exportSubscriberData($email) {
        $subscriber = $this->getSubscriber($email);
        if (!$subscriber) {
            return false;
        }

        $subscriber_id = $subscriber['subscriber_id'];
        $exported_data = [
            'subscriber_details' => $subscriber,
            'list_memberships' => [],
            'campaign_opens' => [],
            'campaign_clicks' => [],
            'bounce_logs' => [],
        ];

        try {
            // Fetch list memberships
            $sql_lists = "SELECT l.list_name, lsr.status, lsr.joined_at FROM " . MM_TABLE_PREFIX . "list_subscriber_rel lsr JOIN " . MM_TABLE_PREFIX . "lists l ON lsr.list_id = l.list_id WHERE lsr.subscriber_id = :subscriber_id";
            $exported_data['list_memberships'] = $this->db->fetchAll($sql_lists, [':subscriber_id' => $subscriber_id]);

            // Fetch campaign opens
            $sql_opens = "SELECT c.campaign_name, o.opened_at, o.ip_address, o.user_agent FROM " . MM_TABLE_PREFIX . "opens o JOIN " . MM_TABLE_PREFIX . "campaigns c ON o.campaign_id = c.campaign_id WHERE o.subscriber_id = :subscriber_id ORDER BY o.opened_at DESC";
            $exported_data['campaign_opens'] = $this->db->fetchAll($sql_opens, [':subscriber_id' => $subscriber_id]);

            // Fetch campaign clicks
            $sql_clicks = "SELECT c.campaign_name, cl.original_url, cl.clicked_at, cl.ip_address, cl.user_agent FROM " . MM_TABLE_PREFIX . "clicks cl JOIN " . MM_TABLE_PREFIX . "campaigns c ON cl.campaign_id = c.campaign_id WHERE cl.subscriber_id = :subscriber_id ORDER BY cl.clicked_at DESC";
            $exported_data['campaign_clicks'] = $this->db->fetchAll($sql_clicks, [':subscriber_id' => $subscriber_id]);

            // Fetch bounce logs
            $sql_bounces = "SELECT bounce_type, reason, processed_at, raw_email_content FROM " . MM_TABLE_PREFIX . "bounces_log WHERE subscriber_id = :subscriber_id ORDER BY processed_at DESC";
            $exported_data['bounce_logs'] = $this->db->fetchAll($sql_bounces, [':subscriber_id' => $subscriber_id]);

            return $exported_data;

        } catch (PDOException $e) {
            error_log('MassMailerSubscriberManager: Error exporting subscriber data: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes all data associated with a specific subscriber (Right to be Forgotten).
     * This will delete entries from subscribers, list_subscriber_rel, opens, clicks, and bounce_log.
     *
     * @param string $email The email of the subscriber to delete.
     * @return bool True on successful deletion, false otherwise.
     */
    public function deleteSubscriberData($email) {
        $subscriber = $this->getSubscriber($email);
        if (!$subscriber) {
            error_log('MassMailerSubscriberManager: Subscriber not found for deletion: ' . $email);
            return false;
        }

        $subscriber_id = $subscriber['subscriber_id'];

        try {
            $this->db->beginTransaction();

            // Delete from list_subscriber_rel
            $sql_del_rel = "DELETE FROM " . MM_TABLE_PREFIX . "list_subscriber_rel WHERE subscriber_id = :subscriber_id";
            $this->db->query($sql_del_rel, [':subscriber_id' => $subscriber_id]);

            // Delete from opens
            $sql_del_opens = "DELETE FROM " . MM_TABLE_PREFIX . "opens WHERE subscriber_id = :subscriber_id";
            $this->db->query($sql_del_opens, [':subscriber_id' => $subscriber_id]);

            // Delete from clicks
            $sql_del_clicks = "DELETE FROM " . MM_TABLE_PREFIX . "clicks WHERE subscriber_id = :subscriber_id";
            $this->db->query($sql_del_clicks, [':subscriber_id' => $subscriber_id]);

            // Delete from bounces_log (subscriber_id is NULLed by FK, but we can delete the record if desired)
            // Or just let the ON DELETE SET NULL handle it if you want to keep the log of the bounce event itself.
            // For "right to be forgotten", full deletion is usually preferred.
            $sql_del_bounces = "DELETE FROM " . MM_TABLE_PREFIX . "bounces_log WHERE subscriber_id = :subscriber_id";
            $this->db->query($sql_del_bounces, [':subscriber_id' => $subscriber_id]);


            // Finally, delete from subscribers table
            $sql_del_subscriber = "DELETE FROM {$this->subscribers_table} WHERE subscriber_id = :subscriber_id";
            $stmt = $this->db->query($sql_del_subscriber, [':subscriber_id' => $subscriber_id]);

            $this->db->commit();

            error_log('MassMailerSubscriberManager: All data for subscriber ' . $email . ' (ID: ' . $subscriber_id . ') deleted.');
            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log('MassMailerSubscriberManager: Error deleting subscriber data for ' . $email . ': ' . $e->getMessage());
            return false;
        }
    }

    // getSubscriber, getSubscribersByList, removeSubscriberFromList, getSubscriberStatusDistribution remain the same.
}
