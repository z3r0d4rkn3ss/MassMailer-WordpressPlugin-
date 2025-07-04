<?php
/**
 * Mass Mailer Main Plugin File - GDPR/Privacy Features Updates
 *
 * This file updates the main plugin to support GDPR features.
 * It adds database column updates for double opt-in and routing for the verification endpoint.
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
require_once ABSPATH . '/includes/bounce-handler.php';
require_once ABSPATH . '/includes/segment-manager.php';
require_once ABSPATH . '/includes/ab-test-manager.php';
require_once ABSPATH . '/includes/api-manager.php';


// --- Plugin Activation and Deactivation Hooks (Updated for GDPR) ---
function mass_mailer_activate() {
    $db = MassMailerDB::getInstance();
    $template_manager = new MassMailerTemplateManager();
    $campaign_manager = new MassMailerCampaignManager();
    $queue_manager = new MassMailerQueueManager();
    $tracker = new MassMailerTracker();
    $automation_manager = new MassMailerAutomationManager();
    $auth = new MassMailerAuth();
    $settings_manager = new MassMailerSettingsManager();
    $bounce_handler = new MassMailerBounceHandler();
    $segment_manager = new MassMailerSegmentManager();
    $ab_test_manager = new MassMailerABTestManager();
    $api_manager = new MassMailerAPIManager();


    // SQL for existing tables (ensure they are idempotent with IF NOT EXISTS)
    // IMPORTANT: Note the changes in mm_subscribers table structure (verification_token, opt_in_date)
    // If you're updating an existing DB, you'll need ALTER TABLE statements.
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
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `verification_token` VARCHAR(64) NULL UNIQUE, -- NEW for double opt-in
        `opt_in_date` TIMESTAMP NULL -- NEW: Date of confirmed opt-in
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

    $sql_templates = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "templates` (
            `template_id` INT AUTO_INCREMENT PRIMARY KEY,
            `template_name` VARCHAR(255) NOT NULL UNIQUE,
            `template_subject` VARCHAR(255) NOT NULL,
            `template_html` LONGTEXT,
            `template_text` LONGTEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
            FOREIGN KEY (`subscriber_id`) REFERENCES `" . MM_TABLE_PREFIX . "subscribers`(`subscriber_id`) ON DELETE CASCADE
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
            FOREIGN KEY (`subscriber_id`) REFERENCES `" . MM_TABLE_PREFIX . "subscribers`(`subscriber_id`) ON DELETE CASCADE
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
            FOREIGN KEY (`subscriber_id`) REFERENCES `" . MM_TABLE_PREFIX . "subscribers`(`subscriber_id`) ON DELETE CASCADE
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

    $sql_users = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "users` (
            `user_id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'editor') DEFAULT 'editor',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_settings = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "settings` (
            `setting_id` INT AUTO_INCREMENT PRIMARY KEY,
            `setting_key` VARCHAR(100) NOT NULL UNIQUE,
            `setting_value` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_bounces_log = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "bounces_log` (
            `log_id` INT AUTO_INCREMENT PRIMARY KEY,
            `subscriber_id` INT NULL,
            `email` VARCHAR(255) NOT NULL,
            `bounce_type` ENUM('soft', 'hard', 'complaint') NOT NULL,
            `reason` TEXT NULL,
            `processed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `raw_email_content` LONGTEXT NULL,
            FOREIGN KEY (`subscriber_id`) REFERENCES " . MM_TABLE_PREFIX . "subscribers(`subscriber_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_segments = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "segments` (
            `segment_id` INT AUTO_INCREMENT PRIMARY KEY,
            `segment_name` VARCHAR(255) NOT NULL UNIQUE,
            `segment_rules` JSON NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_ab_tests = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "ab_tests` (
            `ab_test_id` INT AUTO_INCREMENT PRIMARY KEY,
            `test_name` VARCHAR(255) NOT NULL UNIQUE,
            `test_type` ENUM('subject_line', 'content') NOT NULL,
            `variant_campaign_ids` JSON NOT NULL,
            `audience_split_percentage` INT DEFAULT 10,
            `winner_criteria` ENUM('opens', 'clicks') DEFAULT 'clicks',
            `status` ENUM('draft', 'running', 'completed', 'cancelled') DEFAULT 'draft',
            `winner_campaign_id` INT NULL,
            `remaining_audience_sent` BOOLEAN DEFAULT FALSE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql_api_keys = "CREATE TABLE IF NOT EXISTS `" . MM_TABLE_PREFIX . "api_keys` (
            `api_key_id` INT AUTO_INCREMENT PRIMARY KEY,
            `api_key` VARCHAR(64) NOT NULL UNIQUE,
            `user_id` INT NULL,
            `description` VARCHAR(255) NULL,
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES " . MM_TABLE_PREFIX . "users(`user_id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";


    try {
        $db->query($sql_lists);
        $db->query($sql_subscribers); // This now includes verification_token and opt_in_date
        $db->query($sql_rel);
        $template_manager->createTemplateTable();
        $segment_manager->createSegmentsTable();
        $campaign_manager->createCampaignTable();
        $queue_manager->createQueueTable();
        $tracker->createTrackingTables();
        $automation_manager->createAutomationTable();
        $auth->createUsersTable();
        $settings_manager->createSettingsTable();
        $bounce_handler->createBouncesLogTable();
        $ab_test_manager->createABTestsTable();
        $api_manager->createApiKeysTable();

        // Add foreign key for ab_test_id to mm_campaigns if it doesn't exist
        $check_fk_campaigns_ab_test_sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . MM_TABLE_PREFIX . "campaigns' AND COLUMN_NAME = 'ab_test_id' AND REFERENCED_TABLE_NAME = '" . MM_TABLE_PREFIX . "ab_tests';";
        $fk_campaigns_ab_test_exists = $db->fetch($check_fk_campaigns_ab_test_sql);

        if (!$fk_campaigns_ab_test_exists) {
            $add_fk_campaigns_ab_test_sql = "ALTER TABLE `" . MM_TABLE_PREFIX . "campaigns` ADD CONSTRAINT `fk_campaigns_ab_test` FOREIGN KEY (`ab_test_id`) REFERENCES `" . MM_TABLE_PREFIX . "ab_tests`(`ab_test_id`) ON DELETE SET NULL;";
            $db->query($add_fk_campaigns_ab_test_sql);
            error_log('Mass Mailer: Added foreign key for ab_test_id to mm_campaigns.');
        }

        // Add foreign key for user_id to mm_api_keys if it doesn't exist
        $check_fk_api_keys_user_sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . MM_TABLE_PREFIX . "api_keys' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = '" . MM_TABLE_PREFIX . "users';";
        $fk_api_keys_user_exists = $db->fetch($check_fk_api_keys_user_sql);

        if (!$fk_api_keys_user_exists) {
            $add_fk_api_keys_user_sql = "ALTER TABLE `" . MM_TABLE_PREFIX . "api_keys` ADD CONSTRAINT `fk_api_keys_user` FOREIGN KEY (`user_id`) REFERENCES `" . MM_TABLE_PREFIX . "users`(`user_id`) ON DELETE SET NULL;";
            $db->query($add_fk_api_keys_user_sql);
            error_log('Mass Mailer: Added foreign key for user_id to mm_api_keys.');
        }

        // NEW: Add columns to mm_subscribers for GDPR/Double Opt-in if they don't exist
        // Check for 'verification_token' column
        $check_token_col_sql = "SHOW COLUMNS FROM `" . MM_TABLE_PREFIX . "subscribers` LIKE 'verification_token';";
        $token_col_exists = $db->fetch($check_token_col_sql);
        if (!$token_col_exists) {
            $add_token_col_sql = "ALTER TABLE `" . MM_TABLE_PREFIX . "subscribers` ADD COLUMN `verification_token` VARCHAR(64) NULL UNIQUE AFTER `updated_at`;";
            $db->query($add_token_col_sql);
            error_log('Mass Mailer: Added verification_token column to mm_subscribers.');
        }

        // Check for 'opt_in_date' column
        $check_opt_in_col_sql = "SHOW COLUMNS FROM `" . MM_TABLE_PREFIX . "subscribers` LIKE 'opt_in_date';";
        $opt_in_col_exists = $db->fetch($check_opt_in_col_sql);
        if (!$opt_in_col_exists) {
            $add_opt_in_col_sql = "ALTER TABLE `" . MM_TABLE_PREFIX . "subscribers` ADD COLUMN `opt_in_date` TIMESTAMP NULL AFTER `verification_token`;";
            $db->query($add_opt_in_col_sql);
            error_log('Mass Mailer: Added opt_in_date column to mm_subscribers.');
        }


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


// --- Frontend Subscription Form Integration (Existing from Phase 1) ---
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

            // New subscribers will be 'pending' and receive a verification email
            $subscriber_id = $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'pending');

            if ($subscriber_id) {
                if ($list_id) {
                    $list = $list_manager->getList($list_id);
                    if ($list) {
                        // Add to list with 'pending' status initially, will become 'active' on verification
                        $added_to_list = $subscriber_manager->addSubscriberToList($subscriber_id, $list_id, 'pending');
                        if ($added_to_list) {
                            $response['success'] = true;
                            $response['message'] = 'Thank you for subscribing! Please check your email to confirm your subscription.';
                            // Automation for 'subscriber_added' will trigger only on 'subscribed' status
                            // So, we don't trigger it here for 'pending' status.
                        } else {
                            $response['message'] = 'Subscription failed for the specified list. Please try again.';
                        }
                    } else {
                        $response['message'] = 'The specified list does not exist.';
                    }
                } else {
                    $response['success'] = true;
                    $response['message'] = 'Thank you for subscribing! Please check your email to confirm your subscription.';
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


// --- Cron Job for Bounce Processing (Existing from Bounce Handling Phase) ---
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

// --- Cron Job for A/B Test Processing (Existing from A/B Testing Phase) ---
function mass_mailer_process_ab_tests_cron() {
    $ab_test_manager = new MassMailerABTestManager();
    $all_ab_tests = $ab_test_manager->getAllABTests();

    foreach ($all_ab_tests as $test) {
        if ($test['status'] === 'running') {
            $winner_id = $ab_test_manager->determineWinner($test['ab_test_id']);
            if ($winner_id) {
                error_log('MassMailer: A/B Test ' . $test['ab_test_id'] . ' winner determined by cron: ' . $winner_id);
            }
        } elseif ($test['status'] === 'completed' && !$test['remaining_audience_sent'] && $test['winner_campaign_id']) {
            $ab_test_manager->sendWinnerToRemainingAudience($test['ab_test_id']);
            error_log('MassMailer: A/B Test ' . $test['ab_test_id'] . ' winner sent to remaining audience by cron.');
        }
    }
}
// Example of how you might schedule this in a WordPress environment:
// if (!wp_next_scheduled('mass_mailer_ab_tests_cron_hook')) {
//     wp_schedule_event(time(), 'twicedaily', 'mass_mailer_ab_tests_cron_hook'); // Schedule twice daily
// }
// add_action('mass_mailer_ab_tests_cron_hook', 'mass_mailer_process_ab_tests_cron');


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
                break;
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

// NEW: Routing for email verification endpoint
if (isset($_GET['action']) && $_GET['action'] === 'verify_email') {
    require_once ABSPATH . '/verify.php';
    exit; // Stop further execution after handling verification
}

?>
