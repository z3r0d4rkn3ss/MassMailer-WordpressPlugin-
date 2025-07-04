<?php
/**
 * Mass Mailer Main Plugin File - Phase 3 Updates
 *
 * This file integrates the new components for Phase 3: Campaign System.
 *
 * IMPORTANT: This code snippet provides the *additions* to your existing
 * mass-mailer.php file. You will need to integrate these sections.
 *
 * @package Mass_Mailer
 */

// Ensure no direct access to the file
if (!defined('ABSPATH')) {
    die('Direct access not allowed.');
}

// --- Configuration and Core Includes (Existing from Phase 1 & 2) ---
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/includes/db.php';
require_once dirname(__FILE__) . '/includes/list-manager.php';
require_once dirname(__FILE__) . '/includes/subscriber-manager.php';
require_once dirname(__FILE__) . '/includes/template-manager.php';
require_once dirname(__FILE__) . '/includes/mailer.php';

// --- NEW: Include Campaign Manager ---
require_once dirname(__FILE__) . '/includes/campaign-manager.php';


// --- Plugin Activation and Deactivation Hooks (Updated for Phase 3) ---
function mass_mailer_activate() {
    $db = MassMailerDB::getInstance();
    $template_manager = new MassMailerTemplateManager();
    $campaign_manager = new MassMailerCampaignManager(); // Instantiate to call create table method

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
        // NEW: Create campaigns table
        $campaign_manager->createCampaignTable(); // Call method from campaign manager
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

// Example simple routing for standalone app (for demonstration, not robust for production):
// if (isset($_GET['action']) && $_GET['action'] === 'mass_mailer_subscribe') {
//     mass_mailer_handle_form_submission();
// }

// --- Example of using the Mailer (for testing/demonstration) - Existing from Phase 2 ---
/*
function mass_mailer_test_email_send() {
    $mailer = new MassMailerMailer();
    $template_id = 1; // Replace with an actual template ID from your DB
    $subscriber_id = 1; // Replace with an actual subscriber ID from your DB

    $template_manager = new MassMailerTemplateManager();
    $subscriber_manager = new MassMailerSubscriberManager();

    if ($template_manager->getTemplate($template_id) && $subscriber_manager->getSubscriber($subscriber_id)) {
        $sent = $mailer->sendTemplateEmailToSubscriber($template_id, $subscriber_id);
        if ($sent) {
            error_log('Test email sent successfully!');
        } else {
            error_log('Test email failed to send.');
        }
    } else {
        error_log('Cannot send test email: Template or Subscriber not found.');
    }
}
// mass_mailer_test_email_send();
*/

?>
