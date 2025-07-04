<?php
/**
 * Mass Mailer Admin Analytics Page
 *
 * This file provides the user interface for viewing campaign analytics
 * (opens, clicks, etc.) within the Mass Mailer admin area.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Ensure core files are loaded
if (!class_exists('MassMailerCampaignManager')) {
    require_once dirname(__FILE__) . '/../includes/campaign-manager.php';
    require_once dirname(__FILE__) . '/../includes/tracker.php';
}

$campaign_manager = new MassMailerCampaignManager();
$tracker = new MassMailerTracker();

$message = '';
$message_type = '';

$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;
$current_campaign = null;
$campaign_stats = [];
$campaign_opens = [];
$campaign_clicks = [];

if ($campaign_id > 0) {
    $current_campaign = $campaign_manager->getCampaign($campaign_id);
    if ($current_campaign) {
        $campaign_stats = [
            'total_recipients' => $current_campaign['total_recipients'],
            'sent_count' => $current_campaign['sent_count'],
            'open_count' => $tracker->getCampaignOpenCount($campaign_id),
            'click_count' => $tracker->getCampaignClickCount($campaign_id),
            'unique_click_count' => $tracker->getCampaignUniqueClickCount($campaign_id),
        ];
        $campaign_opens = $tracker->getCampaignOpens($campaign_id);
        $campaign_clicks = $tracker->getCampaignClicks($campaign_id);

        // Calculate rates
        $campaign_stats['open_rate'] = ($campaign_stats['sent_count'] > 0) ?
            round(($campaign_stats['open_count'] / $campaign_stats['sent_count']) * 100, 2) : 0;
        $campaign_stats['click_rate'] = ($campaign_stats['open_count'] > 0) ? // Click rate based on opens
            round(($campaign_stats['click_count'] / $campaign_stats['open_count']) * 100, 2) : 0;
        $campaign_stats['overall_click_rate'] = ($campaign_stats['sent_count'] > 0) ? // Overall click rate based on sent
            round(($campaign_stats['click_count'] / $campaign_stats['sent_count']) * 100, 2) : 0;

    } else {
        $message = 'Campaign not found.';
        $message_type = 'error';
        $campaign_id = 0; // Reset to show overall stats or no campaign selected
    }
}

// Fetch all campaigns for the dropdown
$all_campaigns = $campaign_manager->getAllCampaigns();

// --- HTML for Admin Analytics Page ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Analytics</title>
    <!-- Basic Admin CSS -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1000px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .campaign-selector { margin-bottom: 30px; padding: 20px; background-color: #f9f9f9; border-radius: 8px; border: 1px solid #eee; }
        .campaign-selector label { font-weight: 600; margin-right: 10px; }
        .campaign-selector select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background-color: #e9f7fe; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #d0effd; }
        .stat-box h3 { margin-top: 0; color: #007cba; font-size: 1.2em; }
        .stat-box .value { font-size: 2.5em; font-weight: bold; color: #333; margin-top: 5px; }
        .stat-box .percentage { font-size: 1.5em; font-weight: bold; color: #28a745; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { border: 1px solid #eee; padding: 12px; text-align: left; }
        table th { background-color: #f2f2f2; font-weight: 600; color: #555; }
        table tr:nth-child(even) { background-color: #f9f9f9; }
        .section-title { margin-top: 40px; margin-bottom: 20px; color: #007cba; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Campaign Analytics</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="campaign-selector">
            <label for="campaign_select">Select Campaign:</label>
            <select id="campaign_select" onchange="if(this.value) window.location.href = 'analytics.php?campaign_id=' + this.value;">
                <option value="">-- Select a Campaign --</option>
                <?php foreach ($all_campaigns as $campaign): ?>
                    <option value="<?php echo htmlspecialchars($campaign['campaign_id']); ?>"
                        <?php echo ($campaign_id == $campaign['campaign_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($campaign['campaign_name']); ?> (ID: <?php echo htmlspecialchars($campaign['campaign_id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($current_campaign): ?>
            <h2>Analytics for "<?php echo htmlspecialchars($current_campaign['campaign_name']); ?>"</h2>

            <div class="stats-grid">
                <div class="stat-box">
                    <h3>Total Recipients</h3>
                    <div class="value"><?php echo htmlspecialchars($campaign_stats['total_recipients']); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Emails Sent</h3>
                    <div class="value"><?php echo htmlspecialchars($campaign_stats['sent_count']); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Unique Opens</h3>
                    <div class="value"><?php echo htmlspecialchars($campaign_stats['open_count']); ?></div>
                    <div class="percentage"><?php echo htmlspecialchars($campaign_stats['open_rate']); ?>% Open Rate</div>
                </div>
                <div class="stat-box">
                    <h3>Total Clicks</h3>
                    <div class="value"><?php echo htmlspecialchars($campaign_stats['click_count']); ?></div>
                    <div class="percentage"><?php echo htmlspecialchars($campaign_stats['overall_click_rate']); ?>% Overall Click Rate</div>
                </div>
                <div class="stat-box">
                    <h3>Unique Clicks</h3>
                    <div class="value"><?php echo htmlspecialchars($campaign_stats['unique_click_count']); ?></div>
                    <div class="percentage"><?php echo htmlspecialchars($campaign_stats['click_rate']); ?>% Click-Through Rate</div>
                </div>
            </div>

            <h2 class="section-title">Recent Opens</h2>
            <?php if (empty($campaign_opens)): ?>
                <p>No opens recorded for this campaign yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Subscriber Email</th>
                            <th>Opened At</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaign_opens as $open): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($open['email']); ?></td>
                                <td><?php echo htmlspecialchars($open['opened_at']); ?></td>
                                <td><?php echo htmlspecialchars($open['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($open['user_agent']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 class="section-title">Recent Clicks</h2>
            <?php if (empty($campaign_clicks)): ?>
                <p>No clicks recorded for this campaign yet.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Subscriber Email</th>
                            <th>Clicked At</th>
                            <th>Original URL</th>
                            <th>IP Address</th>
                            <th>User Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaign_clicks as $click): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($click['email']); ?></td>
                                <td><?php echo htmlspecialchars($click['clicked_at']); ?></td>
                                <td><a href="<?php echo htmlspecialchars($click['original_url']); ?>" target="_blank"><?php echo htmlspecialchars(substr($click['original_url'], 0, 70)) . (strlen($click['original_url']) > 70 ? '...' : ''); ?></a></td>
                                <td><?php echo htmlspecialchars($click['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($click['user_agent']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php else: ?>
            <p>Please select a campaign from the dropdown above to view its analytics.</p>
        <?php endif; ?>
    </div>
</body>
</html>
