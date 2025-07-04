<?php
/**
 * Plugin Name: Mass Mailer
 * Plugin URI: https://g.co/kgs/PJ1qMQH
 * Description: A comprehensive email marketing and newsletter plugin for WordPress.
 * Version: 1.0.0
 * Author: Matthew Belcher
 * Author URI: https://g.co/kgs/PJ1qMQH
 * License: GPL2
 */

// Start session at the very beginning for authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure no direct access to the file
// This check is standard for WordPress plugin files
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/'); // Define ABSPATH for standalone use if not WordPress
    // In a real WordPress plugin, this file should always be loaded via WP.
    // die('This plugin file cannot be accessed directly.');
}

// --- Configuration and Core Includes ---
// config.php is no longer needed as db.php now uses WordPress's global settings
// require_once ABSPATH . '/config.php'; // REMOVED: No longer needed with WordPress DB integration

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


/**
 * Activation function for the Mass Mailer plugin.
 * This function is called when the plugin is activated.
 * It creates all necessary database tables and sets up initial data.
 */
function mass_mailer_activate() {
    // Get database instance
    $db = MassMailerDB::getInstance();

    // Initialize managers to ensure their table creation methods are called
    $list_manager = new MassMailerListManager();
    $subscriber_manager = new MassMailerSubscriberManager();
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

    // Call table creation methods for all managers
    // These methods should include logic to check if tables exist before creating.
    $list_manager->createListTable();
    $subscriber_manager->createSubscriberTable();
    $template_manager->createTemplateTable();
    $campaign_manager->createCampaignTable();
    $queue_manager->createQueueTable();
    $tracker->createTrackingTables(); // Creates opens and clicks tables
    $automation_manager->createAutomationsTable();
    $auth->createUsersTable(); // Also creates default admin user if none exists
    $settings_manager->createSettingsTable();
    $bounce_handler->createBouncesLogTable();
    $segment_manager->createSegmentsTable();
    $ab_test_manager->createABTestsTable();
    $api_manager->createApiKeysTable();

    // Ensure default settings are in place if they don't exist
    // This is important for the mailer and tracking to function correctly.
    if (!$settings_manager->getSetting('default_from_name')) {
        $settings_manager->setSetting('default_from_name', 'Your Company');
    }
    if (!$settings_manager->getSetting('default_from_email')) {
        $settings_manager->setSetting('default_from_email', 'noreply@yourdomain.com');
    }
    if (!$settings_manager->getSetting('tracking_base_url')) {
        // Attempt to guess base URL, or set a placeholder
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        // Adjust to point to the root of your mass-mailer directory
        $base_url = str_replace('/mass-mailer.php', '', $current_url);
        $settings_manager->setSetting('tracking_base_url', $base_url);
    }
    if (!$settings_manager->getSetting('mailer_type')) {
        $settings_manager->setSetting('mailer_type', 'mail'); // Default to PHP mail()
    }
    if (!$settings_manager->getSetting('smtp_host')) {
        $settings_manager->setSetting('smtp_host', '');
    }
    if (!$settings_manager->getSetting('smtp_port')) {
        $settings_manager->setSetting('smtp_port', '587');
    }
    if (!$settings_manager->getSetting('smtp_username')) {
        $settings_manager->setSetting('smtp_username', '');
    }
    if (!$settings_manager->getSetting('smtp_password')) {
        $settings_manager->setSetting('smtp_password', '');
    }
    if (!$settings_manager->getSetting('smtp_encryption')) {
        $settings_manager->setSetting('smtp_encryption', 'tls');
    }
    if (!$settings_manager->getSetting('bounce_handling_enabled')) {
        $settings_manager->setSetting('bounce_handling_enabled', '0'); // Disabled by default
    }
    if (!$settings_manager->getSetting('imap_host')) {
        $settings_manager->setSetting('imap_host', '');
    }
    if (!$settings_manager->getSetting('imap_port')) {
        $settings_manager->setSetting('imap_port', '993');
    }
    if (!$settings_manager->getSetting('imap_username')) {
        $settings_manager->setSetting('imap_username', '');
    }
    if (!$settings_manager->getSetting('imap_password')) {
        $settings_manager->setSetting('imap_password', '');
    }
    if (!$settings_manager->getSetting('imap_flags')) {
        $settings_manager->setSetting('imap_flags', '/imap/ssl/novalidate-cert');
    }
}

// Register the activation hook for WordPress
register_activation_hook(__FILE__, 'mass_mailer_activate');


/**
 * Handles frontend form submissions for subscriber opt-in.
 * This function is hooked to a WordPress action or called directly via AJAX.
 */
function mass_mailer_handle_form_submission() {
    // This function is designed to be called by AJAX, so it should output JSON.
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once ABSPATH . 'includes/subscriber-manager.php';
        $subscriber_manager = new MassMailerSubscriberManager();

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $list_id = filter_input(INPUT_POST, 'list_id', FILTER_SANITIZE_NUMBER_INT);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Please provide a valid email address.';
        } else {
            // Default status for new subscribers is 'pending' for double opt-in
            $subscriber_id = $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'pending');

            if ($subscriber_id) {
                // If a list_id is provided, associate the subscriber with the list
                if ($list_id) {
                    $subscriber_manager->addSubscriberToList($subscriber_id, $list_id, 'pending'); // Set list status to pending too
                }

                // Send verification email
                if ($subscriber_manager->sendVerificationEmail($subscriber_id)) {
                    $response['success'] = true;
                    $response['message'] = 'Thank you for subscribing! Please check your inbox to confirm your email address.';
                } else {
                    // This case might mean the email was added, but verification email failed to send.
                    // You might want to log this more specifically.
                    $response['message'] = 'Subscription successful, but failed to send verification email. Please try again later.';
                    error_log('MassMailer: Failed to send verification email for ' . $email);
                }
            } else {
                $response['message'] = 'Failed to process your subscription. This email might already be subscribed or a server error occurred.';
            }
        }
    } else {
        $response['message'] = 'Invalid request method.';
    }

    echo json_encode($response);
    exit; // Stop further execution
}

// Register the AJAX action for frontend form submission
// For non-logged in users (nopriv) and logged-in users
add_action('wp_ajax_mass_mailer_subscribe', 'mass_mailer_handle_form_submission');
add_action('wp_ajax_nopriv_mass_mailer_subscribe', 'mass_mailer_handle_form_submission');


/**
 * Handles tracking of email opens and clicks, and unsubscribe requests.
 * This function is called directly via GET parameters in email links.
 */
function mass_mailer_handle_tracking() {
    require_once ABSPATH . 'includes/tracker.php';
    require_once ABSPATH . 'includes/subscriber-manager.php';
    $tracker = new MassMailerTracker();
    $subscriber_manager = new MassMailerSubscriberManager();

    $action = $_GET['action'] ?? '';
    $campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
    $subscriber_id = isset($_GET['subscriber_id']) ? intval($_GET['subscriber_id']) : 0;

    switch ($action) {
        case 'track_open':
            if ($campaign_id > 0 && $subscriber_id > 0) {
                $tracker->logOpen($campaign_id, $subscriber_id);
                // Serve a 1x1 transparent GIF to track the open
                header('Content-Type: image/gif');
                echo base64_decode('R0lGODlhAQABAJAAAP///wAAACH5BAEAAAIALAAAAAABAAEAAAICTAEAOw==');
                exit;
            }
            break;

        case 'track_click':
            $url = isset($_GET['url']) ? base66_decode(urldecode($_GET['url'])) : ''; // Decode the original URL
            if ($campaign_id > 0 && $subscriber_id > 0 && !empty($url)) {
                $tracker->logClick($campaign_id, $subscriber_id, $url);
                header('Location: ' . $url); // Redirect to the original URL
                exit;
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

// Simple routing for tracking and unsubscribe actions
// This is for direct access to mass-mailer.php via GET parameters
if (isset($_GET['action']) && in_array($_GET['action'], ['track_open', 'track_click', 'unsubscribe'])) {
    mass_mailer_handle_tracking();
}

// Routing for email verification endpoint
if (isset($_GET['action']) && $_GET['action'] === 'verify_email') {
    require_once ABSPATH . '/verify.php';
    exit; // Stop further execution after handling verification
}

/**
 * Shortcode for displaying the Mass Mailer subscription form.
 * Usage: [mass_mailer_form list_id="1" title="Join Our Newsletter" show_name_fields="true"]
 *
 * @param array $atts Shortcode attributes.
 * @return string The HTML for the subscription form.
 */
function mass_mailer_subscription_form_shortcode($atts) {
    // Default attributes
    $atts = shortcode_atts([
        'list_id' => null,
        'title' => 'Subscribe to Our Newsletter',
        'description' => 'Stay updated with our latest news and offers!',
        'show_name_fields' => 'true', // 'true' or 'false'
    ], $atts, 'mass_mailer_form');

    // Convert boolean string to actual boolean
    $atts['show_name_fields'] = filter_var($atts['show_name_fields'], FILTER_VALIDATE_BOOLEAN);

    // Start output buffering to capture HTML
    ob_start();
    // Include the form builder view file
    require ABSPATH . 'views/form-builder.php';
    return ob_get_clean();
}
add_shortcode('mass_mailer_form', 'mass_mailer_subscription_form_shortcode');


/**
 * Cron function to process the email queue.
 * This should be scheduled via WordPress cron or system cron.
 */
function mass_mailer_process_queue_cron_wp() {
    require_once ABSPATH . 'includes/queue-manager.php';
    $queue_manager = new MassMailerQueueManager();
    $queue_manager->processQueueBatch();
    error_log('MassMailer: Queue processing cron job executed.');
}
add_action('mass_mailer_daily_cron_hook', 'mass_mailer_process_queue_cron_wp');
// Schedule the cron job if it's not already scheduled
if (!wp_next_scheduled('mass_mailer_daily_cron_hook')) {
    wp_schedule_event(time(), 'daily', 'mass_mailer_daily_cron_hook');
}

/**
 * Cron function to process A/B tests.
 * This should be scheduled via WordPress cron or system cron.
 */
function mass_mailer_process_ab_tests_cron_wp() {
    require_once ABSPATH . 'includes/ab-test-manager.php';
    $ab_test_manager = new MassMailerABTestManager();
    $ab_test_manager->processPendingABTests();
    error_log('MassMailer: A/B test processing cron job executed.');
}
add_action('mass_mailer_daily_cron_hook', 'mass_mailer_process_ab_tests_cron_wp');


/**
 * Cron function to process bounces.
 * This should be scheduled via WordPress cron or system cron.
 */
function mass_mailer_process_bounces_cron_wp() {
    require_once ABSPATH . 'includes/bounce-handler.php';
    require_once ABSPATH . 'includes/settings-manager.php'; // Required to check if bounce handling is enabled
    $settings_manager = new MassMailerSettingsManager();
    if ($settings_manager->getSetting('bounce_handling_enabled') === '1') {
        $bounce_handler = new MassMailerBounceHandler();
        $bounce_handler->processBouncesViaIMAP();
        error_log('MassMailer: Bounce processing cron job executed.');
    } else {
        error_log('MassMailer: Bounce processing skipped, disabled in settings.');
    }
}
add_action('mass_mailer_daily_cron_hook', 'mass_mailer_process_bounces_cron_wp');

// Add admin menu items
function mass_mailer_admin_menu() {
    add_menu_page(
        'Mass Mailer',
        'Mass Mailer',
        'manage_options', // Capability required to access
        'mass-mailer-dashboard',
        'mass_mailer_dashboard_page',
        'dashicons-email', // Icon
        25 // Position in menu
    );

    add_submenu_page(
        'mass-mailer-dashboard',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'mass-mailer-dashboard',
        'mass_mailer_dashboard_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Lists',
        'Lists',
        'manage_options',
        'mass-mailer-lists',
        'mass_mailer_lists_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Subscribers',
        'Subscribers',
        'manage_options',
        'mass-mailer-subscribers',
        'mass_mailer_subscribers_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Segments',
        'Segments',
        'manage_options',
        'mass-mailer-segments',
        'mass_mailer_segments_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Templates',
        'Templates',
        'manage_options',
        'mass-mailer-templates',
        'mass_mailer_templates_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Campaigns',
        'Campaigns',
        'manage_options',
        'mass-mailer-campaigns',
        'mass_mailer_campaigns_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'A/B Tests',
        'A/B Tests',
        'manage_options',
        'mass-mailer-ab-tests',
        'mass_mailer_ab_tests_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Automations',
        'Automations',
        'manage_options',
        'mass-mailer-automations',
        'mass_mailer_automations_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Reports',
        'Reports',
        'manage_options',
        'mass-mailer-reports',
        'mass_mailer_reports_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Analytics',
        'Analytics',
        'manage_options',
        'mass-mailer-analytics',
        'mass_mailer_analytics_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Bounce Log',
        'Bounce Log',
        'manage_options',
        'mass-mailer-bounces',
        'mass_mailer_bounces_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Privacy & GDPR',
        'Privacy & GDPR',
        'manage_options',
        'mass-mailer-privacy',
        'mass_mailer_privacy_page'
    );
    add_submenu_page(
        'mass-mailer-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'mass-mailer-settings',
        'mass_mailer_settings_page'
    );
}
add_action('admin_menu', 'mass_mailer_admin_menu');

// Admin page callbacks - these functions will include the respective admin files
function mass_mailer_dashboard_page() { include ABSPATH . 'admin/dashboard.php'; }
function mass_mailer_lists_page() { include ABSPATH . 'admin/lists.php'; }
function mass_mailer_subscribers_page() { include ABSPATH . 'admin/subscribers.php'; }
function mass_mailer_segments_page() { include ABSPATH . 'admin/segments.php'; }
function mass_mailer_templates_page() { include ABSPATH . 'admin/templates.php'; }
function mass_mailer_campaigns_page() { include ABSPATH . 'admin/campaigns.php'; }
function mass_mailer_ab_tests_page() { include ABSPATH . 'admin/ab-tests.php'; }
function mass_mailer_automations_page() { include ABSPATH . 'admin/automations.php'; }
function mass_mailer_reports_page() { include ABSPATH . 'admin/reports.php'; }
function mass_mailer_analytics_page() { include ABSPATH . 'admin/analytics.php'; }
function mass_mailer_bounces_page() { include ABSPATH . 'admin/bounces.php'; }
function mass_mailer_privacy_page() { include ABSPATH . 'admin/privacy.php'; }
function mass_mailer_settings_page() { include ABSPATH . 'admin/settings.php'; }

// Enqueue admin styles (assuming style.css is in mass-mailer/css/)
function mass_mailer_enqueue_admin_styles($hook) {
    // Only load our styles on our plugin pages
    if (strpos($hook, 'mass-mailer') !== false) {
        wp_enqueue_style('mass-mailer-admin-style', plugins_url('css/style.css', __FILE__), array(), '1.0.0');
    }
}
add_action('admin_enqueue_scripts', 'mass_mailer_enqueue_admin_styles');

// Enqueue frontend scripts (for the subscription form)
function mass_mailer_enqueue_frontend_scripts() {
    // Only enqueue if the shortcode is present or on specific pages
    // For simplicity, we'll enqueue globally for now, but you might want conditional loading.
    wp_enqueue_script('mass-mailer-form-handler', plugins_url('assets/js/form-handler.js', __FILE__), array('jquery'), '1.0.0', true);
}
add_action('wp_enqueue_scripts', 'mass_mailer_enqueue_frontend_scripts');

// Add a deactivation hook to clean up scheduled cron events
function mass_mailer_deactivate() {
    wp_clear_scheduled_hook('mass_mailer_daily_cron_hook');
    error_log('MassMailer: Cron events cleared on deactivation.');
}
register_deactivation_hook(__FILE__, 'mass_mailer_deactivate');

?>
