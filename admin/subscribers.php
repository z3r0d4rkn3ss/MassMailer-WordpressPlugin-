<?php
/**
 * Mass Mailer Admin Subscribers Page
 *
 * This file provides the user interface for managing subscribers,
 * optionally filtered by a specific mailing list, within the Mass Mailer admin area.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Ensure core files are loaded
if (!class_exists('MassMailerSubscriberManager')) {
    require_once dirname(__FILE__) . '/../includes/subscriber-manager.php';
    require_once dirname(__FILE__) . '/../includes/list-manager.php'; // Needed to get list name
}

$subscriber_manager = new MassMailerSubscriberManager();
$list_manager = new MassMailerListManager();
$message = '';
$message_type = ''; // 'success' or 'error'
$current_list_id = isset($_GET['list_id']) ? intval($_GET['list_id']) : null;
$current_list_name = 'All Subscribers';

// If a list_id is provided, try to fetch the list name
if ($current_list_id) {
    $list_info = $list_manager->getList($current_list_id);
    if ($list_info) {
        $current_list_name = htmlspecialchars($list_info['list_name']);
    } else {
        $message = 'Specified list not found.';
        $message_type = 'error';
        $current_list_id = null; // Reset to show all subscribers if list not found
    }
}


// Handle form submissions for adding/updating/deleting subscribers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_subscriber':
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
                $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'subscribed'; // Default status

                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $subscriber_id = $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, $status);
                    if ($subscriber_id) {
                        // If adding to a specific list from this page
                        if ($current_list_id) {
                            $subscriber_manager->addSubscriberToList($subscriber_id, $current_list_id, 'active');
                        }
                        $message = 'Subscriber "' . htmlspecialchars($email) . '" added/updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to add/update subscriber. Email might be invalid or a database error occurred.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Please enter a valid email address.';
                    $message_type = 'error';
                }
                break;

            case 'update_subscriber':
                $subscriber_id = isset($_POST['subscriber_id']) ? intval($_POST['subscriber_id']) : 0;
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
                $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'subscribed';

                if ($subscriber_id > 0 && !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    if ($subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, $status)) {
                        $message = 'Subscriber "' . htmlspecialchars($email) . '" updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to update subscriber. Email might be invalid or a database error occurred.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid subscriber ID or email for update.';
                    $message_type = 'error';
                }
                break;

            case 'delete_subscriber':
                $subscriber_id = isset($_POST['subscriber_id']) ? intval($_POST['subscriber_id']) : 0;
                if ($subscriber_id > 0) {
                    if ($subscriber_manager->deleteSubscriber($subscriber_id)) {
                        $message = 'Subscriber deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete subscriber. Subscriber might not exist.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid subscriber ID for deletion.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch subscribers for display
$subscribers = $subscriber_manager->getAllSubscribers($current_list_id);

// --- HTML for Admin Subscribers Page ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Manage Subscribers</title>
    <!-- Basic Admin CSS -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        form { background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #eee; }
        form label { display: block; margin-bottom: 8px; font-weight: 600; }
        form input[type="text"], form input[type="email"], form select { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
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
        .back-link { margin-bottom: 20px; display: block; text-decoration: none; color: #007cba; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Subscribers <?php echo $current_list_id ? 'for List: "' . $current_list_name . '"' : ''; ?></h1>

        <a href="lists.php" class="back-link">&larr; Back to Lists</a>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Add New Subscriber <?php echo $current_list_id ? 'to "' . $current_list_name . '"' : ''; ?></h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_subscriber">
            <?php if ($current_list_id): ?>
                <input type="hidden" name="list_id" value="<?php echo $current_list_id; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name">
            </div>
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="subscribed">Subscribed</option>
                    <option value="pending">Pending</option>
                    <option value="unsubscribed">Unsubscribed</option>
                    <option value="bounced">Bounced</option>
                </select>
            </div>
            <button type="submit">Add Subscriber</button>
        </form>

        <h2>Existing Subscribers <?php echo $current_list_id ? 'in "' . $current_list_name . '"' : ''; ?></h2>
        <?php if (empty($subscribers)): ?>
            <p>No subscribers found <?php echo $current_list_id ? 'in this list.' : 'yet.'; ?> Add one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Status</th>
                        <?php if ($current_list_id): ?>
                            <th>List Status</th>
                        <?php endif; ?>
                        <th>Subscribed At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($subscriber['subscriber_id']); ?></td>
                            <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                            <td><?php echo htmlspecialchars($subscriber['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($subscriber['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($subscriber['status']); ?></td>
                            <?php if ($current_list_id): ?>
                                <td><?php echo htmlspecialchars($subscriber['list_status']); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($subscriber['subscribed_at']); ?></td>
                            <td class="action-buttons">
                                <!-- Edit Form (can be a modal or separate page) -->
                                <button class="edit" onclick="showEditForm(
                                    <?php echo $subscriber['subscriber_id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($subscriber['email'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($subscriber['first_name'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($subscriber['last_name'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($subscriber['status'])); ?>'
                                )">Edit</button>

                                <!-- Delete Form -->
                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this subscriber? This will remove them from ALL lists.');">
                                    <input type="hidden" name="action" value="delete_subscriber">
                                    <input type="hidden" name="subscriber_id" value="<?php echo htmlspecialchars($subscriber['subscriber_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit Subscriber Modal/Form (hidden by default) -->
            <div id="editSubscriberModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:400px; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit Subscriber</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_subscriber">
                        <input type="hidden" id="edit_subscriber_id" name="subscriber_id">
                        <div class="form-group">
                            <label for="edit_email">Email Address:</label>
                            <input type="email" id="edit_email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_first_name">First Name:</label>
                            <input type="text" id="edit_first_name" name="first_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_last_name">Last Name:</label>
                            <input type="text" id="edit_last_name" name="last_name">
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status:</label>
                            <select id="edit_status" name="status">
                                <option value="subscribed">Subscribed</option>
                                <option value="pending">Pending</option>
                                <option value="unsubscribed">Unsubscribed</option>
                                <option value="bounced">Bounced</option>
                            </select>
                        </div>
                        <button type="submit">Update Subscriber</button>
                        <button type="button" onclick="document.getElementById('editSubscriberModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                function showEditForm(id, email, firstName, lastName, status) {
                    document.getElementById('edit_subscriber_id').value = id;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_first_name').value = firstName;
                    document.getElementById('edit_last_name').value = lastName;
                    document.getElementById('edit_status').value = status;
                    document.getElementById('editSubscriberModal').style.display = 'flex'; // Use flex to center
                }
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
