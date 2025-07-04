<?php
/**
 * Mass Mailer Admin Campaigns Page - Phase 4 Updates
 *
 * This file updates the campaign management UI to integrate with the queue system.
 * The "Send Now" action will now populate the queue.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Ensure core files are loaded
if (!class_exists('MassMailerCampaignManager')) {
    require_once dirname(__FILE__) . '/../includes/campaign-manager.php';
    require_once dirname(__FILE__) . '/../includes/list-manager.php';
    require_once dirname(__FILE__) . '/../includes/template-manager.php';
    // NEW: Include Queue Manager
    require_once dirname(__FILE__) . '/../includes/queue-manager.php';
}

$campaign_manager = new MassMailerCampaignManager();
$list_manager = new MassMailerListManager();
$template_manager = new MassMailerTemplateManager();
$queue_manager = new MassMailerQueueManager(); // Instantiate Queue Manager

$message = '';
$message_type = ''; // 'success' or 'error'

// Fetch all lists and templates for dropdowns
$all_lists = $list_manager->getAllLists();
$all_templates = $template_manager->getAllTemplates();

// Handle form submissions for adding/updating/deleting campaigns
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_campaign':
                $campaign_name = isset($_POST['campaign_name']) ? trim($_POST['campaign_name']) : '';
                $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
                $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
                $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
                $send_at = isset($_POST['send_at']) && !empty($_POST['send_at']) ? $_POST['send_at'] : null;

                if (!empty($campaign_name) && $template_id > 0 && $list_id > 0 && !empty($subject)) {
                    $new_campaign_id = $campaign_manager->createCampaign($campaign_name, $template_id, $list_id, $subject, $send_at);
                    if ($new_campaign_id) {
                        // Populate queue immediately if not scheduled for future
                        if (!$send_at || strtotime($send_at) <= time()) {
                            $added_to_queue_count = $queue_manager->populateQueueFromCampaign($new_campaign_id);
                            if ($added_to_queue_count !== false) {
                                $message = 'Campaign "' . htmlspecialchars($campaign_name) . '" created and ' . $added_to_queue_count . ' emails added to queue!';
                                $message_type = 'success';
                                // Update campaign status to 'sending' if immediately sent
                                $campaign_manager->updateCampaignStatus($new_campaign_id, 'sending');
                            } else {
                                $message = 'Campaign "' . htmlspecialchars($campaign_name) . '" created, but failed to add emails to queue.';
                                $message_type = 'error';
                            }
                        } else {
                            $message = 'Campaign "' . htmlspecialchars($campaign_name) . '" created and scheduled for ' . htmlspecialchars($send_at) . '!';
                            $message_type = 'success';
                        }
                    } else {
                        $message = 'Failed to create campaign. A campaign with this name might already exist or invalid IDs.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Campaign name, template, list, and subject cannot be empty.';
                    $message_type = 'error';
                }
                break;

            case 'update_campaign':
                $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
                $campaign_name = isset($_POST['campaign_name']) ? trim($_POST['campaign_name']) : '';
                $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
                $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
                $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'draft';
                $send_at = isset($_POST['send_at']) && !empty($_POST['send_at']) ? $_POST['send_at'] : null;

                if ($campaign_id > 0 && !empty($campaign_name) && $template_id > 0 && $list_id > 0 && !empty($subject) && !empty($status)) {
                    if ($campaign_manager->updateCampaign($campaign_id, $campaign_name, $template_id, $list_id, $subject, $status, $send_at)) {
                        $message = 'Campaign "' . htmlspecialchars($campaign_name) . '" updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update campaign. Check if the ID is valid or if the name is already taken.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid campaign ID, name, template, list, subject, or status for update.';
                    $message_type = 'error';
                }
                break;

            case 'delete_campaign':
                $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
                if ($campaign_id > 0) {
                    if ($campaign_manager->deleteCampaign($campaign_id)) {
                        $message = 'Campaign deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete campaign. Campaign might not exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid campaign ID for deletion.';
                    $message_type = 'error';
                }
                break;
            case 'send_now':
                $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
                if ($campaign_id > 0) {
                    $campaign = $campaign_manager->getCampaign($campaign_id);
                    if ($campaign && ($campaign['status'] === 'draft' || $campaign['status'] === 'paused' || $campaign['status'] === 'scheduled')) {
                        // Mark campaign as sending and populate queue
                        if ($campaign_manager->updateCampaignStatus($campaign_id, 'sending')) {
                            $added_to_queue_count = $queue_manager->populateQueueFromCampaign($campaign_id);
                            if ($added_to_queue_count !== false) {
                                $message = 'Campaign "' . htmlspecialchars($campaign['campaign_name']) . '" marked for sending and ' . $added_to_queue_count . ' emails added to queue!';
                                $message_type = 'success';
                            } else {
                                $message = 'Campaign "' . htmlspecialchars($campaign['campaign_name']) . '" marked for sending, but failed to add emails to queue.';
                                $message_type = 'error';
                                // Revert status if queue population failed
                                $campaign_manager->updateCampaignStatus($campaign_id, 'paused');
                            }
                        } else {
                            $message = 'Failed to update campaign status to "sending".';
                            $message_type = 'error';
                        }
                    } else {
                        $message = 'Campaign cannot be sent. It must be in draft, paused, or scheduled status.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid campaign ID for sending.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch all campaigns for display
$all_campaigns = $campaign_manager->getAllCampaigns();

// --- HTML for Admin Campaigns Page ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Manage Campaigns</title>
    <!-- Basic Admin CSS -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        form { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee; }
        form label { display: block; margin-bottom: 8px; font-weight: 600; }
        form input[type="text"], form input[type="datetime-local"], form select { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
        form button { background-color: #007cba; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
        form button:hover { background-color: #005f93; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #eee; padding: 12px; text-align: left; }
        table th { background-color: #f2f2f2; font-weight: 600; color: #555; }
        table tr:nth-child(even) { background-color: #f9f9f9; }
        .action-buttons button { margin-right: 5px; padding: 6px 12px; font-size: 0.85em; border-radius: 4px; }
        .action-buttons .edit { background-color: #ffc107; color: #333; }
        .action-buttons .edit:hover { background-color: #e0a800; }
        .action-buttons .delete { background-color: #dc3545; color: #fff; }
        .action-buttons .delete:hover { background-color: #c82333; }
        .action-buttons .send-now { background-color: #28a745; color: #fff; }
        .action-buttons .send-now:hover { background-color: #218838; }
        .status-draft { color: #6c757d; }
        .status-scheduled { color: #007bff; }
        .status-sending { color: #ffc107; }
        .status-sent { color: #28a745; }
        .status-paused { color: #6f42c1; }
        .status-cancelled { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Campaigns</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Add New Campaign</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_campaign">
            <div class="form-group">
                <label for="campaign_name">Campaign Name:</label>
                <input type="text" id="campaign_name" name="campaign_name" required>
            </div>
            <div class="form-group">
                <label for="template_id">Email Template:</label>
                <select id="template_id" name="template_id" required>
                    <option value="">Select a Template</option>
                    <?php foreach ($all_templates as $template): ?>
                        <option value="<?php echo htmlspecialchars($template['template_id']); ?>">
                            <?php echo htmlspecialchars($template['template_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="list_id">Target List:</label>
                <select id="list_id" name="list_id" required>
                    <option value="">Select a List</option>
                    <?php foreach ($all_lists as $list): ?>
                        <option value="<?php echo htmlspecialchars($list['list_id']); ?>">
                            <?php echo htmlspecialchars($list['list_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject">Subject Line (Overrides Template Default):</label>
                <input type="text" id="subject" name="subject" required>
            </div>
            <div class="form-group">
                <label for="send_at">Schedule Send Date/Time (Optional):</label>
                <input type="datetime-local" id="send_at" name="send_at">
            </div>
            <button type="submit">Create Campaign</button>
        </form>

        <h2>Existing Campaigns</h2>
        <?php if (empty($all_campaigns)): ?>
            <p>No campaigns found. Create one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Template</th>
                        <th>List</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Sent/Total</th>
                        <th>Scheduled At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_campaigns as $campaign): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($campaign['campaign_id']); ?></td>
                            <td><?php echo htmlspecialchars($campaign['campaign_name']); ?></td>
                            <td><?php echo htmlspecialchars($campaign['template_name']); ?></td>
                            <td><?php echo htmlspecialchars($campaign['list_name']); ?></td>
                            <td><?php echo htmlspecialchars($campaign['subject']); ?></td>
                            <td><span class="status-<?php echo htmlspecialchars($campaign['status']); ?>"><?php echo htmlspecialchars(ucfirst($campaign['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars($campaign['sent_count']) . '/' . htmlspecialchars($campaign['total_recipients']); ?></td>
                            <td><?php echo $campaign['send_at'] ? htmlspecialchars($campaign['send_at']) : 'N/A'; ?></td>
                            <td class="action-buttons">
                                <button class="edit" onclick="showEditForm(
                                    <?php echo $campaign['campaign_id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($campaign['campaign_name'])); ?>',
                                    <?php echo htmlspecialchars($campaign['template_id']); ?>,
                                    <?php echo htmlspecialchars($campaign['list_id']); ?>,
                                    '<?php echo addslashes(htmlspecialchars($campaign['subject'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($campaign['status'])); ?>',
                                    '<?php echo $campaign['send_at'] ? date('Y-m-d\TH:i', strtotime($campaign['send_at'])) : ''; ?>'
                                )">Edit</button>

                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this campaign?');">
                                    <input type="hidden" name="action" value="delete_campaign">
                                    <input type="hidden" name="campaign_id" value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>

                                <?php
                                $can_send = in_array($campaign['status'], ['draft', 'paused', 'scheduled']);
                                if ($can_send): ?>
                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to send this campaign now? This will add all list subscribers to the queue.');">
                                    <input type="hidden" name="action" value="send_now">
                                    <input type="hidden" name="campaign_id" value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>">
                                    <button type="submit" class="send-now">Send Now</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit Campaign Modal/Form (hidden by default) -->
            <div id="editCampaignModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:600px; max-height: 90vh; overflow-y: auto; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit Campaign</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_campaign">
                        <input type="hidden" id="edit_campaign_id" name="campaign_id">
                        <div class="form-group">
                            <label for="edit_campaign_name">Campaign Name:</label>
                            <input type="text" id="edit_campaign_name" name="campaign_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_template_id">Email Template:</label>
                            <select id="edit_template_id" name="template_id" required>
                                <option value="">Select a Template</option>
                                <?php foreach ($all_templates as $template): ?>
                                    <option value="<?php echo htmlspecialchars($template['template_id']); ?>">
                                        <?php echo htmlspecialchars($template['template_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_list_id">Target List:</label>
                            <select id="edit_list_id" name="list_id" required>
                                <option value="">Select a List</option>
                                <?php foreach ($all_lists as $list): ?>
                                    <option value="<?php echo htmlspecialchars($list['list_id']); ?>">
                                        <?php echo htmlspecialchars($list['list_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_subject">Subject Line:</label>
                            <input type="text" id="edit_subject" name="subject" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status:</label>
                            <select id="edit_status" name="status">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="sending">Sending</option>
                                <option value="sent">Sent</option>
                                <option value="paused">Paused</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_send_at">Schedule Send Date/Time (Optional):</label>
                            <input type="datetime-local" id="edit_send_at" name="send_at">
                        </div>
                        <button type="submit">Update Campaign</button>
                        <button type="button" onclick="document.getElementById('editCampaignModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                function showEditForm(id, name, templateId, listId, subject, status, sendAt) {
                    document.getElementById('edit_campaign_id').value = id;
                    document.getElementById('edit_campaign_name').value = name;
                    document.getElementById('edit_template_id').value = templateId;
                    document.getElementById('edit_list_id').value = listId;
                    document.getElementById('edit_subject').value = subject;
                    document.getElementById('edit_status').value = status;
                    document.getElementById('edit_send_at').value = sendAt; // datetime-local needs YYYY-MM-DDTHH:MM format
                    document.getElementById('editCampaignModal').style.display = 'flex';
                }
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
