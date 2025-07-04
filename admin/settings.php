<?php
/**
 * Mass Mailer Admin Settings Page - API Key Management Update
 *
 * This file updates the settings UI to include configuration for API keys.
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

// Ensure managers are loaded
if (!class_exists('MassMailerSettingsManager')) {
    require_once dirname(__FILE__) . '/../includes/settings-manager.php';
}
// NEW: Include API Manager
if (!class_exists('MassMailerAPIManager')) {
    require_once dirname(__FILE__) . '/../includes/api-manager.php';
}

$settings_manager = new MassMailerSettingsManager();
$api_manager = new MassMailerAPIManager(); // Instantiate API Manager

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

    // Mailer Settings
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

    // Bounce Handling Settings
    if (isset($_POST['bounce_handling_enabled'])) {
        if ($settings_manager->setSetting('bounce_handling_enabled', $_POST['bounce_handling_enabled'] === '1' ? '1' : '0')) $updated_count++;
    }
    if (isset($_POST['bounce_imap_host'])) {
        if ($settings_manager->setSetting('bounce_imap_host', trim($_POST['bounce_imap_host']))) $updated_count++;
    }
    if (isset($_POST['bounce_imap_port'])) {
        if ($settings_manager->setSetting('bounce_imap_port', intval($_POST['bounce_imap_port']))) $updated_count++;
    }
    if (isset($_POST['bounce_imap_username'])) {
        if ($settings_manager->setSetting('bounce_imap_username', trim($_POST['bounce_imap_username']))) $updated_count++;
    }
    if (isset($_POST['bounce_imap_password'])) {
        if (!empty($_POST['bounce_imap_password'])) {
            if ($settings_manager->setSetting('bounce_imap_password', $_POST['bounce_imap_password'])) $updated_count++;
        }
    }
    if (isset($_POST['bounce_imap_flags'])) {
        if ($settings_manager->setSetting('bounce_imap_flags', trim($_POST['bounce_imap_flags']))) $updated_count++;
    }


    if ($updated_count > 0) {
        $message = 'Settings saved successfully!';
        $message_type = 'success';
    } else {
        $message = 'No settings were updated or an error occurred.';
        $message_type = 'error';
    }
}

// Handle API Key actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    $current_user = $auth->getCurrentUser();
    $user_id = $current_user['user_id'] ?? null;

    if (!$user_id) {
        $message = 'Error: User not logged in or user ID not found.';
        $message_type = 'error';
    } else {
        switch ($_POST['api_action']) {
            case 'generate_api_key':
                $description = isset($_POST['api_key_description']) ? trim($_POST['api_key_description']) : '';
                $new_key = $api_manager->createApiKey($user_id, $description);
                if ($new_key) {
                    $message = 'New API Key generated: <code>' . htmlspecialchars($new_key) . '</code>. Please copy it now as it will not be shown again!';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to generate API Key.';
                    $message_type = 'error';
                }
                break;

            case 'update_api_key':
                $api_key_id = isset($_POST['api_key_id']) ? intval($_POST['api_key_id']) : 0;
                $description = isset($_POST['edit_api_key_description']) ? trim($_POST['edit_api_key_description']) : '';
                $status = isset($_POST['edit_api_key_status']) ? trim($_POST['edit_api_key_status']) : '';
                if ($api_key_id > 0 && !empty($description) && !empty($status)) {
                    if ($api_manager->updateApiKey($api_key_id, $description, $status)) {
                        $message = 'API Key updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update API Key.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid parameters for API Key update.';
                    $message_type = 'error';
                }
                break;

            case 'delete_api_key':
                $api_key_id = isset($_POST['api_key_id']) ? intval($_POST['api_key_id']) : 0;
                if ($api_key_id > 0) {
                    if ($api_manager->deleteApiKey($api_key_id)) {
                        $message = 'API Key deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete API Key.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid API Key ID for deletion.';
                    $message_type = 'error';
                }
                break;
        }
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

// Bounce Handling Defaults
$bounce_handling_enabled = $current_settings['bounce_handling_enabled'] ?? '0';
$bounce_imap_host = $current_settings['bounce_imap_host'] ?? '';
$bounce_imap_port = $current_settings['bounce_imap_port'] ?? 993;
$bounce_imap_username = $current_settings['bounce_imap_username'] ?? '';
$bounce_imap_password = ''; // Never pre-fill password fields for security
$bounce_imap_flags = $current_settings['bounce_imap_flags'] ?? '/imap/ssl/novalidate-cert';

// Fetch all API keys for display
$all_api_keys = $api_manager->getAllApiKeys();

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
        h2 { color: #007cba; margin-top: 30px; margin-bottom: 20px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
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

        .api-key-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .api-key-table th, .api-key-table td { border: 1px solid #eee; padding: 10px; text-align: left; }
        .api-key-table th { background-color: #f2f2f2; font-weight: 600; color: #555; }
        .api-key-table tr:nth-child(even) { background-color: #f9f9f9; }
        .api-key-table code { background-color: #eef; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        .action-buttons button { margin-right: 5px; padding: 6px 12px; font-size: 0.85em; border-radius: 4px; }
        .action-buttons .edit { background-color: #ffc107; color: #333; }
        .action-buttons .edit:hover { background-color: #e0a800; }
        .action-buttons .delete { background-color: #dc3545; color: #fff; }
        .action-buttons .delete:hover { background-color: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mass Mailer Settings</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
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

            <section>
                <h2>Bounce & Complaint Handling</h2>
                <div class="form-group">
                    <label for="bounce_handling_enabled">Enable Bounce Handling:</label>
                    <select id="bounce_handling_enabled" name="bounce_handling_enabled" onchange="toggleBounceSettings(this.value)">
                        <option value="0" <?php echo ($bounce_handling_enabled === '0') ? 'selected' : ''; ?>>No</option>
                        <option value="1" <?php echo ($bounce_handling_enabled === '1') ? 'selected' : ''; ?>>Yes</option>
                    </select>
                    <p class="info-text">Enables automatic processing of bounced emails and spam complaints.</p>
                </div>
                <div id="bounce_settings_fields" style="<?php echo ($bounce_handling_enabled === '1') ? 'display:block;' : 'display:none;'; ?>">
                    <p class="info-text">Configure IMAP access to a dedicated mailbox where bounces/FBLs are sent.</p>
                    <div class="form-group">
                        <label for="bounce_imap_host">IMAP Host:</label>
                        <input type="text" id="bounce_imap_host" name="bounce_imap_host" value="<?php echo htmlspecialchars($bounce_imap_host); ?>">
                        <p class="info-text">e.g., imap.yourmail.com</p>
                    </div>
                    <div class="form-group">
                        <label for="bounce_imap_port">IMAP Port:</label>
                        <input type="number" id="bounce_imap_port" name="bounce_imap_port" value="<?php echo htmlspecialchars($bounce_imap_port); ?>">
                        <p class="info-text">Common ports: 993 (IMAPS), 143 (IMAP)</p>
                    </div>
                    <div class="form-group">
                        <label for="bounce_imap_username">IMAP Username:</label>
                        <input type="text" id="bounce_imap_username" name="bounce_imap_username" value="<?php echo htmlspecialchars($bounce_imap_username); ?>">
                    </div>
                    <div class="form-group">
                        <label for="bounce_imap_password">IMAP Password:</label>
                        <input type="password" id="bounce_imap_password" name="bounce_imap_password" placeholder="Leave blank to keep current password">
                        <p class="info-text">Only enter if you want to change the password.</p>
                    </div>
                    <div class="form-group">
                        <label for="bounce_imap_flags">IMAP Flags:</label>
                        <input type="text" id="bounce_imap_flags" name="bounce_imap_flags" value="<?php echo htmlspecialchars($bounce_imap_flags); ?>">
                        <p class="info-text">e.g., <code>/imap/ssl/novalidate-cert</code> or <code>/pop3/ssl</code>. Consult your mail provider.</p>
                    </div>
                </div>
            </section>

            <button type="submit" name="save_settings">Save General Settings</button>
        </form>

        <section>
            <h2>API Key Management</h2>
            <p class="info-text">Generate and manage API keys for external applications to interact with your Mass Mailer.</p>

            <h3>Generate New API Key</h3>
            <form method="POST" action="">
                <input type="hidden" name="api_action" value="generate_api_key">
                <div class="form-group">
                    <label for="api_key_description">Description:</label>
                    <input type="text" id="api_key_description" name="api_key_description" placeholder="e.g., CRM Integration, Website Form" required>
                </div>
                <button type="submit">Generate API Key</button>
            </form>

            <h3>Existing API Keys</h3>
            <?php if (empty($all_api_keys)): ?>
                <p>No API keys found. Generate one above!</p>
            <?php else: ?>
                <table class="api-key-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>API Key</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Status</th>
                            <th>Last Used</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_api_keys as $key): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($key['api_key_id']); ?></td>
                                <td><code><?php echo htmlspecialchars($key['api_key']); ?></code></td>
                                <td><?php echo htmlspecialchars($key['description']); ?></td>
                                <td><?php echo htmlspecialchars($key['username'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($key['status'])); ?></td>
                                <td><?php echo $key['last_used_at'] ? htmlspecialchars($key['last_used_at']) : 'Never'; ?></td>
                                <td class="action-buttons">
                                    <button class="edit" onclick="showEditApiKeyForm(
                                        <?php echo htmlspecialchars($key['api_key_id']); ?>,
                                        '<?php echo addslashes(htmlspecialchars($key['description'])); ?>',
                                        '<?php echo htmlspecialchars($key['status']); ?>'
                                    )">Edit</button>
                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this API Key? This action cannot be undone.');">
                                        <input type="hidden" name="api_action" value="delete_api_key">
                                        <input type="hidden" name="api_key_id" value="<?php echo htmlspecialchars($key['api_key_id']); ?>">
                                        <button type="submit" class="delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Edit API Key Modal/Form (hidden by default) -->
                <div id="editApiKeyModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                    <div style="background:#fff; padding:30px; border-radius:8px; width:500px; max-height: 90vh; overflow-y: auto; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                        <h3>Edit API Key</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="api_action" value="update_api_key">
                            <input type="hidden" id="edit_api_key_id" name="api_key_id">
                            <div class="form-group">
                                <label for="edit_api_key_description">Description:</label>
                                <input type="text" id="edit_api_key_description" name="edit_api_key_description" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_api_key_status">Status:</label>
                                <select id="edit_api_key_status" name="edit_api_key_status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <button type="submit">Update API Key</button>
                            <button type="button" onclick="document.getElementById('editApiKeyModal').style.display='none';">Cancel</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mailerTypeSelect = document.getElementById('mailer_type');
            const smtpSettingsDiv = document.getElementById('smtp_settings');
            const bounceHandlingEnabledSelect = document.getElementById('bounce_handling_enabled');
            const bounceSettingsFieldsDiv = document.getElementById('bounce_settings_fields');

            function toggleSmtpSettings() {
                if (mailerTypeSelect.value === 'smtp') {
                    smtpSettingsDiv.style.display = 'block';
                } else {
                    smtpSettingsDiv.style.display = 'none';
                }
            }

            function toggleBounceSettings(value) {
                if (value === '1') {
                    bounceSettingsFieldsDiv.style.display = 'block';
                } else {
                    bounceSettingsFieldsDiv.style.display = 'none';
                }
            }

            mailerTypeSelect.addEventListener('change', toggleSmtpSettings);
            bounceHandlingEnabledSelect.addEventListener('change', () => toggleBounceSettings(bounceHandlingEnabledSelect.value));


            // Initial calls to set correct display on page load
            toggleSmtpSettings();
            toggleBounceSettings(bounceHandlingEnabledSelect.value);
        });

        function showEditApiKeyForm(id, description, status) {
            document.getElementById('edit_api_key_id').value = id;
            document.getElementById('edit_api_key_description').value = description;
            document.getElementById('edit_api_key_status').value = status;
            document.getElementById('editApiKeyModal').style.display = 'flex';
        }
    </script>
</body>
</html>
