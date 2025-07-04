<?php
/**
 * Mass Mailer Admin Lists Page
 *
 * This file provides the user interface for managing mailing lists
 * within the Mass Mailer admin area.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Ensure core files are loaded
if (!class_exists('MassMailerListManager')) {
    require_once dirname(__FILE__) . '/../includes/list-manager.php';
}

$list_manager = new MassMailerListManager();
$message = '';
$message_type = ''; // 'success' or 'error'

// Handle form submissions for adding/updating/deleting lists
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_list':
                $list_name = isset($_POST['list_name']) ? trim($_POST['list_name']) : '';
                $list_description = isset($_POST['list_description']) ? trim($_POST['list_description']) : '';

                if (!empty($list_name)) {
                    $new_list_id = $list_manager->createList($list_name, $list_description);
                    if ($new_list_id) {
                        $message = 'List "' . htmlspecialchars($list_name) . '" created successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to create list. A list with this name might already exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'List name cannot be empty.';
                    $message_type = 'error';
                }
                break;

            case 'update_list':
                $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
                $list_name = isset($_POST['list_name']) ? trim($_POST['list_name']) : '';
                $list_description = isset($_POST['list_description']) ? trim($_POST['list_description']) : '';

                if ($list_id > 0 && !empty($list_name)) {
                    if ($list_manager->updateList($list_id, $list_name, $list_description)) {
                        $message = 'List "' . htmlspecialchars($list_name) . '" updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update list. Check if the ID is valid or if the name is already taken.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid list ID or name for update.';
                    $message_type = 'error';
                }
                break;

            case 'delete_list':
                $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
                if ($list_id > 0) {
                    if ($list_manager->deleteList($list_id)) {
                        $message = 'List deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete list. List might not exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid list ID for deletion.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch all lists for display
$all_lists = $list_manager->getAllLists();

// --- HTML for Admin Lists Page ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Manage Lists</title>
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
        .action-buttons .view-subscribers { background-color: #28a745; color: #fff; }
        .action-buttons .view-subscribers:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Mailing Lists</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Add New List</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_list">
            <div class="form-group">
                <label for="list_name">List Name:</label>
                <input type="text" id="list_name" name="list_name" required>
            </div>
            <div class="form-group">
                <label for="list_description">Description:</label>
                <textarea id="list_description" name="list_description" rows="3"></textarea>
            </div>
            <button type="submit">Add List</button>
        </form>

        <h2>Existing Lists</h2>
        <?php if (empty($all_lists)): ?>
            <p>No mailing lists found. Add one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_lists as $list): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($list['list_id']); ?></td>
                            <td><?php echo htmlspecialchars($list['list_name']); ?></td>
                            <td><?php echo htmlspecialchars($list['list_description']); ?></td>
                            <td><?php echo htmlspecialchars($list['created_at']); ?></td>
                            <td class="action-buttons">
                                <!-- Edit Form (can be a modal or separate page) -->
                                <button class="edit" onclick="showEditForm(<?php echo $list['list_id']; ?>, '<?php echo addslashes(htmlspecialchars($list['list_name'])); ?>', '<?php echo addslashes(htmlspecialchars($list['list_description'])); ?>')">Edit</button>

                                <!-- Delete Form -->
                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this list? This will also remove all associated subscribers from this list.');">
                                    <input type="hidden" name="action" value="delete_list">
                                    <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($list['list_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                                <!-- Link to view subscribers for this list -->
                                <a href="subscribers.php?list_id=<?php echo htmlspecialchars($list['list_id']); ?>" class="view-subscribers"><button>View Subscribers</button></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit List Modal/Form (hidden by default) -->
            <div id="editListModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:400px; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit List</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_list">
                        <input type="hidden" id="edit_list_id" name="list_id">
                        <div class="form-group">
                            <label for="edit_list_name">List Name:</label>
                            <input type="text" id="edit_list_name" name="list_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_list_description">Description:</label>
                            <textarea id="edit_list_description" name="list_description" rows="3"></textarea>
                        </div>
                        <button type="submit">Update List</button>
                        <button type="button" onclick="document.getElementById('editListModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                function showEditForm(id, name, description) {
                    document.getElementById('edit_list_id').value = id;
                    document.getElementById('edit_list_name').value = name;
                    document.getElementById('edit_list_description').value = description;
                    document.getElementById('editListModal').style.display = 'flex'; // Use flex to center
                }
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
