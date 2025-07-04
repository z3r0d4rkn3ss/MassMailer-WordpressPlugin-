<?php
/**
 * Mass Mailer Admin Bounce Log Page
 *
 * This file provides the user interface for viewing the log of bounced emails
 * and complaints within the Mass Mailer admin area.
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
// Optional: Restrict access to 'admin' role or specific role for this page
// if (!$auth->hasRole('admin')) {
//     die('Access Denied. You must be an administrator to view this page.');
// }

// Ensure bounce handler is loaded
if (!class_exists('MassMailerBounceHandler')) {
    require_once dirname(__FILE__) . '/../includes/bounce-handler.php';
}

$bounce_handler = new MassMailerBounceHandler();
$message = '';
$message_type = '';

// Handle manual trigger for bounce processing (for testing/debugging)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_bounces_now') {
    $settings_manager = new MassMailerSettingsManager();
    if ($settings_manager->getSetting('bounce_handling_enabled') === '1') {
        if ($bounce_handler->processBouncesViaIMAP()) {
            $message = 'IMAP bounce processing initiated. Check logs for details.';
            $message_type = 'success';
        } else {
            $message = 'IMAP bounce processing failed. Check settings and PHP IMAP extension.';
            $message_type = 'error';
        }
    } else {
        $message = 'Bounce handling is disabled in settings. Enable it to process bounces.';
        $message_type = 'error';
    }
}


// Fetch all bounce log entries
$all_bounces = $bounce_handler->getAllBouncesLog();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Bounce Log</title>
    <!-- Basic Admin CSS -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #eee; padding: 12px; text-align: left; vertical-align: top; }
        table th { background-color: #f2f2f2; font-weight: 600; color: #555; }
        table tr:nth-child(even) { background-color: #f9f9f9; }
        .bounce-type-hard { color: #dc3545; font-weight: bold; }
        .bounce-type-soft { color: #ffc107; }
        .bounce-type-complaint { color: #6f42c1; font-weight: bold; }
        .action-area { margin-bottom: 20px; padding: 15px; background-color: #f9f9f9; border: 1px solid #eee; border-radius: 8px; }
        .action-area button { background-color: #007cba; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; transition: background-color 0.2s; }
        .action-area button:hover { background-color: #005f93; }
        .raw-email-toggle { cursor: pointer; color: #007cba; text-decoration: underline; font-size: 0.9em; }
        .raw-email-content { display: none; background-color: #eee; padding: 10px; margin-top: 10px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; word-break: break-all; font-size: 0.8em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bounce and Complaint Log</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="action-area">
            <h3>Process Bounces Now</h3>
            <p>This will connect to your configured IMAP mailbox and process new bounce/complaint emails. Ensure your IMAP settings are correct in <a href="settings.php">Settings</a>.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="process_bounces_now">
                <button type="submit">Process IMAP Mailbox</button>
            </form>
        </div>

        <h2>Logged Bounces & Complaints</h2>
        <?php if (empty($all_bounces)): ?>
            <p>No bounce or complaint logs found yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Processed At</th>
                        <th>Raw Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_bounces as $bounce): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($bounce['log_id']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($bounce['email']); ?>
                                <?php if ($bounce['subscriber_id']): ?>
                                    <br><small>(Subscriber ID: <?php echo htmlspecialchars($bounce['subscriber_id']); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><span class="bounce-type-<?php echo htmlspecialchars($bounce['bounce_type']); ?>"><?php echo htmlspecialchars(ucfirst($bounce['bounce_type'])); ?></span></td>
                            <td><?php echo htmlspecialchars($bounce['reason']); ?></td>
                            <td><?php echo htmlspecialchars($bounce['processed_at']); ?></td>
                            <td>
                                <?php if (!empty($bounce['raw_email_content'])): ?>
                                    <span class="raw-email-toggle" onclick="toggleRawEmail(this)">View Raw Email</span>
                                    <div class="raw-email-content">
                                        <?php echo nl2br(htmlspecialchars($bounce['raw_email_content'])); ?>
                                    </div>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
                function toggleRawEmail(element) {
                    const rawEmailDiv = element.nextElementSibling;
                    if (rawEmailDiv.style.display === 'none' || rawEmailDiv.style.display === '') {
                        rawEmailDiv.style.display = 'block';
                        element.textContent = 'Hide Raw Email';
                    } else {
                        rawEmailDiv.style.display = 'none';
                        element.textContent = 'View Raw Email';
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
