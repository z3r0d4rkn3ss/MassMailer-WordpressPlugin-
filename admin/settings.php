<?php
/**
 * Mass Mailer Admin Settings Page
 *
 * This file provides the user interface for configuring global settings
 * for the Mass Mailer plugin.
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
// Optional: Restrict access to 'admin' role only
if (!$auth->hasRole('admin')) {
    die('Access Denied. You must be an administrator to view this page.');
}

// Ensure settings manager is loaded
if (!class_exists('MassMailerSettingsManager')) {
    require_once dirname(__FILE__) . '/../includes/settings-manager.php';
}

$settings_manager = new MassMailerSettingsManager();
$message = '';
$message_type = '';

// Handle form submission for saving settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $updated_count = 0;

    // General Settings
    if (isset($_POST['default_from_name'])) {
        if ($settings_manager->setSetting('default_from_name', trim($_POST['default_from_name']))) $updated_count++;
    }
    if (isset($_POST['default_from_email'])) {
        if ($settings_manager->setSetting('default_from_email', trim($_POST['default_from_email']))) $updated_count++;
    }
    if (isset($_POST['reply_to_email'])) {
        if ($settings_manager->setSetting('reply_to_email', trim($_POST['reply_to_email']))) $updated_count++;
    }

    // SMTP Settings
    if (isset($_POST['mailer_type'])) {
        if ($settings_manager->setSetting('mailer_type', trim($_POST['mailer_type']))) $updated_count++;
    }
    if (isset($_POST['smtp_host'])) {
        if ($settings_manager->setSetting('smtp_host', trim($_POST['smtp_host']))) $updated_count++;
    }
    if (isset($_POST['smtp_port'])) {
        if ($settings_manager->setSetting('smtp_port', intval($_POST['smtp_port']))) $updated_count++;
    }
    if (isset($_POST['smtp_username'])) {
        if ($settings_manager->setSetting('smtp_username', trim($_POST['smtp_username']))) $updated_count++;
    }
    if (isset($_POST['smtp_password'])) {
        // Only update password if it's not empty (allow leaving blank to keep current)
        if (!empty($_POST['smtp_password'])) {
            if ($settings_manager->setSetting('smtp_password', $_POST['smtp_password'])) $updated_count++;
        }
    }
    if (isset($_POST['smtp_encryption'])) {
        if ($settings_manager->setSetting('smtp_encryption', trim($_POST['smtp_encryption']))) $updated_count++;
    }

    // Tracking Settings
    if (isset($_POST['tracking_base_url'])) {
        if ($settings_manager->setSetting('tracking_base_url', trim($_POST['tracking_base_url']))) $updated_count++;
    }


    if ($updated_count > 0) {
        $message = 'Settings saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'No settings were updated or an error occurred.';
        $message_type = 'error';
    }
}

// Retrieve current settings
$current_settings = $settings_manager->getAllSettings();

// Default values for form fields if not set in DB
$default_from_name = $current_settings['default_from_name'] ?? 'Mass Mailer';
$default_from_email = $current_settings['default_from_email'] ?? 'noreply@example.com';
$reply_to_email = $current_settings['reply_to_email'] ?? '';

$mailer_type = $current_settings['mailer_type'] ?? 'php_mail';
$smtp_host = $current_settings['smtp_host'] ?? '';
$smtp_port = $current_settings['smtp_port'] ?? 587;
$smtp_username = $current_settings['smtp_username'] ?? '';
$smtp_password = ''; // Never pre-fill password fields for security
$smtp_encryption = $current_settings['smtp_encryption'] ?? 'tls';

$tracking_base_url = $current_settings['tracking_base_url'] ?? 'http://localhost/mass-mailer/mass-mailer.php'; // Default fallback

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Settings</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        form section { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee; }
        form section h2 { color: #007cba; margin-top: 0; padding-bottom: 10px; border-bottom: 1px dashed #ddd; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box; /* Include padding in width */
        }
        button[type="submit"] { background-color: #007cba; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 1.1em; transition: background-color 0.2s; }
        button[type="submit"]:hover { background-color: #005f93; }
        .info-text { font-size: 0.85em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mass Mailer Settings</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <section>
                <h2>General Settings</h2>
                <div class="form-group">
                    <label for="default_from_name">Default From Name:</label>
                    <input type="text" id="default_from_name" name="default_from_name" value="<?php echo htmlspecialchars($default_from_name); ?>">
                    <p class="info-text">The name emails will appear to be sent from.</p>
                </div>
                <div class="form-group">
                    <label for="default_from_email">Default From Email:</label>
                    <input type="email" id="default_from_email" name="default_from_email" value="<?php echo htmlspecialchars($default_from_email); ?>">
                    <p class="info-text">The email address emails will appear to be sent from.</p>
                </div>
                <div class="form-group">
                    <label for="reply_to_email">Reply-To Email (Optional):</label>
                    <input type="email" id="reply_to_email" name="reply_to_email" value="<?php echo htmlspecialchars($reply_to_email); ?>">
                    <p class="info-text">Subscribers will reply to this address.</p>
                </div>
            </section>

            <section>
                <h2>Mailer Settings</h2>
                <div class="form-group">
                    <label for="mailer_type">Mailer Type:</label>
                    <select id="mailer_type" name="mailer_type">
                        <option value="php_mail" <?php echo ($mailer_type === 'php_mail') ? 'selected' : ''; ?>>PHP mail() function</option>
                        <option value="smtp" <?php echo ($mailer_type === 'smtp') ? 'selected' : ''; ?>>SMTP</option>
                        <!-- Add options for API services like SendGrid, Mailgun etc. later -->
                    </select>
                    <p class="info-text">Choose how emails are sent. SMTP is recommended for reliability.</p>
                </div>
                <div id="smtp_settings" style="<?php echo ($mailer_type === 'smtp') ? 'display:block;' : 'display:none;'; ?>">
                    <div class="form-group">
                        <label for="smtp_host">SMTP Host:</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_host); ?>">
                    </div>
                    <div class="form-group">
                        <label for="smtp_port">SMTP Port:</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_port); ?>">
                    </div>
                    <div class="form-group">
                        <label for="smtp_username">SMTP Username:</label>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($smtp_username); ?>">
                    </div>
                    <div class="form-group">
                        <label for="smtp_password">SMTP Password:</label>
                        <input type="password" id="smtp_password" name="smtp_password" placeholder="Leave blank to keep current password">
                        <p class="info-text">Only enter if you want to change the password.</p>
                    </div>
                    <div class="form-group">
                        <label for="smtp_encryption">SMTP Encryption:</label>
                        <select id="smtp_encryption" name="smtp_encryption">
                            <option value="none" <?php echo ($smtp_encryption === 'none') ? 'selected' : ''; ?>>None</option>
                            <option value="ssl" <?php echo ($smtp_encryption === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                            <option value="tls" <?php echo ($smtp_encryption === 'tls') ? 'selected' : ''; ?>>TLS</option>
                        </select>
                    </div>
                </div>
            </section>

            <section>
                <h2>Tracking Settings</h2>
                <div class="form-group">
                    <label for="tracking_base_url">Tracking Base URL:</label>
                    <input type="text" id="tracking_base_url" name="tracking_base_url" value="<?php echo htmlspecialchars($tracking_base_url); ?>">
                    <p class="info-text">The full URL to your <code>mass-mailer.php</code> file. Used for open and click tracking. E.g., <code>http://yourdomain.com/mass-mailer/mass-mailer.php</code></p>
                </div>
            </section>

            <button type="submit" name="save_settings">Save Settings</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mailerTypeSelect = document.getElementById('mailer_type');
            const smtpSettingsDiv = document.getElementById('smtp_settings');

            function toggleSmtpSettings() {
                if (mailerTypeSelect.value === 'smtp') {
                    smtpSettingsDiv.style.display = 'block';
                } else {
                    smtpSettingsDiv.style.display = 'none';
                }
            }

            mailerTypeSelect.addEventListener('change', toggleSmtpSettings);

            // Initial call to set correct display on page load
            toggleSmtpSettings();
        });
    </script>
</body>
</html>
