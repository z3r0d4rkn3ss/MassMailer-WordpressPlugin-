<?php
/**
 * Mass Mailer Admin Templates Page
 *
 * This file provides the user interface for managing email templates
 * within the Mass Mailer admin area.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Ensure core files are loaded
if (!class_exists('MassMailerTemplateManager')) {
    require_once dirname(__FILE__) . '/../includes/template-manager.php';
}

$template_manager = new MassMailerTemplateManager();
$message = '';
$message_type = ''; // 'success' or 'error'

// Handle form submissions for adding/updating/deleting templates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_template':
                $template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : '';
                $template_subject = isset($_POST['template_subject']) ? trim($_POST['template_subject']) : '';
                $template_html = isset($_POST['template_html']) ? $_POST['template_html'] : '';
                $template_text = isset($_POST['template_text']) ? $_POST['template_text'] : '';

                if (!empty($template_name) && !empty($template_subject) && !empty($template_html)) {
                    $new_template_id = $template_manager->createTemplate($template_name, $template_subject, $template_html, $template_text);
                    if ($new_template_id) {
                        $message = 'Template "' . htmlspecialchars($template_name) . '" created successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to create template. A template with this name might already exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Template name, subject, and HTML content cannot be empty.';
                    $message_type = 'error';
                }
                break;

            case 'update_template':
                $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
                $template_name = isset($_POST['template_name']) ? trim($_POST['template_name']) : '';
                $template_subject = isset($_POST['template_subject']) ? trim($_POST['template_subject']) : '';
                $template_html = isset($_POST['template_html']) ? $_POST['template_html'] : '';
                $template_text = isset($_POST['template_text']) ? $_POST['template_text'] : '';

                if ($template_id > 0 && !empty($template_name) && !empty($template_subject) && !empty($template_html)) {
                    if ($template_manager->updateTemplate($template_id, $template_name, $template_subject, $template_html, $template_text)) {
                        $message = 'Template "' . htmlspecialchars($template_name) . '" updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update template. Check if the ID is valid or if the name is already taken.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid template ID, name, subject, or HTML content for update.';
                    $message_type = 'error';
                }
                break;

            case 'delete_template':
                $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
                if ($template_id > 0) {
                    if ($template_manager->deleteTemplate($template_id)) {
                        $message = 'Template deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete template. Template might not exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid template ID for deletion.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch all templates for display
$all_templates = $template_manager->getAllTemplates();

// --- HTML for Admin Templates Page ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Manage Templates</title>
    <!-- Basic Admin CSS (you would have a dedicated admin.css) -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        form { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee; }
        form label { display: block; margin-bottom: 8px; font-weight: 600; }
        form input[type="text"], form textarea { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
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
        .action-buttons .design { background-color: #17a2b8; color: #fff; }
        .action-buttons .design:hover { background-color: #138496; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Email Templates</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Add New Template</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_template">
            <div class="form-group">
                <label for="template_name">Template Name:</label>
                <input type="text" id="template_name" name="template_name" required>
            </div>
            <div class="form-group">
                <label for="template_subject">Default Subject:</label>
                <input type="text" id="template_subject" name="template_subject" required>
            </div>
            <div class="form-group">
                <label for="template_html">HTML Content:</label>
                <textarea id="template_html" name="template_html" rows="10" required></textarea>
            </div>
            <div class="form-group">
                <label for="template_text">Plain Text Content (Optional):</label>
                <textarea id="template_text" name="template_text" rows="5"></textarea>
            </div>
            <button type="submit">Add Template</button>
        </form>

        <h2>Existing Templates</h2>
        <?php if (empty($all_templates)): ?>
            <p>No email templates found. Add one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Subject</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_templates as $template): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($template['template_id']); ?></td>
                            <td><?php echo htmlspecialchars($template['template_name']); ?></td>
                            <td><?php echo htmlspecialchars($template['template_subject']); ?></td>
                            <td class="action-buttons">
                                <!-- Edit Form (can be a modal or separate page) -->
                                <button class="edit" onclick="showEditForm(
                                    <?php echo $template['template_id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($template['template_name'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($template['template_subject'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($template['template_html'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($template['template_text'])); ?>'
                                )">Edit</button>

                                <!-- Link to Email Designer -->
                                <a href="email-designer.php?template_id=<?php echo htmlspecialchars($template['template_id']); ?>" class="design"><button>Design</button></a>

                                <!-- Delete Form -->
                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                    <input type="hidden" name="action" value="delete_template">
                                    <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($template['template_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit Template Modal/Form (hidden by default) -->
            <div id="editTemplateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:600px; max-height: 90vh; overflow-y: auto; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit Template</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_template">
                        <input type="hidden" id="edit_template_id" name="template_id">
                        <div class="form-group">
                            <label for="edit_template_name">Template Name:</label>
                            <input type="text" id="edit_template_name" name="template_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_template_subject">Default Subject:</label>
                            <input type="text" id="edit_template_subject" name="template_subject" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_template_html">HTML Content:</label>
                            <textarea id="edit_template_html" name="template_html" rows="15" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_template_text">Plain Text Content (Optional):</label>
                            <textarea id="edit_template_text" name="template_text" rows="8"></textarea>
                        </div>
                        <button type="submit">Update Template</button>
                        <button type="button" onclick="document.getElementById('editTemplateModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                function showEditForm(id, name, subject, html, text) {
                    document.getElementById('edit_template_id').value = id;
                    document.getElementById('edit_template_name').value = name;
                    document.getElementById('edit_template_subject').value = subject;
                    document.getElementById('edit_template_html').value = html;
                    document.getElementById('edit_template_text').value = text;
                    document.getElementById('editTemplateModal').style.display = 'flex'; // Use flex to center
                }
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
