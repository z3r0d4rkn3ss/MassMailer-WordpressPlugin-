<?php
/**
 * Mass Mailer Main Plugin File - Bounce Handling Updates
 *
 * This file integrates the new components for Bounce & Complaint Handling.
 * It adds database table creation for bounce logs and sets up a cron job
 * to process incoming bounce/FBL emails.
 *
 * @package Mass_Mailer
 */

// Start session at the very beginning for authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure no direct access to the file
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/'); // Define ABSPATH for standalone use if not WordPress
}

// --- Configuration and Core Includes (Existing from all previous phases) ---
require_once ABSPATH . '/config.php';
require_once ABSPATH . '/includes/db.php';
require_once ABSPATH . '/includes/list-manager.php';
require_once ABSPATH . '/includes/subscriber-manager.php';
require_once ABSPATH . '/includes/template-manager.php';
require_once ABSPATH . '/includes/mailer.php';
require_once ABSPATH . '/includes/campaign-manager.php';
require_once ABSPATH . '/includes/queue-manager.php';
require_once ABSPATH . '/includes/tracker.php';
require_once ABSPATH . '/includes/automation-manager.php';
require_once ABSPATH . '/includes/auth.php';
require_once ABSPATH . '/includes/settings-manager.php';

// --- NEW: Include Bounce Handler ---
require_once ABSPATH . '/includes/bounce-handler.php';


// --- Plugin Activation and Deactivation Hooks (Updated for Bounce Handling) ---
function mass_mailer_activate() {
    $db = MassMailerDB::getInstance();
    $template_manager = new MassMailerTemplateManager();
    $campaign_manager = new MassMailerCampaignManager();
    $queue_manager = new MassMailerQueueManager();
    $tracker = new MassMailerTracker();
    $automation_manager = new MassMailerAutomationManager();
    $auth = new MassMailerAuth();
    $settings_manager = new MassMailerSettingsManager();
    $bounce_handler = new MassMailerBounceHandler(); // Instantiate to call create table method


    // SQL for existing tables (ensure they are idempotent with IF NOT EXISTS)
    $sql_lists = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "lists` (
        `list_id` INT AUTO_INCREMENT PRIMARY KEY,
        `list_name` VARCHAR(255) NOT NULL UNIQUE,
        `list_description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_subscribers = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "subscribers` (
        `subscriber_id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL UNIQUE,
        `first_name` VARCHAR(100),
        `last_name` VARCHAR(100),
        `status` ENUM('subscribed', 'unsubscribed', 'pending', 'bounced') DEFAULT 'pending',
        `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_rel = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "list_subscriber_rel` (
        `list_id` INT NOT NULL,
        `subscriber_id` INT NOT NULL,
        `status` ENUM('active', 'inactive') DEFAULT 'active',
        `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`list_id`, `subscriber_id`),
        FOREIGN KEY (`list_id`) REFERENCES `" . MM_TABLE_PREFIX . "lists`(`list_id`) ON DELETE CASCADE,
        FOREIGN KEY (`subscriber_id`) REFERENCES `" . MM_TABLE_PREFIX . "subscribers`(`subscriber_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_campaigns = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "campaigns` (
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

    $sql_queue = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "queue` (
            `queue_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `subscriber_id` INT NOT NULL,
            `status` ENUM('pending', 'processing', 'sent', 'failed', 'skipped') DEFAULT 'pending',
            `attempts` INT DEFAULT 0,
            `last_attempt_at` TIMESTAMP NULL,
            `error_message` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `sent_at` TIMESTAMP NULL,
            UNIQUE KEY `campaign_subscriber_idx` (`campaign_id`, `subscriber_id`),
            FOREIGN KEY (`campaign_id`) REFERENCES " . MM_TABLE_PREFIX . "campaigns(`campaign_id`) ON DELETE CASCADE,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_opens = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "opens` (
            `open_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `subscriber_id` INT NOT NULL,
            `opened_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            UNIQUE KEY `campaign_subscriber_open_idx` (`campaign_id`, `subscriber_id`),
            FOREIGN KEY (`campaign_id`) REFERENCES " . MM_TABLE_PREFIX . "campaigns(`campaign_id`) ON DELETE CASCADE,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_clicks = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "clicks` (
            `click_id` INT AUTO_INCREMENT PRIMARY KEY,
            `campaign_id` INT NOT NULL,
            `subscriber_id` INT NOT NULL,
            `original_url` TEXT NOT NULL,
            `clicked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            FOREIGN KEY (`campaign_id`) REFERENCES " . MM_TABLE_PREFIX . "campaigns(`campaign_id`) ON DELETE CASCADE,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_automations = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "automations` (
            `automation_id` INT AUTO_INCREMENT PRIMARY KEY,
            `automation_name` VARCHAR(255) NOT NULL UNIQUE,
            `trigger_type` VARCHAR(50) NOT NULL,
            `trigger_config` JSON NULL,
            `action_type` VARCHAR(50) NOT NULL,
            `action_config` JSON NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";


    try {
        $db->query($sql_lists);
        $db->query($sql_subscribers);
        $db->query($sql_rel);
        $template_manager->createTemplateTable();
        $campaign_manager->createCampaignTable();
        $queue_manager->createQueueTable();
        $tracker->createTrackingTables();
        $automation_manager->createAutomationTable();
        $auth->createUsersTable();
        $settings_manager->createSettingsTable();
        // NEW: Create bounces log table
        $bounce_handler->createBouncesLogTable();
        error_log('Mass Mailer: All database tables created/checked successfully.');
    } catch (PDOException $e) {
        error_log('Mass Mailer: Database table creation failed: ' . $e->getMessage());
    }
}
// register_activation_hook(__FILE__, 'mass_mailer_activate'); // Example hook


function mass_mailer_deactivate() {
    // Cleanup tasks on deactivation
}
// register_deactivation_hook(__FILE__, 'mass_mailer_deactivate'); // Example hook


// --- Frontend Subscription Form Integration (Existing from Phase 1 & 7) ---
function mass_mailer_subscription_form($atts = []) {
    $atts = array_merge([
        'list_id' => null,
        'title' => 'Subscribe to Our Newsletter',
        'description' => 'Stay updated with our latest news and offers!',
        'show_name_fields' => true,
    ], $atts);
    ob_start();
    require ABSPATH . '/views/form-builder.php';
    return ob_get_clean();
}
// add_shortcode('mass_mailer_form', 'mass_mailer_subscription_form'); // Example shortcode


// --- Handle Frontend Form Submission (AJAX Endpoint - Existing from Phase 1 & 7) ---
function mass_mailer_handle_form_submission() {
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];
    if (isset($_POST['mass_mailer_form_submit']) && isset($_POST['email'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : null;
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : null;
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please enter a valid email address.';
        } else {
            $subscriber_manager = new MassMailerSubscriberManager();
            $list_manager = new MassMailerListManager();

            $subscriber_id = $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'pending');

            if ($subscriber_id) {
                if ($list_id) {
                    $list = $list_manager->getList($list_id);
                    if ($list) {
                        $added_to_list = $subscriber_manager->addSubscriberToList($subscriber_id, $list_id, 'active');
                        if ($added_to_list) {
                            $response['success'] = true;
                            $response['message'] = 'Thank you for subscribing!';
                            $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'subscribed');

                            $automation_manager = new MassMailerAutomationManager();
                            $automation_manager->processAutomations('subscriber_added', [
                                'subscriber_id' => $subscriber_id,
                                'list_id' => $list_id,
                                'email' => $email
                            ]);

                        } else {
                            $response['message'] = 'Subscription failed for the specified list. Please try again.';
                        }
                    } else {
                        $response['message'] = 'The specified list does not exist.';
                    }
                } else {
                    $response['success'] = true;
                    $response['message'] = 'Thank you for subscribing! (No specific list selected)';
                    $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'subscribed');
                }
            } else {
                $response['message'] = 'Failed to process your subscription. Please try again later.';
            }
        }
    } else {
        $response['message'] = 'Invalid form submission.';
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
// add_action('wp_ajax_mass_mailer_subscribe', 'mass_mailer_handle_form_submission'); // Example hook
// add_action('wp_ajax_nopriv_mass_mailer_subscribe', 'mass_mailer_handle_form_submission'); // Example hook


// --- Cron Job / Queue Processing Trigger (Existing from Phase 4) ---
function mass_mailer_process_queue_cron() {
    $queue_manager = new MassMailerQueueManager();
    $queue_manager->processQueueBatch();
}
// Example of how you might schedule this in a WordPress environment:
// if (!wp_next_scheduled('mass_mailer_queue_cron_hook')) {
//     wp_schedule_event(time(), 'hourly', 'mass_mailer_queue_cron_hook'); // Schedule hourly
// }
// add_action('mass_mailer_queue_cron_hook', 'mass_mailer_process_queue_cron');

// For a standalone PHP application, you would set up a system-level cron job
// to hit a specific PHP script that executes `mass_mailer_process_queue_cron()`.
// Example cron entry (on your server):
// * * * * * /usr/bin/php /path/to/your/mass-mailer/cron-worker.php > /dev/null 2>&1


// --- NEW: Cron Job for Bounce Processing ---
/**
 * Function to be triggered by a cron job to process bounced emails via IMAP.
 */
function mass_mailer_process_bounces_cron() {
    $settings_manager = new MassMailerSettingsManager();
    if ($settings_manager->getSetting('bounce_handling_enabled') === '1') {
        $bounce_handler = new MassMailerBounceHandler();
        $bounce_handler->processBouncesViaIMAP();
    } else {
        error_log('MassMailer: Bounce handling is disabled in settings.');
    }
}
// Example of how you might schedule this in a WordPress environment:
// if (!wp_next_scheduled('mass_mailer_bounces_cron_hook')) {
//     wp_schedule_event(time(), 'daily', 'mass_mailer_bounces_cron_hook'); // Schedule daily
// }
// add_action('mass_mailer_bounces_cron_hook', 'mass_mailer_process_bounces_cron');

// For a standalone PHP application, you would set up a separate system-level cron job
// to hit a specific PHP script that executes `mass_mailer_process_bounces_cron()`.
// Example cron entry (on your server):
// 0 0 * * * /usr/bin/php /path/to/your/mass-mailer/bounce-worker.php > /dev/null 2>&1
/*
// bounce-worker.php
<?php
require_once dirname(__FILE__) . '/mass-mailer.php'; // Load your main plugin file
mass_mailer_process_bounces_cron();
?>
*/


// --- Tracking Pixel and Click Redirect Endpoints (Existing from Phase 5 & 7) ---
function mass_mailer_handle_tracking() {
    $tracker = new MassMailerTracker();
    $automation_manager = new MassMailerAutomationManager();
    $subscriber_manager = new MassMailerSubscriberManager();

    $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
    $subscriber_id = isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'track_open':
                if ($campaign_id > 0 && $subscriber_id > 0) {
                    $logged = $tracker->logOpen($campaign_id, $subscriber_id, $ip_address, $user_agent);
                    if ($logged) {
                        $automation_manager->processAutomations('campaign_opened', [
                            'campaign_id' => $campaign_id,
                            'subscriber_id' => $subscriber_id,
                            'ip_address' => $ip_address,
                            'user_agent' => $user_agent
                        ]);
                    }
                }
                header('Content-Type: image/gif');
                echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
                exit;
            case 'track_click':
                if ($campaign_id > 0 && $subscriber_id > 0) {
                    $original_url_encoded = isset($_GET['url']) ? $_GET['url'] : '';
                    $original_url = base66_decode(urldecode($original_url_encoded));

                    if (!empty($original_url)) {
                        $logged = $tracker->logClick($campaign_id, $subscriber_id, $original_url, $ip_address, $user_agent);
                        if ($logged) {
                            $automation_manager->processAutomations('campaign_clicked', [
                                'campaign_id' => $campaign_id,
                                'subscriber_id' => $subscriber_id,
                                'original_url' => $original_url,
                                'ip_address' => $ip_address,
                                'user_agent' => $user_agent
                            ]);
                        }
                        header('Location: ' . $original_url);
                        exit;
                    } else {
                        error_log('MassMailer: Invalid URL for click tracking. campaign_id: ' . $campaign_id . ', subscriber_id: ' . $subscriber_id);
                        header('Location: /');
                        exit;
                    }
                }
                break; // Break from switch if campaign/subscriber IDs are invalid
            case 'unsubscribe':
                $email_to_unsubscribe = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_SANITIZE_EMAIL) : '';
                if (!empty($email_to_unsubscribe)) {
                    $subscriber = $subscriber_manager->getSubscriber($email_to_unsubscribe);
                    if ($subscriber) {
                        $subscriber_manager->addOrUpdateSubscriber($email_to_unsubscribe, null, null, 'unsubscribed');
                        echo "<h1>You have been successfully unsubscribed.</h1><p>You will no longer receive emails from us.</p>";
                        error_log('MassMailer: Subscriber ' . $email_to_unsubscribe . ' unsubscribed.');
                    } else {
                        echo "<h1>Unsubscription failed.</h1><p>Subscriber not found.</p>";
                        error_log('MassMailer: Unsubscribe failed, email not found: ' . $email_to_unsubscribe);
                    }
                } else {
                    echo "<h1>Unsubscription failed.</h1><p>Invalid unsubscribe request.</p>";
                    error_log('MassMailer: Invalid unsubscribe request.');
                }
                exit;
        }
    }
}

// Simple routing for tracking and unsubscribe actions
if (isset($_GET['action']) && in_array($_GET['action'], ['track_open', 'track_click', 'unsubscribe'])) {
    mass_mailer_handle_tracking();
}

// Example simple routing for frontend form submission (existing)
// if (isset($_GET['action']) && $_GET['action'] === 'mass_mailer_subscribe') {
//     mass_mailer_handle_form_submission();
// }

?>
