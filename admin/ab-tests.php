<?php
/**
 * Mass Mailer Admin A/B Tests Page
 *
 * This file provides the user interface for managing A/B tests.
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
if (!class_exists('MassMailerABTestManager')) {
    require_once dirname(__FILE__) . '/../includes/ab-test-manager.php';
    require_once dirname(__FILE__) . '/../includes/campaign-manager.php'; // For campaign selection
    require_once dirname(__FILE__) . '/../includes/tracker.php'; // For fetching campaign stats
}

$ab_test_manager = new MassMailerABTestManager();
$campaign_manager = new MassMailerCampaignManager();
$tracker = new MassMailerTracker();

$message = '';
$message_type = ''; // 'success' or 'error'

// Fetch all campaigns for variant selection
$all_campaigns = $campaign_manager->getAllCampaigns();

// Filter campaigns that are not yet part of an A/B test
$available_campaigns = array_filter($all_campaigns, function($campaign) {
    return empty($campaign['ab_test_id']);
});


// Handle form submissions for adding/updating/deleting/starting/winning A/B tests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_ab_test':
            case 'update_ab_test':
                $ab_test_id = isset($_POST['ab_test_id']) ? intval($_POST['ab_test_id']) : 0;
                $test_name = isset($_POST['test_name']) ? trim($_POST['test_name']) : '';
                $test_type = isset($_POST['test_type']) ? trim($_POST['test_type']) : '';
                $variant_campaign_ids = isset($_POST['variant_campaign_ids']) ? array_map('intval', $_POST['variant_campaign_ids']) : [];
                $audience_split_percentage = isset($_POST['audience_split_percentage']) ? intval($_POST['audience_split_percentage']) : 10;
                $winner_criteria = isset($_POST['winner_criteria']) ? trim($_POST['winner_criteria']) : '';
                $status = isset($_POST['status']) ? trim($_POST['status']) : 'draft';
                $winner_campaign_id = isset($_POST['winner_campaign_id']) ? intval($_POST['winner_campaign_id']) : null;
                $remaining_audience_sent = isset($_POST['remaining_audience_sent']) ? true : false;

                if (!empty($test_name) && !empty($test_type) && count($variant_campaign_ids) === 2 && $audience_split_percentage > 0 && !empty($winner_criteria)) {
                    if ($_POST['action'] === 'add_ab_test') {
                        $new_test_id = $ab_test_manager->createABTest($test_name, $test_type, $variant_campaign_ids, $audience_split_percentage, $winner_criteria);
                        if ($new_test_id) {
                            $message = 'A/B Test "' . htmlspecialchars($test_name) . '" created successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Failed to create A/B test. Name might exist or invalid campaigns selected.';
                            $message_type = 'error';
                        }
                    } else { // update_ab_test
                        if ($ab_test_id > 0) {
                            if ($ab_test_manager->updateABTest($ab_test_id, $test_name, $test_type, $variant_campaign_ids, $audience_split_percentage, $winner_criteria, $status, $winner_campaign_id, $remaining_audience_sent)) {
                                $message = 'A/B Test "' . htmlspecialchars($test_name) . '" updated successfully!';
                                $message_type = 'success';
                            } else {
                                $message = 'Failed to update A/B test. Check ID or name conflict.';
                                $message_type = 'error';
                            }
                        } else {
                            $message = 'Invalid A/B Test ID for update.';
                            $message_type = 'error';
                        }
                    }
                } else {
                    $message = 'Missing required fields for A/B test.';
                    $message_type = 'error';
                }
                break;

            case 'delete_ab_test':
                $ab_test_id = isset($_POST['ab_test_id']) ? intval($_POST['ab_test_id']) : 0;
                if ($ab_test_id > 0) {
                    if ($ab_test_manager->deleteABTest($ab_test_id)) {
                        $message = 'A/B Test deleted successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to delete A/B test.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid A/B Test ID for deletion.';
                    $message_type = 'error';
                }
                break;

            case 'start_ab_test':
                $ab_test_id = isset($_POST['ab_test_id']) ? intval($_POST['ab_test_id']) : 0;
                if ($ab_test_id > 0) {
                    if ($ab_test_manager->startABTest($ab_test_id)) {
                        $message = 'A/B Test started successfully! Variants are being sent to a subset of your audience.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to start A/B test. Check test status or campaign validity.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid A/B Test ID for starting.';
                    $message_type = 'error';
                }
                break;

            case 'determine_winner':
                $ab_test_id = isset($_POST['ab_test_id']) ? intval($_POST['ab_test_id']) : 0;
                if ($ab_test_id > 0) {
                    $winner_campaign_id = $ab_test_manager->determineWinner($ab_test_id);
                    if ($winner_campaign_id) {
                        $winner_campaign = $campaign_manager->getCampaign($winner_campaign_id);
                        $message = 'Winner determined for A/B Test: Campaign "' . htmlspecialchars($winner_campaign['campaign_name']) . '" (ID: ' . $winner_campaign_id . ') is the winner!';
                        $message_type = 'success';
                    } else {
                        $message = 'Could not determine a clear winner for A/B Test. It might be a tie or not enough data.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid A/B Test ID for winner determination.';
                    $message_type = 'error';
                }
                break;

            case 'send_winner':
                $ab_test_id = isset($_POST['ab_test_id']) ? intval($_POST['ab_test_id']) : 0;
                if ($ab_test_id > 0) {
                    if ($ab_test_manager->sendWinnerToRemainingAudience($ab_test_id)) {
                        $message = 'Winning campaign is being sent to the remaining audience!';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to send winning campaign to remaining audience. Check test status.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid A/B Test ID for sending winner.';
                    $message_type = 'error';
                }
                break;
        }
    }
}

// Fetch all A/B tests for display
$all_ab_tests = $ab_test_manager->getAllABTests();

// Helper to get campaign name by ID
function getCampaignNameById($campaign_id, $all_campaigns) {
    foreach ($all_campaigns as $campaign) {
        if ($campaign['campaign_id'] == $campaign_id) {
            return $campaign['campaign_name'];
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
    <title>Mass Mailer - A/B Tests</title>
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
        form input[type="text"], form input[type="number"], form select { width: calc(100% - 22px); padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; }
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
        .action-buttons .start-test { background-color: #28a745; color: #fff; }
        .action-buttons .start-test:hover { background-color: #218838; }
        .action-buttons .determine-winner { background-color: #007bff; color: #fff; }
        .action-buttons .determine-winner:hover { background-color: #0056b3; }
        .action-buttons .send-winner { background-color: #6c757d; color: #fff; }
        .action-buttons .send-winner:hover { background-color: #5a6268; }
        .status-draft { color: #6c757d; }
        .status-running { color: #007bff; font-weight: bold; }
        .status-completed { color: #28a745; font-weight: bold; }
        .status-cancelled { color: #dc3545; }
        .info-text { font-size: 0.85em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage A/B Tests</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Create New A/B Test</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_ab_test">
            <div class="form-group">
                <label for="test_name">Test Name:</label>
                <input type="text" id="test_name" name="test_name" required>
            </div>
            <div class="form-group">
                <label for="test_type">Test Type:</label>
                <select id="test_type" name="test_type" required>
                    <option value="">Select Type</option>
                    <option value="subject_line">Subject Line</option>
                    <option value="content">Email Content</option>
                </select>
            </div>
            <div class="form-group">
                <label>Variant Campaigns (Must be 2, targeting the same segment):</label>
                <select name="variant_campaign_ids[]" required>
                    <option value="">Select Variant A</option>
                    <?php foreach ($available_campaigns as $campaign): ?>
                        <option value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>">
                            <?php echo htmlspecialchars($campaign['campaign_name']); ?> (Segment: <?php echo htmlspecialchars($campaign['segment_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="variant_campaign_ids[]" required style="margin-top: 10px;">
                    <option value="">Select Variant B</option>
                    <?php foreach ($available_campaigns as $campaign): ?>
                        <option value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>">
                            <?php echo htmlspecialchars($campaign['campaign_name']); ?> (Segment: <?php echo htmlspecialchars($campaign['segment_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="info-text">Create two separate campaigns (e.g., "Campaign X - Variant A" and "Campaign X - Variant B") that target the *same segment* before creating an A/B test.</p>
            </div>
            <div class="form-group">
                <label for="audience_split_percentage">Test Audience Percentage:</label>
                <input type="number" id="audience_split_percentage" name="audience_split_percentage" min="1" max="99" value="10" required>
                <p class="info-text">Percentage of the target segment to send the test variants to (e.g., 10 for 10% of audience split between variants).</p>
            </div>
            <div class="form-group">
                <label for="winner_criteria">Winner Criteria:</label>
                <select id="winner_criteria" name="winner_criteria" required>
                    <option value="">Select Criteria</option>
                    <option value="opens">Highest Opens</option>
                    <option value="clicks">Highest Clicks</option>
                </select>
            </div>
            <button type="submit">Create A/B Test</button>
        </form>

        <h2>Existing A/B Tests</h2>
        <?php if (empty($all_ab_tests)): ?>
            <p>No A/B tests found. Create one above!</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Variants</th>
                        <th>Split %</th>
                        <th>Winner Criteria</th>
                        <th>Status</th>
                        <th>Winner</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_ab_tests as $test): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($test['ab_test_id']); ?></td>
                            <td><?php echo htmlspecialchars($test['test_name']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $test['test_type']))); ?></td>
                            <td>
                                <?php
                                $variant_names = [];
                                foreach ($test['variant_campaign_ids'] as $cid) {
                                    $variant_names[] = getCampaignNameById($cid, $all_campaigns);
                                }
                                echo htmlspecialchars(implode(' vs ', $variant_names));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($test['audience_split_percentage']); ?>%</td>
                            <td><?php echo htmlspecialchars(ucfirst($test['winner_criteria'])); ?></td>
                            <td><span class="status-<?php echo htmlspecialchars($test['status']); ?>"><?php echo htmlspecialchars(ucfirst($test['status'])); ?></span></td>
                            <td>
                                <?php
                                if ($test['winner_campaign_id']) {
                                    echo htmlspecialchars(getCampaignNameById($test['winner_campaign_id'], $all_campaigns));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="action-buttons">
                                <?php if ($test['status'] === 'draft'): ?>
                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to START this A/B test? This will begin sending variants to your test audience.');">
                                        <input type="hidden" name="action" value="start_ab_test">
                                        <input type="hidden" name="ab_test_id" value="<?php echo htmlspecialchars($test['ab_test_id']); ?>">
                                        <button type="submit" class="start-test">Start Test</button>
                                    </form>
                                <?php elseif ($test['status'] === 'running'): ?>
                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to determine the winner now?');">
                                        <input type="hidden" name="action" value="determine_winner">
                                        <input type="hidden" name="ab_test_id" value="<?php echo htmlspecialchars($test['ab_test_id']); ?>">
                                        <button type="submit" class="determine-winner">Determine Winner</button>
                                    </form>
                                <?php elseif ($test['status'] === 'completed' && !$test['remaining_audience_sent']): ?>
                                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to send the winning campaign to the remaining audience?');">
                                        <input type="hidden" name="action" value="send_winner">
                                        <input type="hidden" name="ab_test_id" value="<?php echo htmlspecialchars($test['ab_test_id']); ?>">
                                        <button type="submit" class="send-winner">Send Winner</button>
                                    </form>
                                <?php endif; ?>
                                <button class="edit" onclick="showEditForm(
                                    <?php echo $test['ab_test_id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($test['test_name'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($test['test_type'])); ?>',
                                    '<?php echo addslashes(json_encode($test['variant_campaign_ids'])); ?>',
                                    <?php echo htmlspecialchars($test['audience_split_percentage']); ?>,
                                    '<?php echo addslashes(htmlspecialchars($test['winner_criteria'])); ?>',
                                    '<?php echo addslashes(htmlspecialchars($test['status'])); ?>',
                                    <?php echo $test['winner_campaign_id'] ? htmlspecialchars($test['winner_campaign_id']) : 'null'; ?>,
                                    <?php echo $test['remaining_audience_sent'] ? 'true' : 'false'; ?>
                                )">Edit</button>

                                <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this A/B test? This will NOT delete the associated campaigns.');">
                                    <input type="hidden" name="action" value="delete_ab_test">
                                    <input type="hidden" name="ab_test_id" value="<?php echo htmlspecialchars($test['ab_test_id']); ?>">
                                    <button type="submit" class="delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Edit A/B Test Modal/Form (hidden by default) -->
            <div id="editABTestModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
                <div style="background:#fff; padding:30px; border-radius:8px; width:600px; max-height: 90vh; overflow-y: auto; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3>Edit A/B Test</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_ab_test">
                        <input type="hidden" id="edit_ab_test_id" name="ab_test_id">
                        <div class="form-group">
                            <label for="edit_test_name">Test Name:</label>
                            <input type="text" id="edit_test_name" name="test_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_test_type">Test Type:</label>
                            <select id="edit_test_type" name="test_type" required>
                                <option value="subject_line">Subject Line</option>
                                <option value="content">Email Content</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Variant Campaigns (Must be 2, targeting the same segment):</label>
                            <select id="edit_variant_campaign_id_1" name="variant_campaign_ids[]" required>
                                <option value="">Select Variant A</option>
                                <?php foreach ($all_campaigns as $campaign): // Use all campaigns here as it might be an existing variant ?>
                                    <option value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>">
                                        <?php echo htmlspecialchars($campaign['campaign_name']); ?> (Segment: <?php echo htmlspecialchars($campaign['segment_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select id="edit_variant_campaign_id_2" name="variant_campaign_ids[]" required style="margin-top: 10px;">
                                <option value="">Select Variant B</option>
                                <?php foreach ($all_campaigns as $campaign): ?>
                                    <option value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>">
                                        <?php echo htmlspecialchars($campaign['campaign_name']); ?> (Segment: <?php echo htmlspecialchars($campaign['segment_name']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="info-text">Ensure selected campaigns target the same segment.</p>
                        </div>
                        <div class="form-group">
                            <label for="edit_audience_split_percentage">Test Audience Percentage:</label>
                            <input type="number" id="edit_audience_split_percentage" name="audience_split_percentage" min="1" max="99" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_winner_criteria">Winner Criteria:</label>
                            <select id="edit_winner_criteria" name="winner_criteria" required>
                                <option value="opens">Highest Opens</option>
                                <option value="clicks">Highest Clicks</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_status">Status:</label>
                            <select id="edit_status" name="status">
                                <option value="draft">Draft</option>
                                <option value="running">Running</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_winner_campaign_id">Winner Campaign ID:</label>
                            <input type="number" id="edit_winner_campaign_id" name="winner_campaign_id" readonly>
                            <p class="info-text">Automatically set when winner is determined.</p>
                        </div>
                        <div class="form-group">
                            <input type="checkbox" id="edit_remaining_audience_sent" name="remaining_audience_sent" disabled>
                            <label for="edit_remaining_audience_sent" style="display:inline;">Remaining Audience Sent</label>
                        </div>
                        <button type="submit">Update A/B Test</button>
                        <button type="button" onclick="document.getElementById('editABTestModal').style.display='none';">Cancel</button>
                    </form>
                </div>
            </div>

            <script>
                function showEditForm(id, name, type, variantCampaignIdsJson, splitPercentage, winnerCriteria, status, winnerCampaignId, remainingAudienceSent) {
                    const variantCampaignIds = JSON.parse(variantCampaignIdsJson);
                    document.getElementById('edit_ab_test_id').value = id;
                    document.getElementById('edit_test_name').value = name;
                    document.getElementById('edit_test_type').value = type;
                    document.getElementById('edit_variant_campaign_id_1').value = variantCampaignIds[0];
                    document.getElementById('edit_variant_campaign_id_2').value = variantCampaignIds[1];
                    document.getElementById('edit_audience_split_percentage').value = splitPercentage;
                    document.getElementById('edit_winner_criteria').value = winnerCriteria;
                    document.getElementById('edit_status').value = status;
                    document.getElementById('edit_winner_campaign_id').value = winnerCampaignId || '';
                    document.getElementById('edit_remaining_audience_sent').checked = remainingAudienceSent;

                    document.getElementById('editABTestModal').style.display = 'flex';
                }
            </script>

        <?php endif; ?>
    </div>
</body>
</html>
