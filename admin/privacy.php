<?php
/**
 * Mass Mailer Admin Privacy Page
 *
 * This file provides the user interface for GDPR/Privacy related features,
 * specifically for data export (Right to Access) and data erasure (Right to be Forgotten).
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Start session and ensure authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__FILE__) . '/../includes/auth.php';
$auth = new MassMailerAuth();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
// Restrict access to 'admin' role for sensitive privacy operations
if (!$auth->hasRole('admin')) {
    die('Access Denied. You must be an administrator to view this page.');
}

// Ensure core files are loaded
if (!class_exists('MassMailerSubscriberManager')) {
    require_once dirname(__FILE__) . '/../includes/subscriber-manager.php';
}

$subscriber_manager = new MassMailerSubscriberManager();

$message = '';
$message_type = ''; // 'success' or 'error'

// Handle form submissions for data export or deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_address = isset($_POST['email_address']) ? trim($_POST['email_address']) : '';

    if (!filter_var($email_address, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'export_data') {
            $data = $subscriber_manager->exportSubscriberData($email_address);
            if ($data) {
                // Prepare data for download
                $filename = 'mass_mailer_data_export_' . str_replace('@', '_at_', $email_address) . '_' . date('Ymd_His') . '.json';
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo json_encode($data, JSON_PRETTY_PRINT);
                exit; // Stop further script execution to serve the file
            } else {
                $message = 'No data found for this email address, or an error occurred during export.';
                $message_type = 'error';
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_data') {
            if ($subscriber_manager->deleteSubscriberData($email_address)) {
                $message = 'All data for ' . htmlspecialchars($email_address) . ' has been successfully erased.';
                $message_type = 'success';
            } else {
                $message = 'Failed to erase data for ' . htmlspecialchars($email_address) . '. Subscriber might not exist or an error occurred.';
                $message_type = 'error';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Privacy & GDPR</title>
    <!-- Basic Admin CSS -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        h2 { color: #007cba; margin-top: 30px; margin-bottom: 20px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        form { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee; }
        form label { display: block; margin-bottom: 8px; font-weight: 600; }
        form input[type="email"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        form button { background-color: #007cba; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; margin-right: 10px; }
        form button.delete-btn { background-color: #dc3545; }
        form button:hover { background-color: #005f93; }
        form button.delete-btn:hover { background-color: #c82333; }
        .info-text { font-size: 0.85em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Privacy & GDPR Tools</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <p class="info-text">These tools assist in complying with data privacy regulations by allowing you to export or permanently erase subscriber data upon request.</p>

        <h2>Export Subscriber Data (Right to Access)</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="export_data">
            <div class="form-group">
                <label for="export_email">Subscriber Email Address:</label>
                <input type="email" id="export_email" name="email_address" placeholder="Enter subscriber's email" required>
                <p class="info-text">Enter the email address of the subscriber whose data you wish to export. This will download a JSON file containing all their associated data.</p>
            </div>
            <button type="submit">Export Data</button>
        </form>

        <h2>Erase Subscriber Data (Right to be Forgotten)</h2>
        <form method="POST" action="" onsubmit="return confirm('WARNING: This action will permanently delete ALL data associated with this subscriber, including their email, names, list memberships, open/click history, and bounce logs. This action cannot be undone. Are you absolutely sure you want to proceed?');">
            <input type="hidden" name="action" value="delete_data">
            <div class="form-group">
                <label for="delete_email">Subscriber Email Address:</label>
                <input type="email" id="delete_email" name="email_address" placeholder="Enter subscriber's email" required>
                <p class="info-text">Enter the email address of the subscriber whose data you wish to permanently erase from the system.</p>
            </div>
            <button type="submit" class="delete-btn">Erase Data Permanently</button>
        </form>

        <h2>Double Opt-in</h2>
        <p class="info-text">New subscribers signing up via the frontend form will now receive a verification email. Their status will remain 'pending' until they click the confirmation link in that email. This ensures explicit consent.</p>
        <p class="info-text">You can find the frontend subscription form in <code>views/form-builder.php</code>.</p>
        <p class="info-text">The verification endpoint is located at <code>verify.php</code>.</p>

    </div>
</body>
</html>
