<?php
/**
 * Mass Mailer Email Designer View
 *
 * This file provides a basic placeholder for the email design interface.
 * A full-fledged email designer would involve complex JavaScript and UI components.
 * For now, it allows basic editing of the HTML content of a selected template.
 *
 * @package Mass_Mailer
 * @subpackage Views
 */

// Ensure template manager is loaded
if (!class_exists('MassMailerTemplateManager')) {
    require_once dirname(__FILE__) . '/../includes/template-manager.php';
}

$template_manager = new MassMailerTemplateManager();
$template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
$template = null;
$message = '';
$message_type = '';

if ($template_id > 0) {
    $template = $template_manager->getTemplate($template_id);
    if (!$template) {
        $message = 'Template not found.';
        $message_type = 'error';
    }
} else {
    $message = 'No template ID provided.';
    $message_type = 'error';
}

// Handle saving template content from the designer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template_content') {
    $template_id_post = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $html_content = isset($_POST['html_content']) ? $_POST['html_content'] : '';
    $text_content = isset($_POST['text_content']) ? $_POST['text_content'] : '';

    if ($template_id_post > 0 && $template) {
        // Use the existing template name and subject, just update content
        if ($template_manager->updateTemplate(
            $template_id_post,
            $template['template_name'],
            $template['template_subject'],
            $html_content,
            $text_content
        )) {
            $message = 'Template content saved successfully!';
            $message_type = 'success';
            // Re-fetch template to show updated content
            $template = $template_manager->getTemplate($template_id_post);
        } else {
            $message = 'Failed to save template content.';
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid template ID or template not loaded.';
        $message_type = 'error';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Email Designer</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .designer-area { display: flex; gap: 20px; margin-top: 20px; }
        .editor-panel, .preview-panel { flex: 1; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .editor-panel h3, .preview-panel h3 { background-color: #f2f2f2; padding: 10px 15px; margin: 0; border-bottom: 1px solid #ddd; }
        .editor-content { padding: 15px; }
        .editor-content textarea { width: calc(100% - 20px); height: 400px; border: 1px solid #ccc; border-radius: 4px; padding: 10px; font-family: monospace; }
        .preview-content { padding: 15px; background-color: #fff; min-height: 400px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .save-button { background-color: #28a745; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; margin-top: 15px; }
        .save-button:hover { background-color: #218838; }
        .back-link { margin-bottom: 20px; display: block; text-decoration: none; color: #007cba; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Email Designer: <?php echo $template ? htmlspecialchars($template['template_name']) : 'New Template'; ?></h1>

        <a href="templates.php" class="back-link">&larr; Back to Templates</a>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($template): ?>
            <form method="POST" action="" id="designerForm">
                <input type="hidden" name="action" value="save_template_content">
                <input type="hidden" name="template_id" value="<?php echo htmlspecialchars($template['template_id']); ?>">

                <div class="designer-area">
                    <div class="editor-panel">
                        <h3>HTML Editor</h3>
                        <div class="editor-content">
                            <div class="form-group">
                                <label for="html_content">Edit HTML:</label>
                                <textarea id="html_content" name="html_content" onkeyup="updatePreview()"><?php echo htmlspecialchars($template['template_html']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="text_content">Plain Text Content:</label>
                                <textarea id="text_content" name="text_content"><?php echo htmlspecialchars($template['template_text']); ?></textarea>
                            </div>
                            <button type="submit" class="save-button">Save Template Content</button>
                        </div>
                    </div>
                    <div class="preview-panel">
                        <h3>Live Preview</h3>
                        <div class="preview-content" id="email_preview">
                            <!-- Email HTML will be rendered here -->
                        </div>
                    </div>
                </div>
            </form>

            <script>
                function updatePreview() {
                    const htmlContent = document.getElementById('html_content').value;
                    const previewFrame = document.getElementById('email_preview');
                    // Using innerHTML directly for demonstration. For security in a real app,
                    // consider sandboxing if user-provided HTML is involved.
                    previewFrame.innerHTML = htmlContent;
                }

                // Initial preview update on load
                document.addEventListener('DOMContentLoaded', updatePreview);
            </script>
        <?php else: ?>
            <p>Please select a template from the <a href="templates.php">Manage Templates</a> page to start designing.</p>
        <?php endif; ?>
    </div>
</body>
</html>
