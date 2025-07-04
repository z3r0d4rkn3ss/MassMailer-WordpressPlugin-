<?php
/**
 * Mass Mailer Email Verification Endpoint
 *
 * This file handles the double opt-in verification process.
 * Subscribers click a link in their email containing a unique token,
 * which this script uses to update their status to 'subscribed'.
 *
 * @package Mass_Mailer
 * @subpackage Public
 */

// Define ABSPATH for consistent pathing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include core configuration and necessary managers
require_once ABSPATH . 'config.php';
require_once ABSPATH . 'includes/db.php';
require_once ABSPATH . 'includes/subscriber-manager.php';

$subscriber_manager = new MassMailerSubscriberManager();

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';

if (empty($token)) {
    $message = 'Invalid verification link. No token provided.';
    $message_type = 'error';
} else {
    if ($subscriber_manager->verifySubscriber($token)) {
        $message = 'Your email address has been successfully verified! Thank you for confirming your subscription.';
        $message_type = 'success';
    } else {
        $message = 'Email verification failed. The token might be invalid or expired, or your email is already verified.';
        $message_type = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Verification</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .container { max-width: 600px; margin: 0 auto; background-color: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
        h1 { color: #007cba; margin-bottom: 20px; }
        .message { padding: 15px 20px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; font-size: 1.1em; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        p { line-height: 1.6; }
        a { color: #007cba; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Subscription Verification</h1>
        <div class="message <?php echo $message_type; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <p>You can close this window now or return to our <a href="/">homepage</a>.</p>
    </div>
</body>
</html>
