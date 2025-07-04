<?php
/**
 * Mass Mailer Bounce and Complaint Handler
 *
 * This class is responsible for processing bounce and complaint notifications
 * (e.g., via IMAP polling or webhooks) and updating subscriber statuses accordingly.
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
if (!class_exists('MassMailerSettingsManager')) {
    require_once dirname(__FILE__) . '/settings-manager.php';
}

class MassMailerBounceHandler {
    private $db;
    private $subscriber_manager;
    private $settings_manager;
    private $bounces_log_table; // New table for bounce logs

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->settings_manager = new MassMailerSettingsManager();
        $this->bounces_log_table = MM_TABLE_PREFIX . 'bounces_log'; // New table for bounce logs
    }

    /**
     * Creates the mm_bounces_log table if it doesn't exist.
     * This would typically be called during plugin activation.
     */
    public function createBouncesLogTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->bounces_log_table}` (
            `log_id` INT AUTO_INCREMENT PRIMARY KEY,
            `subscriber_id` INT NULL, -- Can be NULL if email not found in subscribers
            `email` VARCHAR(255) NOT NULL,
            `bounce_type` ENUM('soft', 'hard', 'complaint') NOT NULL,
            `reason` TEXT NULL,
            `processed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `raw_email_content` LONGTEXT NULL, -- Store the full bounced email for debugging
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        try {
            $this->db->query($sql);
            error_log('MassMailerBounceHandler: mm_bounces_log table created/checked successfully.');
            return true;
        } catch (PDOException $e) {
            error_log('MassMailerBounceHandler: Error creating mm_bounces_log table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Processes a raw email content to detect bounces or complaints.
     * This is a conceptual method. Real-world implementation would use a dedicated
     * bounce parsing library (e.g., PHP-Bounce-Handler) or rely on webhook data
     * from an SMTP service.
     *
     * @param string $raw_email_content The full raw content of the bounced/complaint email.
     * @return bool True if processed successfully, false otherwise.
     */
    public function processIncomingEmail($raw_email_content) {
        // This is a highly simplified parser.
        // A real parser would analyze headers and body for specific bounce/FBL codes.

        $email = null;
        $bounce_type = null;
        $reason = 'Unknown';

        // Attempt to find recipient email in headers or body
        if (preg_match('/Final-Recipient: rfc822; ([^\s]+)/i', $raw_email_content, $matches)) {
            $email = trim($matches[1]);
        } elseif (preg_match('/Original-Recipient: rfc822; ([^\s]+)/i', $raw_email_content, $matches)) {
            $email = trim($matches[1]);
        } elseif (preg_match('/X-Original-To: ([^\s]+)/i', $raw_email_content, $matches)) {
            $email = trim($matches[1]);
        } elseif (preg_match('/Feedback-ID: .*?:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}):/i', $raw_email_content, $matches)) {
            $email = trim($matches[1]);
            $bounce_type = 'complaint';
            $reason = 'Feedback Loop Complaint';
        }

        // Determine bounce type (very basic)
        if ($email && !$bounce_type) {
            if (preg_match('/(status: 5\.\d\.\d|permanently failed|address does not exist|user unknown)/i', $raw_email_content)) {
                $bounce_type = 'hard';
                $reason = 'Hard Bounce: Address does not exist or permanent error.';
            } elseif (preg_match('/(status: 4\.\d\.\d|temporarily unavailable|mailbox full|quota exceeded)/i', $raw_email_content)) {
                $bounce_type = 'soft';
                $reason = 'Soft Bounce: Temporary issue (e.g., mailbox full).';
            } elseif (preg_match('/(spam|abuse|complaint)/i', $raw_email_content)) {
                $bounce_type = 'complaint';
                $reason = 'Spam Complaint detected.';
            }
        }

        if ($email && $bounce_type) {
            error_log("MassMailerBounceHandler: Detected {$bounce_type} for {$email}. Reason: {$reason}");
            return $this->handleBounce($email, $bounce_type, $reason, $raw_email_content);
        }

        error_log('MassMailerBounceHandler: Could not parse bounce/complaint from email content.');
        return false;
    }

    /**
     * Handles a detected bounce or complaint by updating subscriber status and logging.
     *
     * @param string $email The email address that bounced/complained.
     * @param string $bounce_type 'soft', 'hard', or 'complaint'.
     * @param string $reason The reason for the bounce/complaint.
     * @param string $raw_email_content The full raw email content (for logging).
     * @return bool True on success, false on failure.
     */
    public function handleBounce($email, $bounce_type, $reason, $raw_email_content = null) {
        $subscriber = $this->subscriber_manager->getSubscriber($email);
        $subscriber_id = $subscriber ? $subscriber['subscriber_id'] : null;

        // Log the bounce/complaint
        try {
            $sql = "INSERT INTO {$this->bounces_log_table} (subscriber_id, email, bounce_type, reason, raw_email_content) VALUES (:subscriber_id, :email, :bounce_type, :reason, :raw_email_content)";
            $this->db->query($sql, [
                ':subscriber_id' => $subscriber_id,
                ':email' => $email,
                ':bounce_type' => $bounce_type,
                ':reason' => $reason,
                ':raw_email_content' => $raw_email_content
            ]);
        } catch (PDOException $e) {
            error_log('MassMailerBounceHandler: Error logging bounce: ' . $e->getMessage());
            return false;
        }

        // Update subscriber status based on bounce type
        if ($subscriber) {
            $current_status = $subscriber['status'];
            $new_status = $current_status;

            switch ($bounce_type) {
                case 'hard':
                case 'complaint':
                    // For hard bounces and complaints, permanently mark as unsubscribed/bounced
                    $new_status = 'bounced'; // Or 'unsubscribed' if you prefer
                    break;
                case 'soft':
                    // For soft bounces, you might increment a counter and only change status after N soft bounces
                    // For simplicity, we'll just log for now and not immediately change status.
                    // A more advanced system would track soft bounce counts per subscriber.
                    error_log("MassMailerBounceHandler: Soft bounce for {$email}. Status remains {$current_status}.");
                    return true; // Don't change status for soft bounce immediately
            }

            if ($new_status !== $current_status) {
                if ($this->subscriber_manager->addOrUpdateSubscriber($email, null, null, $new_status)) {
                    error_log("MassMailerBounceHandler: Subscriber {$email} status updated to '{$new_status}' due to {$bounce_type} bounce/complaint.");
                    return true;
                } else {
                    error_log("MassMailerBounceHandler: Failed to update subscriber {$email} status to '{$new_status}'.");
                    return false;
                }
            }
            return true; // Status was already correct or no change needed
        } else {
            error_log("MassMailerBounceHandler: Bounced email for {$email} but subscriber not found in database.");
            return true; // Logged the bounce, even if subscriber not found
        }
    }

    /**
     * Initiates the process of fetching and handling bounces/complaints via IMAP.
     * This method is designed to be called by a cron job.
     *
     * IMPORTANT: This requires IMAP PHP extension to be enabled.
     * This will connect to a mailbox, read emails, process them, and delete them.
     * Ensure the mailbox is dedicated to receiving bounces/FBLs.
     */
    public function processBouncesViaIMAP() {
        $host = $this->settings_manager->getSetting('bounce_imap_host');
        $port = $this->settings_manager->getSetting('bounce_imap_port', 993);
        $username = $this->settings_manager->getSetting('bounce_imap_username');
        $password = $this->settings_manager->getSetting('bounce_imap_password');
        $flags = $this->settings_manager->getSetting('bounce_imap_flags', '/imap/ssl/novalidate-cert'); // e.g., /imap/ssl/novalidate-cert or /pop3/ssl

        if (empty($host) || empty($username) || empty($password)) {
            error_log('MassMailerBounceHandler: IMAP settings incomplete. Cannot process bounces.');
            return false;
        }

        $mailbox_path = "{" . $host . ":" . $port . $flags . "}INBOX";
        error_log("MassMailerBounceHandler: Attempting to connect to IMAP: " . $mailbox_path);

        $inbox = imap_open($mailbox_path, $username, $password);

        if (!$inbox) {
            error_log('MassMailerBounceHandler: IMAP connection failed: ' . imap_last_error());
            return false;
        }

        $emails = imap_search($inbox, 'ALL'); // Get all emails

        if ($emails) {
            error_log('MassMailerBounceHandler: Found ' . count($emails) . ' emails to process.');
            foreach ($emails as $mail_id) {
                $raw_email = imap_fetchbody($inbox, $mail_id, ""); // Fetch full raw email
                if ($raw_email) {
                    $this->processIncomingEmail($raw_email);
                    imap_delete($inbox, $mail_id); // Mark for deletion after processing
                }
            }
            imap_expunge($inbox); // Permanently delete marked emails
            error_log('MassMailerBounceHandler: Finished processing IMAP emails.');
        } else {
            error_log('MassMailerBounceHandler: No emails found in IMAP inbox.');
        }

        imap_close($inbox);
        return true;
    }

    /**
     * Retrieves all logged bounces/complaints.
     *
     * @return array An array of bounce log data.
     */
    public function getAllBouncesLog() {
        $sql = "SELECT bl.*, s.email AS subscriber_email_from_db, s.first_name, s.last_name
                FROM {$this->bounces_log_table} bl
                LEFT JOIN " . MM_TABLE_PREFIX . "subscribers s ON bl.subscriber_id = s.subscriber_id
                ORDER BY bl.processed_at DESC";
        try {
            return $this->db->fetchAll($sql);
        } catch (PDOException $e) {
            error_log('MassMailerBounceHandler: Error getting all bounces log: ' . $e->getMessage());
            return [];
        }
    }
}
