<?php
/**
 * Mass Mailer Cron Worker Simulation
 *
 * This script simulates the cron jobs for queue processing, A/B test processing,
 * and bounce handling. In a production environment, these functions would be
 * triggered by actual system cron jobs. For testing, you can run this file
 * manually via browser or command line.
 *
 * Usage: Navigate to this file in your browser (e.g., http://yourdomain.com/mass-mailer/cron-worker.php)
 * or run it from the command line: php cron-worker.php
 *
 * @package Mass_Mailer
 */

// Define ABSPATH for consistent pathing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include the main mass-mailer.php file which contains the cron functions
require_once ABSPATH . 'mass-mailer.php';

echo "<h1>Mass Mailer Cron Worker</h1>";
echo "<p>Running scheduled tasks...</p>";

// Process the email queue
echo "<h2>Processing Email Queue...</h2>";
try {
    mass_mailer_process_queue_cron();
    echo "<p style='color: green;'>Email queue processing initiated.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error processing email queue: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Process A/B tests
echo "<h2>Processing A/B Tests...</h2>";
try {
    mass_mailer_process_ab_tests_cron();
    echo "<p style='color: green;'>A/B test processing initiated.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error processing A/B tests: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Process bounces
echo "<h2>Processing Bounces...</h2>";
try {
    mass_mailer_process_bounces_cron();
    echo "<p style='color: green;'>Bounce processing initiated (check settings for IMAP configuration).</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error processing bounces: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p style='margin-top: 20px; font-weight: bold;'>All scheduled tasks attempted.</p>";
echo "<p>Check your application logs for detailed output.</p>";

?>
