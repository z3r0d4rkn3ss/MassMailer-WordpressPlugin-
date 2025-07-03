<?php
/**
 * Mass Mailer Configuration File
 *
 * This file contains essential configuration settings for the Mass Mailer plugin,
 * primarily for database connection.
 *
 * @package Mass_Mailer
 * @subpackage Config
 */

// Define database connection constants
// IMPORTANT: Replace these with your actual database credentials.
// For security, in a production environment, consider loading these
// from environment variables or a more secure configuration method.
define('DB_HOST', 'localhost'); // Your database host (e.g., 'localhost', '127.0.0.1')
define('DB_NAME', 'mass_mailer_db'); // Your database name
define('DB_USER', 'db_user'); // Your database username
define('DB_PASS', 'db_password'); // Your database password

// Define table prefixes (useful if you have multiple plugins/apps in one DB)
define('MM_TABLE_PREFIX', 'mm_');

// Define other general constants if needed later
// define('MM_DEBUG_MODE', true);

?>
