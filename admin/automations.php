<?php
/**
 * Mass Mailer Admin Automations Page
 *
 * This file provides the user interface for managing automation rules
 * within the Mass Mailer admin area.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Ensure core files are loaded
if (!class_exists('MassMailerAutomationManager')) {
    require_once dirname(__FILE__) . '/../includes/automation-manager.php';
    require_once dirname(__FILE__) . '/../includes/list-manager.php'; // For list selection in triggers/actions
    require_once dirname(__FILE__) . '/../includes/template-manager.php'; // For template selection in actions
    require_once dirname(__FILE__) . '/../includes/campaign-manager.php'; // For campaign selection in triggers/actions
}

$automation_manager = new MassMailerAutomationManager();
$list_manager = new MassMailerListManager();
$template_manager = new MassMailerTemplateManager();
$campaign_manager = new MassMailerCampaignManager();

$message = '';
$message_type = ''; // 'success' or 'error'

// Fetch all lists, templates, and campaigns for dropdowns
$all_lists = $list_manager->getAllLists();
$all_templates = $template_manager->getAllTemplates();
$all_campaigns = $campaign_manager->getAllCampaigns();

// Handle form submissions for adding/updating/deleting automations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_automation':
            case 'update_automation':
                $automation_id = isset($_POST['automation_id']) ? intval($_POST['automation_id']) : 0;
                $automation_name = isset($_POST['automation_name']) ? trim($_POST['automation_name']) : '';
                $trigger_type = isset($_POST['trigger_type']) ? trim($_POST['trigger_type']) : '';
                $action_type = isset($_POST['action_type']) ? trim($_POST['action_type']) : '';
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';

                // Build trigger_config based on trigger_type
                $trigger_config = [];
                if ($trigger_type === 'subscriber_added' && isset($_POST['trigger_list_id'])) {
                    $trigger_config['list_id'] = intval($_POST['trigger_list_id']);
                } elseif ($trigger_type === 'campaign_opened' && isset($_POST['trigger_campaign_id_open'])) {
                    $trigger_config['campaign_id'] = intval($_POST['trigger_campaign_id_open']);
                } elseif ($trigger_type === 'campaign_clicked' && isset($_POST['trigger_campaign_id_click']) && isset($_POST['trigger_link_url'])) {
                    $trigger_config['campaign_id'] = intval($_POST['trigger_campaign_id_click']);
                    $trigger_config['link_url'] = trim($_POST['trigger_link_url']);
                }
                // Add more trigger configs as needed

                // Build action_config based on action_type
                $action_config = [];
                if ($action_type === 'send_email' && isset($_POST['action_template_id']) && isset($_POST['action_campaign_id_send']) && isset($_POST['action_subject'])) {
                    $action_config['template_id'] = intval($_POST['action_template_id']);
                    $action_config['campaign_id'] = intval($_POST['action_campaign_id_send']);
                    $action_config['subject'] = trim($_POST['action_subject']);
                } elseif ($action_type === 'add_to_list' && isset($_POST['action_add_list_id'])) {
                    $action_config['list_id'] = intval($_POST['action_add_list_id']);
                } elseif ($action_type === 'remove_from_list' && isset($_POST['action_remove_list_id'])) {
                    $action_config['list_id'] = intval($_POST['action_remove_list_id']);
                }
                // Add more action configs as needed

                if (!empty($automation_name) && !empty($trigger_type) && !empty($action_type)) {
                    if ($_POST['action'] === 'add_automation') {
                        $new_automation_id = $automation_manager->createAutomation($automation_name, $trigger_type, $trigger_config, $action_type, $action_config, $status);
                        if ($new_automation_id) {
                            $message = 'Automation "' . htmlspecialchars($automation_name) . '" created successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to create automation. An automation with this name might already exist or invalid parameters.';
                            $message_type = 'error';
                        }
                    } else { // update_automation
                        if ($automation_id > 0) {
                            if ($automation_manager->updateAutomation($automation_id, $automation_name, $trigger_type, $trigger_config, $action_type, $action_config, $status)) {
                                $message = 'Automation "' . htmlspecialchars($automation_name) . '" updated successfully!';
                                $message_type = 'success';
                            } else {
                                $message = 'Failed to update automation. Check if the ID is valid or if the name is already taken.';
                                $message_type = 'error';
                            }
                        } else {
                            $message = 'Invalid automation ID for update.';
                            $message_type = 'error';
                        }
                    }
                } else {
                    $message = 'Automation name, trigger type, and action type cannot be empty.';
                    $message_type = 'error';
                }
                break;

            case 'delete_automation':
                $automation_id = isset($_POST['automation_id']) ? intval($_POST['automation_id']) : 0;
                if ($automation_id > 0) {
                    if ($automation_manager->deleteAutomation($automation_id)) {
                        $message = 'Automation deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete automation. Automation might not exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid automation ID for deletion.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch all automations for display
$all_automations = $automation_manager->getAllAutomations();

// Helper function to render trigger config fields
function renderTriggerConfigFields($trigger_type, $trigger_config, $all_lists, $all_campaigns) {
    $html = '';
    switch ($trigger_type) {
        case 'subscriber_added':
            $selected_list_id = $trigger_config['list_id'] ?? '';
            $html .= '<div class="form-group"><label for="trigger_list_id">To List:</label>';
            $html .= '<select id="trigger_list_id" name="trigger_list_id" required>';
            $html .= '<option value="">Select a List</option>';
            foreach ($all_lists as $list) {
                $html .= '<option value="' . htmlspecialchars($list['list_id']) . '"' . ($selected_list_id == $list['list_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($list['list_name']) . '</option>';
            }
            $html .= '</select></div>';
            break;
        case 'campaign_opened':
            $selected_campaign_id = $trigger_config['campaign_id'] ?? '';
            $html .= '<div class="form-group"><label for="trigger_campaign_id_open">For Campaign:</label>';
            $html .= '<select id="trigger_campaign_id_open" name="trigger_campaign_id_open" required>';
            $html .= '<option value="">Select a Campaign</option>';
            foreach ($all_campaigns as $campaign) {
                $html .= '<option value="' . htmlspecialchars($campaign['campaign_id']) . '"' . ($selected_campaign_id == $campaign['campaign_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($campaign['campaign_name']) . '</option>';
            }
            $html .= '</select></div>';
            break;
        case 'campaign_clicked':
            $selected_campaign_id = $trigger_config['campaign_id'] ?? '';
            $selected_link_url = $trigger_config['link_url'] ?? '';
            $html .= '<div class="form-group"><label for="trigger_campaign_id_click">For Campaign:</label>';
            $html .= '<select id="trigger_campaign_id_click" name="trigger_campaign_id_click" required>';
            $html .= '<option value="">Select a Campaign</option>';
            foreach ($all_campaigns as $campaign) {
                $html .= '<option value="' . htmlspecialchars($campaign['campaign_id']) . '"' . ($selected_campaign_id == $campaign['campaign_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($campaign['campaign_name']) . '</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="form-group"><label for="trigger_link_url">Specific Link URL (Optional):</label>';
            $html .= '<input type="text" id="trigger_link_url" name="trigger_link_url" value="' . htmlspecialchars($selected_link_url) . '" placeholder="e.g., http://yourdomain.com/product"></div>';
            break;
    }
    return $html;
}

// Helper function to render action config fields
function renderActionConfigFields($action_type, $action_config, $all_templates, $all_lists, $all_campaigns) {
    $html = '';
    switch ($action_type) {
        case 'send_email':
            $selected_template_id = $action_config['template_id'] ?? '';
            $selected_campaign_id_send = $action_config['campaign_id'] ?? '';
            $selected_subject = $action_config['subject'] ?? '';
            $html .= '<div class="form-group"><label for="action_template_id">Use Template:</label>';
            $html .= '<select id="action_template_id" name="action_template_id" required>';
            $html .= '<option value="">Select a Template</option>';
            foreach ($all_templates as $template) {
                $html .= '<option value="' . htmlspecialchars($template['template_id']) . '"' . ($selected_template_id == $template['template_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($template['template_name']) . '</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="form-group"><label for="action_campaign_id_send">Associate with Campaign (for tracking):</label>';
            $html .= '<select id="action_campaign_id_send" name="action_campaign_id_send" required>';
            $html .= '<option value="">Select a Campaign</option>';
            foreach ($all_campaigns as $campaign) {
                $html .= '<option value="' . htmlspecialchars($campaign['campaign_id']) . '"' . ($selected_campaign_id_send == $campaign['campaign_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($campaign['campaign_name']) . '</option>';
            }
            $html .= '</select></div>';
            $html .= '<div class="form-group"><label for="action_subject">Email Subject:</label>';
            $html .= '<input type="text" id="action_subject" name="action_subject" value="' . htmlspecialchars($selected_subject) . '" required></div>';
            break;
        case 'add_to_list':
            $selected_list_id = $action_config['list_id'] ?? '';
            $html .= '<div class="form-group"><label for="action_add_list_id">Add to List:</label>';
            $html .= '<select id="action_add_list_id" name="action_add_list_id" required>';
            $html .= '<option value="">Select a List</option>';
            foreach ($all_lists as $list) {
                $html .= '<option value="' . htmlspecialchars($list['list_id']) . '"' . ($selected_list_id == $list['list_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($list['list_name']) . '</option>';
            }
            $html .= '</select></div>';
            break;
        case 'remove_from_list':
            $selected_list_id = $action_config['list_id'] ?? '';
            $html .= '<div class="form-group"><label for="action_remove_list_id">Remove from List:</label>';
            $html .= '<select id="action_remove_list_id" name="action_remove_list_id" required>';
            $html .= '<option value="">Select a List</option>';
            foreach ($all_lists as $list) {
                $html .= '<option value="' . htmlspecialchars($list['list_id']) . '"' . ($selected_list_id == $list['list_id'] ? ' selected' : '') . '>';
                $html .= htmlspecialchars($list['list_name']) . '</option>';
            }
            $html .= '</select></div>';
            break;
    }
    return $html;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Manage Automations</title>
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
        form input[type="text"], form select { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
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
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #6c757d; }
        #trigger_config_fields, #action_config_fields {
            padding: 15px;
            border: 1px dashed #ccc;
            border-radius: 5px;
            margin-bottom: 15px;
            background-color: #fefefe;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Automations</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Add New Automation</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_automation">
            <div class="form-group">
                <label for="automation_name">Automation Name:</label>
                <input type="text" id="automation_name" name="automation_name" required>
            </div>

            <div class="form-group">
                <label for="trigger_type">Trigger Type:</label>
                <select id="trigger_type" name="trigger_type" onchange="updateTriggerFields(this.value)" required>
                    <option value="">Select Trigger</option>
                    <option value="subscriber_added">Subscriber Added to List</option>
                    <option value="campaign_opened">Campaign Opened</option>
                    <option value="campaign_clicked">Campaign Clicked Link</option>
                    <!-- Add more trigger types here -->
                </select>
            </div>
            <div id="trigger_config_fields">
                <!-- Dynamic trigger configuration fields will load here -->
            </div>

            <div class="form-group">
                <label for="action_type">Action Type:</label>
                <select id="action_type" name="action_type" onchange="updateActionFields(this.value)" required>
                    <option value="">Select Action</option>
                    <option value="send_email">Send Email</option>
                    <option value="add_to_list">Add Subscriber to List</option>
                    <option value="remove_from_list">Remove Subscriber from List</option>
                    <!-- Add more action types here -->
                </select>
            </div>
            <div id="action_config_fields">
                <!-- Dynamic action configuration fields will load here -->
            </div>

            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button type="submit">Create Automation</button>
        </form>

        <h2>Existing Automations</h2>
        <?php if (empty($all_automations)): ?>
            <p>No automations found. Create one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Trigger</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_automations as $automation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($automation['automation_id']); ?></td>
                            <td><?php echo htmlspecialchars($automation['automation_name']); ?></td>
                            <td>
                                <?php
                                echo htmlspecialchars(str_replace('_', ' ', ucfirst($automation['trigger_type'])));
                                if (!empty($automation['trigger_config'])) {
                                    echo ' (';
                                    $config_parts = [];
                                    if (isset($automation['trigger_config']['list_id'])) {
                                        $list = $list_manager->getList($automation['trigger_config']['list_id']);
                                        $config_parts[] = 'List: ' . htmlspecialchars($list['list_name'] ?? 'N/A');
                                    }
                                    if (isset($automation['trigger_config']['campaign_id'])) {
                                        $campaign = $campaign_manager->getCampaign($automation['trigger_config']['campaign_id']);
                                        $config_parts[] = 'Campaign: ' . htmlspecialchars($campaign['campaign_name'] ?? 'N/A');
                                    }
                                    if (isset($automation['trigger_config']['link_url'])) {
                                        $config_parts[] = 'Link: ' . htmlspecialchars($automation['trigger_config']['link_url']);
                                    }
                                    echo implode(', ', $config_parts);
                                    echo ')';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                echo htmlspecialchars(str_replace('_', ' ', ucfirst($automation['action_type'])));
                                if (!empty($automation['action_config'])) {
                                    echo ' (';
                                    $config_parts = [];
                                    if (isset($automation['action_config']['template_id'])) {
                                        $template = $template_manager->getTemplate($automation['action_config']['template_id']);
                                        $config_parts[] = 'Template: ' . htmlspecialchars($template['template_name'] ?? 'N/A');
                                    }
                                    if (isset($automation['action_config']['list_id'])) {
                                        $list = $list_manager->getList($automation['action_config']['list_id']);
                                        $config_parts[] = 'List: ' . htmlspecialchars($list['list_name'] ?? 'N/A');
                                    }
                                    if (isset($automation['action_config']['subject'])) {
                                        $config_parts[] = 'Subject: ' . htmlspecialchars($automation['action_config']['subject']);
                                    }
                                    echo implode(', ', $config_parts);
                                    echo ')';
                                }
                                ?>
                            </td>
                            <td><span class="status-<?php echo htmlspecialchars($automation['status']); ?>"><?php echo htmlspecialchars(ucfirst($automation['status'])); ?></span></td>
                            <td><?php echo htmlspecialchars($automation['created_at']); ?></td>
                            <td class="action-buttons">
                                <button class="edit" onclick="showEditForm(
                                    <?php echo $automation['automation_id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($automation['automation_name'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($automation['trigger_type'])); ?>',
                                    '<?php echo addslashes(json_encode($automation['trigger_config'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($automation['action_type'])); ?>',
                                    '<?php echo addslashes(json_encode($automation['action_config'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($automation['status'])); ?>'
                                )">Edit</button>

                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this automation?');">
                                    <input type="hidden" name="action" value="delete_automation">
                                    <input type="hidden" name="automation_id" value="<?php echo htmlspecialchars($automation['automation_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit Automation Modal/Form (hidden by default) -->
            <div id="editAutomationModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:600px; max-height: 90vh; overflow-y: auto; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit Automation</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_automation">
                        <input type="hidden" id="edit_automation_id" name="automation_id">
                        <div class="form-group">
                            <label for="edit_automation_name">Automation Name:</label>
                            <input type="text" id="edit_automation_name" name="automation_name" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_trigger_type">Trigger Type:</label>
                            <select id="edit_trigger_type" name="trigger_type" onchange="updateTriggerFields(this.value, 'edit_')" required>
                                <option value="">Select Trigger</option>
                                <option value="subscriber_added">Subscriber Added to List</option>
                                <option value="campaign_opened">Campaign Opened</option>
                                <option value="campaign_clicked">Campaign Clicked Link</option>
                            </select>
                        </div>
                        <div id="edit_trigger_config_fields">
                            <!-- Dynamic trigger configuration fields will load here -->
                        </div>

                        <div class="form-group">
                            <label for="edit_action_type">Action Type:</label>
                            <select id="edit_action_type" name="action_type" onchange="updateActionFields(this.value, 'edit_')" required>
                                <option value="">Select Action</option>
                                <option value="send_email">Send Email</option>
                                <option value="add_to_list">Add Subscriber to List</option>
                                <option value="remove_from_list">Remove Subscriber from List</option>
                            </select>
                        </div>
                        <div id="edit_action_config_fields">
                            <!-- Dynamic action configuration fields will load here -->
                        </div>

                        <div class="form-group">
                            <label for="edit_status">Status:</label>
                            <select id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <button type="submit">Update Automation</button>
                        <button type="button" onclick="document.getElementById('editAutomationModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                const allLists = <?php echo json_encode($all_lists); ?>;
                const allTemplates = <?php echo json_encode($all_templates); ?>;
                const allCampaigns = <?php echo json_encode($all_campaigns); ?>;

                function generateTriggerFieldsHtml(triggerType, prefix = '', currentConfig = {}) {
                    let html = '';
                    switch (triggerType) {
                        case 'subscriber_added':
                            html += `<div class="form-group">
                                <label for="${prefix}trigger_list_id">To List:</label>
                                <select id="${prefix}trigger_list_id" name="trigger_list_id" required>
                                    <option value="">Select a List</option>`;
                            allLists.forEach(list => {
                                html += `<option value="${list.list_id}" ${currentConfig.list_id == list.list_id ? 'selected' : ''}>${list.list_name}</option>`;
                            });
                            html += `</select></div>`;
                            break;
                        case 'campaign_opened':
                            html += `<div class="form-group">
                                <label for="${prefix}trigger_campaign_id_open">For Campaign:</label>
                                <select id="${prefix}trigger_campaign_id_open" name="trigger_campaign_id_open" required>
                                    <option value="">Select a Campaign</option>`;
                            allCampaigns.forEach(campaign => {
                                html += `<option value="${campaign.campaign_id}" ${currentConfig.campaign_id == campaign.campaign_id ? 'selected' : ''}>${campaign.campaign_name}</option>`;
                            });
                            html += `</select></div>`;
                            break;
                        case 'campaign_clicked':
                            html += `<div class="form-group">
                                <label for="${prefix}trigger_campaign_id_click">For Campaign:</label>
                                <select id="${prefix}trigger_campaign_id_click" name="trigger_campaign_id_click" required>
                                    <option value="">Select a Campaign</option>`;
                            allCampaigns.forEach(campaign => {
                                html += `<option value="${campaign.campaign_id}" ${currentConfig.campaign_id == campaign.campaign_id ? 'selected' : ''}>${campaign.campaign_name}</option>`;
                            });
                            html += `</select></div>`;
                            html += `<div class="form-group">
                                <label for="${prefix}trigger_link_url">Specific Link URL (Optional):</label>
                                <input type="text" id="${prefix}trigger_link_url" name="trigger_link_url" value="${currentConfig.link_url || ''}" placeholder="e.g., http://yourdomain.com/product"></div>`;
                            break;
                    }
                    return html;
                }

                function generateActionFieldsHtml(actionType, prefix = '', currentConfig = {}) {
                    let html = '';
                    switch (actionType) {
                        case 'send_email':
                            html += `<div class="form-group">
                                <label for="${prefix}action_template_id">Use Template:</label>
                                <select id="${prefix}action_template_id" name="action_template_id" required>
                                    <option value="">Select a Template</option>`;
                            allTemplates.forEach(template => {
                                html += `<option value="${template.template_id}" ${currentConfig.template_id == template.template_id ? 'selected' : ''}>${template.template_name}</option>`;
                            });
                            html += `</select></div>`;
                            html += `<div class="form-group">
                                <label for="${prefix}action_campaign_id_send">Associate with Campaign (for tracking):</label>
                                <select id="${prefix}action_campaign_id_send" name="action_campaign_id_send" required>
                                    <option value="">Select a Campaign</option>`;
                            allCampaigns.forEach(campaign => {
                                html += `<option value="${campaign.campaign_id}" ${currentConfig.campaign_id == campaign.campaign_id ? 'selected' : ''}>${campaign.campaign_name}</option>`;
                            });
                            html += `</select></div>`;
                            html += `<div class="form-group">
                                <label for="${prefix}action_subject">Email Subject:</label>
                                <input type="text" id="${prefix}action_subject" name="action_subject" value="${currentConfig.subject || ''}" required></div>`;
                            break;
                        case 'add_to_list':
                            html += `<div class="form-group">
                                <label for="${prefix}action_add_list_id">Add to List:</label>
                                <select id="${prefix}action_add_list_id" name="action_add_list_id" required>
                                    <option value="">Select a List</option>`;
                            allLists.forEach(list => {
                                html += `<option value="${list.list_id}" ${currentConfig.list_id == list.list_id ? 'selected' : ''}>${list.list_name}</option>`;
                            });
                            html += `</select></div>`;
                            break;
                        case 'remove_from_list':
                            html += `<div class="form-group">
                                <label for="${prefix}action_remove_list_id">Remove from List:</label>
                                <select id="${prefix}action_remove_list_id" name="action_remove_list_id" required>
                                    <option value="">Select a List</option>`;
                            allLists.forEach(list => {
                                html += `<option value="${list.list_id}" ${currentConfig.list_id == list.list_id ? 'selected' : ''}>${list.list_name}</option>`;
                            });
                            html += `</select></div>`;
                            break;
                    }
                    return html;
                }

                function updateTriggerFields(triggerType, prefix = '') {
                    document.getElementById(prefix + 'trigger_config_fields').innerHTML = generateTriggerFieldsHtml(triggerType, prefix);
                }

                function updateActionFields(actionType, prefix = '') {
                    document.getElementById(prefix + 'action_config_fields').innerHTML = generateActionFieldsHtml(actionType, prefix);
                }

                function showEditForm(id, name, triggerType, triggerConfigJson, actionType, actionConfigJson, status) {
                    const triggerConfig = JSON.parse(triggerConfigJson);
                    const actionConfig = JSON.parse(actionConfigJson);

                    document.getElementById('edit_automation_id').value = id;
                    document.getElementById('edit_automation_name').value = name;
                    document.getElementById('edit_trigger_type').value = triggerType;
                    document.getElementById('edit_action_type').value = actionType;
                    document.getElementById('edit_status').value = status;

                    // Populate dynamic fields for edit form
                    document.getElementById('edit_trigger_config_fields').innerHTML = generateTriggerFieldsHtml(triggerType, 'edit_', triggerConfig);
                    document.getElementById('edit_action_config_fields').innerHTML = generateActionFieldsHtml(actionType, 'edit_', actionConfig);

                    document.getElementById('editAutomationModal').style.display = 'flex';
                }

                // Initial load for add form (if no trigger/action type selected)
                document.addEventListener('DOMContentLoaded', () => {
                    updateTriggerFields(document.getElementById('trigger_type').value);
                    updateActionFields(document.getElementById('action_type').value);
                });
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
