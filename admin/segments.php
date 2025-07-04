<?php
/**
 * Mass Mailer Admin Segments Page
 *
 * This file provides the user interface for managing subscriber segments
 * within the Mass Mailer admin area.
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
// if (!$auth->hasRole('editor')) {
//     die('Access Denied. You do not have permission to view this page.');
// }

// Ensure core files are loaded
if (!class_exists('MassMailerSegmentManager')) {
    require_once dirname(__FILE__) . '/../includes/segment-manager.php';
    require_once dirname(__FILE__) . '/../includes/list-manager.php'; // For list selection in rules
}

$segment_manager = new MassMailerSegmentManager();
$list_manager = new MassMailerListManager();

$message = '';
$message_type = ''; // 'success' or 'error'

// Fetch all lists for rule builder dropdowns
$all_lists = $list_manager->getAllLists();

// Handle form submissions for adding/updating/deleting segments
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $segment_name = isset($_POST['segment_name']) ? trim($_POST['segment_name']) : '';
        $rules = [];

        // Parse rules from form (simplified for initial implementation)
        if (isset($_POST['rule_field']) && is_array($_POST['rule_field'])) {
            foreach ($_POST['rule_field'] as $i => $field) {
                $operator = $_POST['rule_operator'][$i] ?? '';
                $value = $_POST['rule_value'][$i] ?? '';

                if (!empty($field) && !empty($operator) && $value !== '') {
                    // Handle array values for IN operator (e.g., for list_id)
                    if ($operator === 'IN' && strpos($value, ',') !== false) {
                        $value = array_map('trim', explode(',', $value));
                    }
                    $rules[] = [
                        'field' => $field,
                        'operator' => $operator,
                        'value' => $value
                    ];
                }
            }
        }

        switch ($_POST['action']) {
            case 'add_segment':
                if (!empty($segment_name) && !empty($rules)) {
                    $new_segment_id = $segment_manager->createSegment($segment_name, $rules);
                    if ($new_segment_id) {
                        $message = 'Segment "' . htmlspecialchars($segment_name) . '" created successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to create segment. A segment with this name might already exist or invalid rules.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Segment name and at least one rule are required.';
                    $message_type = 'error';
                }
                break;

            case 'update_segment':
                $segment_id = isset($_POST['segment_id']) ? intval($_POST['segment_id']) : 0;
                if ($segment_id > 0 && !empty($segment_name) && !empty($rules)) {
                    if ($segment_manager->updateSegment($segment_id, $segment_name, $rules)) {
                        $message = 'Segment "' . htmlspecialchars($segment_name) . '" updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update segment. Check if the ID is valid or if the name is already taken.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid segment ID, name, or rules for update.';
                    $message_type = 'error';
                }
                break;

            case 'delete_segment':
                $segment_id = isset($_POST['segment_id']) ? intval($_POST['segment_id']) : 0;
                if ($segment_id > 0) {
                    if ($segment_manager->deleteSegment($segment_id)) {
                        $message = 'Segment deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete segment. Segment might not exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid segment ID for deletion.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch all segments for display
$all_segments = $segment_manager->getAllSegments();

// Helper to get list name by ID
function getListNameById($list_id, $all_lists) {
    foreach ($all_lists as $list) {
        if ($list['list_id'] == $list_id) {
            return $list['list_name'];
        }
    }
    return 'N/A';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Manage Segments</title>
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
        form input[type="text"], form select, form input[type="email"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
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

        .rule-builder { border: 1px dashed #ccc; padding: 15px; margin-bottom: 15px; background-color: #fefefe; border-radius: 5px; }
        .rule-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .rule-row select, .rule-row input { flex: 1; }
        .rule-row button { flex-shrink: 0; background-color: #dc3545; }
        .add-rule-button { background-color: #28a745; margin-top: 10px; }
        .add-rule-button:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Segments</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Add New Segment</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_segment">
            <div class="form-group">
                <label for="segment_name">Segment Name:</label>
                <input type="text" id="segment_name" name="segment_name" required>
            </div>

            <h3>Segment Rules:</h3>
            <div id="rule_builder_container" class="rule-builder">
                <!-- Rule rows will be added here by JavaScript -->
            </div>
            <button type="button" class="add-rule-button" onclick="addRuleRow()">Add Rule</button>
            <p class="info-text">For 'List ID' rules with 'IN' operator, enter comma-separated IDs (e.g., 1,2,3).</p>
            <p class="info-text">For 'Subscribed At' rules, use YYYY-MM-DD format (e.g., 2023-01-01).</p>
            <p class="info-text">For 'Status' rules with 'IN' operator, enter comma-separated statuses (e.g., subscribed,pending).</p>
            <p class="info-text">For 'First Name', 'Last Name', 'Email' rules with 'LIKE' operator, enter partial text (e.g., john).</p>


            <button type="submit" style="margin-top: 20px;">Create Segment</button>
        </form>

        <h2>Existing Segments</h2>
        <?php if (empty($all_segments)): ?>
            <p>No segments found. Create one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Rules</th>
                        <th>Subscribers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_segments as $segment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($segment['segment_id']); ?></td>
                            <td><?php echo htmlspecialchars($segment['segment_name']); ?></td>
                            <td>
                                <?php
                                if (!empty($segment['segment_rules'])) {
                                    echo '<ul>';
                                    foreach ($segment['segment_rules'] as $rule) {
                                        echo '<li>' . htmlspecialchars($rule['field']) . ' ' . htmlspecialchars($rule['operator']) . ' ';
                                        if ($rule['field'] === 'list_id' && (is_array($rule['value']) || strpos($rule['value'], ',') !== false)) {
                                            $list_names = [];
                                            $list_ids_arr = is_array($rule['value']) ? $rule['value'] : array_map('trim', explode(',', $rule['value']));
                                            foreach ($list_ids_arr as $list_id) {
                                                $list_names[] = getListNameById($list_id, $all_lists);
                                            }
                                            echo '(' . htmlspecialchars(implode(', ', $list_names)) . ')';
                                        } else if (is_array($rule['value'])) {
                                            echo '(' . htmlspecialchars(implode(', ', $rule['value'])) . ')';
                                        } else {
                                            echo htmlspecialchars($rule['value']);
                                        }
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                } else {
                                    echo 'No rules defined.';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // This is a conceptual count. For large segments, you might not want to do this on page load.
                                $subscribers_in_segment = $segment_manager->getSubscribersInSegment($segment['segment_id']);
                                echo count($subscribers_in_segment);
                                ?>
                            </td>
                            <td class="action-buttons">
                                <button class="edit" onclick="showEditForm(
                                    <?php echo $segment['segment_id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($segment['segment_name'])); ?>',
                                    '<?php echo addslashes(json_encode($segment['segment_rules'])); ?>'
                                )">Edit</button>

                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this segment?');">
                                    <input type="hidden" name="action" value="delete_segment">
                                    <input type="hidden" name="segment_id" value="<?php echo htmlspecialchars($segment['segment_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit Segment Modal/Form (hidden by default) -->
            <div id="editSegmentModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:800px; max-height: 90vh; overflow-y: auto; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit Segment</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_segment">
                        <input type="hidden" id="edit_segment_id" name="segment_id">
                        <div class="form-group">
                            <label for="edit_segment_name">Segment Name:</label>
                            <input type="text" id="edit_segment_name" name="segment_name" required>
                        </div>

                        <h3>Segment Rules:</h3>
                        <div id="edit_rule_builder_container" class="rule-builder">
                            <!-- Rule rows will be loaded here by JavaScript for editing -->
                        </div>
                        <button type="button" class="add-rule-button" onclick="addRuleRow('edit_')">Add Rule</button>
                        <p class="info-text">For 'List ID' rules with 'IN' operator, enter comma-separated IDs (e.g., 1,2,3).</p>
                        <p class="info-text">For 'Subscribed At' rules, use YYYY-MM-DD format (e.g., 2023-01-01).</p>
                        <p class="info-text">For 'Status' rules with 'IN' operator, enter comma-separated statuses (e.g., subscribed,pending).</p>
                        <p class="info-text">For 'First Name', 'Last Name', 'Email' rules with 'LIKE' operator, enter partial text (e.g., john).</p>

                        <button type="submit" style="margin-top: 20px;">Update Segment</button>
                        <button type="button" onclick="document.getElementById('editSegmentModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                const allLists = <?php echo json_encode($all_lists); ?>; // Pass PHP lists to JS

                function addRuleRow(prefix = '', rule = {}) {
                    const containerId = prefix + 'rule_builder_container';
                    const container = document.getElementById(containerId);
                    if (!container) return;

                    const ruleRow = document.createElement('div');
                    ruleRow.className = 'rule-row';

                    const fieldSelect = document.createElement('select');
                    fieldSelect.name = 'rule_field[]';
                    fieldSelect.required = true;
                    fieldSelect.innerHTML = `
                        <option value="">Select Field</option>
                        <option value="list_id">List ID</option>
                        <option value="status">Status</option>
                        <option value="subscribed_at">Subscribed At</option>
                        <option value="first_name">First Name</option>
                        <option value="last_name">Last Name</option>
                        <option value="email">Email</option>
                        <!-- Add more fields here -->
                    `;
                    fieldSelect.value = rule.field || '';
                    fieldSelect.onchange = () => updateOperatorAndValue(ruleRow, fieldSelect.value, prefix);

                    const operatorSelect = document.createElement('select');
                    operatorSelect.name = 'rule_operator[]';
                    operatorSelect.required = true;
                    // Operators will be dynamically updated by updateOperatorAndValue
                    operatorSelect.innerHTML = `<option value="">Select Operator</option>`;
                    operatorSelect.value = rule.operator || '';

                    const valueInput = document.createElement('input');
                    valueInput.type = 'text';
                    valueInput.name = 'rule_value[]';
                    valueInput.placeholder = 'Value';
                    valueInput.required = true;
                    valueInput.value = Array.isArray(rule.value) ? rule.value.join(',') : (rule.value || '');

                    const removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.textContent = 'Remove';
                    removeButton.onclick = () => ruleRow.remove();

                    ruleRow.appendChild(fieldSelect);
                    ruleRow.appendChild(operatorSelect);
                    ruleRow.appendChild(valueInput);
                    ruleRow.appendChild(removeButton);

                    container.appendChild(ruleRow);

                    // Trigger update for operator and value fields based on initial rule.field
                    if (rule.field) {
                        updateOperatorAndValue(ruleRow, rule.field, prefix, rule.operator, rule.value);
                    }
                }

                function updateOperatorAndValue(row, field, prefix, currentOperator = '', currentValue = '') {
                    const operatorSelect = row.children[1]; // Second child is operator select
                    const valueInput = row.children[2];    // Third child is value input

                    let operatorsHtml = '';
                    let valueType = 'text'; // Default input type

                    switch (field) {
                        case 'list_id':
                        case 'status':
                            operatorsHtml = `
                                <option value="">Select Operator</option>
                                <option value="=">Is</option>
                                <option value="!=">Is Not</option>
                                <option value="IN">Is In (comma-separated IDs/statuses)</option>
                                <option value="NOT IN">Is Not In (comma-separated IDs/statuses)</option>
                            `;
                            break;
                        case 'subscribed_at':
                            operatorsHtml = `
                                <option value="">Select Operator</option>
                                <option value=">">After</option>
                                <option value="<">Before</option>
                                <option value=">=">On or After</option>
                                <option value="<=">On or Before</option>
                                <option value="BETWEEN">Between (YYYY-MM-DD,YYYY-MM-DD)</option>
                            `;
                            valueType = 'text'; // Can be date or date range string
                            break;
                        case 'first_name':
                        case 'last_name':
                        case 'email':
                            operatorsHtml = `
                                <option value="">Select Operator</option>
                                <option value="=">Is Exactly</option>
                                <option value="!=">Is Not Exactly</option>
                                <option value="LIKE">Contains (partial match)</option>
                                <option value="NOT LIKE">Does Not Contain</option>
                            `;
                            break;
                        default:
                            operatorsHtml = `<option value="">Select Operator</option>`;
                            break;
                    }
                    operatorSelect.innerHTML = operatorsHtml;
                    operatorSelect.value = currentOperator; // Set selected operator

                    valueInput.type = valueType;
                    valueInput.value = Array.isArray(currentValue) ? currentValue.join(',') : currentValue; // Set value
                }


                function showEditForm(id, name, rulesJson) {
                    const rules = JSON.parse(rulesJson);
                    document.getElementById('edit_segment_id').value = id;
                    document.getElementById('edit_segment_name').value = name;

                    const editContainer = document.getElementById('edit_rule_builder_container');
                    editContainer.innerHTML = ''; // Clear existing rules

                    rules.forEach(rule => {
                        addRuleRow('edit_', rule); // Populate with existing rules
                    });

                    document.getElementById('editSegmentModal').style.display = 'flex';
                }

                // Initial load: Add one empty rule row for the add form
                document.addEventListener('DOMContentLoaded', () => {
                    addRuleRow();
                });
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
