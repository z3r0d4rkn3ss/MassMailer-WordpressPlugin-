<?php
/**
 * Mass Mailer Main Plugin File
 *
 * This file serves as the main entry point for the Mass Mailer plugin.
 * It handles plugin activation, deactivation, and integrates core functionalities
 * like frontend form display and submission handling.
 *
 * IMPORTANT: This code snippet provides the *additions* to your existing
 * mass-mailer.php file. You will need to integrate these sections.
 *
 * @package Mass_Mailer
 */

// Ensure no direct access to the file
if (!defined('ABSPATH')) { // If this were a WordPress plugin, this would be defined. Adjust as needed for your framework.
    // For a standalone PHP application, you might use a different check or structure.
    // For now, we'll just exit if accessed directly without a proper entry point.
    die('Direct access not allowed.');
}

// --- Configuration and Core Includes ---
// Include configuration
require_once dirname(__FILE__) . '/config.php';

// Include database class
require_once dirname(__FILE__) . '/includes/db.php';

// Include list and subscriber managers
require_once dirname(__FILE__) . '/includes/list-manager.php';
require_once dirname(__FILE__) . '/includes/subscriber-manager.php';

// --- Plugin Activation and Deactivation Hooks (Conceptual) ---
// In a real plugin/application, you'd have activation/deactivation logic here.
// For example, creating database tables on activation.
function mass_mailer_activate() {
    // Get DB instance
    $db = MassMailerDB::getInstance();

    // SQL to create tables (from your db-schema.sql)
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
        error_log('Mass Mailer: Database tables created/checked successfully.');
    } catch (PDOException $e) {
        error_log('Mass Mailer: Database table creation failed: ' . $e->getMessage());
        // In a real application, you might want to prevent plugin activation or show an admin notice.
    }
}
// Register activation hook (example for WordPress, adapt for your framework)
// register_activation_hook(__FILE__, 'mass_mailer_activate');

function mass_mailer_deactivate() {
    // Cleanup tasks on deactivation (e.g., remove temporary files)
    // For now, we won't delete tables on deactivation to preserve data.
}
// register_deactivation_hook(__FILE__, 'mass_mailer_deactivate');


// --- Frontend Subscription Form Integration ---

/**
 * Renders the Mass Mailer subscription form.
 * This function can be called directly or via a shortcode/function in your theme/application.
 *
 * @param array $atts Attributes for the form (e.g., 'list_id' to pre-select a list).
 * @return string The HTML output of the subscription form.
 */
function mass_mailer_subscription_form($atts = []) {
    // Default attributes
    $atts = array_merge([
        'list_id' => null, // Optional: specify a list ID to subscribe to
        'title' => 'Subscribe to Our Newsletter',
        'description' => 'Stay updated with our latest news and offers!',
        'show_name_fields' => true, // Option to show/hide name fields
    ], $atts);

    // Start output buffering to capture HTML
    ob_start();

    // Load the form-builder view
    require dirname(__FILE__) . '/views/form-builder.php';

    // Return captured HTML
    return ob_get_clean();
}

// Register as a shortcode (if using WordPress)
// add_shortcode('mass_mailer_form', 'mass_mailer_subscription_form');

// --- Handle Frontend Form Submission (AJAX Endpoint) ---

/**
 * Handles the AJAX submission for the frontend subscription form.
 * This function should be hooked to an AJAX action.
 */
function mass_mailer_handle_form_submission() {
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    // Check if it's an AJAX request and form data is present
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

            // Attempt to add or update the subscriber
            $subscriber_id = $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'pending'); // Set to pending initially

            if ($subscriber_id) {
                // If a specific list ID is provided, try to add the subscriber to it
                if ($list_id) {
                    $list = $list_manager->getList($list_id);
                    if ($list) {
                        $added_to_list = $subscriber_manager->addSubscriberToList($subscriber_id, $list_id, 'active'); // Set to active for the list
                        if ($added_to_list) {
                            $response['success'] = true;
                            $response['message'] = 'Thank you for subscribing!';
                            // Optionally update main subscriber status to 'subscribed' if added to at least one list
                            $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'subscribed');
                        } else {
                            $response['message'] = 'Subscription failed for the specified list. Please try again.';
                        }
                    } else {
                        $response['message'] = 'The specified list does not exist.';
                    }
                } else {
                    // No specific list, just add/update subscriber without list association
                    $response['success'] = true;
                    $response['message'] = 'Thank you for subscribing! (No specific list selected)';
                    // Update main subscriber status to 'subscribed'
                    $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'subscribed');
                }
            } else {
                $response['message'] = 'Failed to process your subscription. Please try again later.';
            }
        }
    } else {
        $response['message'] = 'Invalid form submission.';
    }

    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit; // Terminate script execution after AJAX response
}

// Register the AJAX action (example for WordPress, adapt for your framework)
// For logged-in users: add_action('wp_ajax_mass_mailer_subscribe', 'mass_mailer_handle_form_submission');
// For non-logged-in users: add_action('wp_ajax_nopriv_mass_mailer_subscribe', 'mass_mailer_handle_form_submission');

// For a standalone PHP application, you might route requests to this function
// based on a specific URL endpoint (e.g., /api/subscribe)
// Example simple routing (for demonstration, not robust for production):
// if (isset($_GET['action']) && $_GET['action'] === 'mass_mailer_subscribe') {
//     mass_mailer_handle_form_submission();
// }

?>
