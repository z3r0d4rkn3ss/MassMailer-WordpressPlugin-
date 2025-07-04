<?php
/**
 * Mass Mailer Main Plugin File - Phase 5 Updates
 *
 * This file integrates the new components for Phase 5: Tracking & Analytics.
 * It adds database table creation for tracking and sets up routing for
 * tracking pixel and click redirect URLs.
 *
 * IMPORTANT: This code snippet provides the *additions* and *modifications* to your existing
 * mass-mailer.php file. You will need to integrate these sections.
 *
 * @package Mass_Mailer
 */

// Ensure no direct access to the file
if (!defined('ABSPATH')) {
    die('Direct access not allowed.');
}

// --- Configuration and Core Includes (Existing from Phase 1, 2, 3, & 4) ---
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/includes/db.php';
require_once dirname(__FILE__) . '/includes/list-manager.php';
require_once dirname(__FILE__) . '/includes/subscriber-manager.php';
require_once dirname(__FILE__) . '/includes/template-manager.php';
require_once dirname(__FILE__) . '/includes/mailer.php';
require_once dirname(__FILE__) . '/includes/campaign-manager.php';
require_once dirname(__FILE__) . '/includes/queue-manager.php';

// --- NEW: Include Tracker Manager ---
require_once dirname(__FILE__) . '/includes/tracker.php';


// --- Plugin Activation and Deactivation Hooks (Updated for Phase 5) ---
function mass_mailer_activate() {
    $db = MassMailerDB::getInstance();
    $template_manager = new MassMailerTemplateManager();
    $campaign_manager = new MassMailerCampaignManager();
    $queue_manager = new MassMailerQueueManager();
    $tracker = new MassMailerTracker(); // Instantiate to call create table method

    // SQL to create tables (from your db-schema.sql) - Ensure these are idempotent (IF NOT EXISTS)
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

    try {
        $db->query($sql_lists);
        $db->query($sql_subscribers);
        $db->query($sql_rel);
        $template_manager->createTemplateTable();
        $campaign_manager->createCampaignTable();
        $queue_manager->createQueueTable();
        // NEW: Create tracking tables
        $tracker->createTrackingTables();
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
    require dirname(__FILE__) . '/views/form-builder.php';
    return ob_get_clean();
}
// add_shortcode('mass_mailer_form', 'mass_mailer_subscription_form'); // Example shortcode


// --- Handle Frontend Form Submission (AJAX Endpoint - Existing from Phase 1) ---
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


// --- NEW: Tracking Pixel and Click Redirect Endpoints ---
/**
 * Handles tracking of email opens and clicks.
 * This function is designed to be hit directly by image requests (for opens)
 * or link clicks (for redirects).
 */
function mass_mailer_handle_tracking() {
    $tracker = new MassMailerTracker();
    $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
    $subscriber_id = isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

    if ($campaign_id > 0 && $subscriber_id > 0) {
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'track_open':
                    // Log the open
                    $tracker->logOpen($campaign_id, $subscriber_id, $ip_address, $user_agent);
                    // Serve a 1x1 transparent GIF
                    header('Content-Type: image/gif');
                    echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw=='); // Transparent 1x1 GIF
                    exit;
                case 'track_click':
                    $original_url_encoded = isset($_GET['url']) ? $_GET['url'] : '';
                    $original_url = base66_decode(urldecode($original_url_encoded)); // Use base66_decode from mailer.php

                    if (!empty($original_url)) {
                        $tracker->logClick($campaign_id, $subscriber_id, $original_url, $ip_address, $user_agent);
                        // Redirect to the original URL
                        header('Location: ' . $original_url);
                        exit;
                    } else {
                        // Handle invalid URL or missing parameters for click tracking
                        error_log('MassMailer: Invalid URL for click tracking. campaign_id: ' . $campaign_id . ', subscriber_id: ' . $subscriber_id);
                        // Redirect to a default page or show an error
                        header('Location: /'); // Redirect to homepage or error page
                        exit;
                    }
                case 'unsubscribe':
                    $email_to_unsubscribe = isset($_GET['email']) ? filter_var($_GET['email'], FILTER_SANITIZE_EMAIL) : '';
                    if (!empty($email_to_unsubscribe)) {
                        $subscriber_manager = new MassMailerSubscriberManager();
                        // Find subscriber by email and update status to 'unsubscribed'
                        $subscriber = $subscriber_manager->getSubscriber($email_to_unsubscribe);
                        if ($subscriber) {
                            $subscriber_manager->addOrUpdateSubscriber($email_to_unsubscribe, null, null, 'unsubscribed');
                            // Optionally remove from all lists or specific list if parameter provided
                            // For simplicity, we'll just update main status to unsubscribed.
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
