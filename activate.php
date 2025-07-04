<?php
/**
 * Mass Mailer Database Activation Helper
 *
 * This script should be run ONCE after deploying the Mass Mailer files
 * and configuring config.php. It calls the mass_mailer_activate() function
 * to create and update all necessary database tables, including those for
 * Segmentation, A/B Testing, API Management, and GDPR features.
 *
 * Usage: Navigate to this file in your browser (e.g., http://yourdomain.com/mass-mailer/activate.php)
 * or run it from the command line: php activate.php
 *
 * @package Mass_Mailer
 */

// Define ABSPATH for consistent pathing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include the main mass-mailer.php file which contains the activation function
require_once ABSPATH . 'mass-mailer.php';

echo "<h1>Mass Mailer Database Activation</h1>";
echo "<p>Attempting to create/update database tables...</p>";

try {
    mass_mailer_activate();
    echo "<p style='color: green; font-weight: bold;'>Database activation completed successfully!</p>";
    echo "<p>You can now proceed to the <a href='admin/login.php'>admin login page</a>.</p>";
    echo "<p>Remember to delete or secure this file after successful activation.</p>";
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>Database activation failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your <code>config.php</code> settings and database permissions.</p>";
}

?>
