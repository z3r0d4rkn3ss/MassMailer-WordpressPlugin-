<?php
/**
 * Mass Mailer Admin Reports Page
 *
 * This file provides the user interface for comprehensive reporting and analytics.
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
if (!class_exists('MassMailerTracker')) {
    require_once dirname(__FILE__) . '/../includes/tracker.php';
    require_once dirname(__FILE__) . '/../includes/subscriber-manager.php';
    require_once dirname(__FILE__) . '/../includes/campaign-manager.php';
    require_once dirname(__FILE__) . '/../includes/list-manager.php';
    require_once dirname(__FILE__) . '/../includes/bounce-handler.php';
}

$tracker = new MassMailerTracker();
$subscriber_manager = new MassMailerSubscriberManager();
$campaign_manager = new MassMailerCampaignManager();
$list_manager = new MassMailerListManager();
$bounce_handler = new MassMailerBounceHandler();

$message = '';
$message_type = '';

// Fetch overall stats
$overall_email_stats = $tracker->getOverallEmailStats();
$subscriber_growth_data = $tracker->getSubscriberGrowthOverTime();
$top_campaigns_opens = $tracker->getTopPerformingCampaigns('opens', 5);
$top_campaigns_clicks = $tracker->getTopPerformingCampaigns('clicks', 5);
$overall_bounce_stats = $tracker->getOverallBounceStats();
$subscriber_status_distribution = $subscriber_manager->getSubscriberStatusDistribution();

// Helper to format numbers
function format_number($num) {
    return number_format($num);
}

// Helper to calculate percentage
function calculate_percentage($part, $total) {
    if ($total == 0) return '0.00%';
    return number_format(($part / $total) * 100, 2) . '%';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Reports</title>
    <!-- Basic Admin CSS -->
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        h2 { color: #007cba; margin-top: 30px; margin-bottom: 20px; border-bottom: 1px dashed #eee; padding-bottom: 10px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background-color: #e9f7fe; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #d0effd; }
        .stat-card h3 { margin-top: 0; color: #005f93; font-size: 1.1em; }
        .stat-card .value { font-size: 2.2em; font-weight: bold; color: #007cba; }
        .stat-card .percentage { font-size: 1.2em; color: #28a745; }
        .stat-card .percentage.negative { color: #dc3545; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 30px; }
        table th, table td { border: 1px solid #eee; padding: 10px; text-align: left; }
        table th { background-color: #f2f2f2; font-weight: 600; color: #555; }
        table tr:nth-child(even) { background-color: #f9f9f9; }

        .chart-container { background-color: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #eee; margin-bottom: 30px; }
        .chart-container canvas { max-width: 100%; height: 300px; } /* Fixed height for charts */
    </style>
    <!-- Include Chart.js for graphs -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <h1>Comprehensive Reports</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>Overall Email Performance</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Campaigns Sent</h3>
                <div class="value"><?php echo format_number($overall_email_stats['total_campaigns_sent']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Emails Sent</h3>
                <div class="value"><?php echo format_number($overall_email_stats['total_emails_sent']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Opens</h3>
                <div class="value"><?php echo format_number($overall_email_stats['total_opens']); ?></div>
                <div class="percentage"><?php echo calculate_percentage($overall_email_stats['total_opens'], $overall_email_stats['total_emails_sent']); ?> Open Rate</div>
            </div>
            <div class="stat-card">
                <h3>Total Clicks</h3>
                <div class="value"><?php echo format_number($overall_email_stats['total_clicks']); ?></div>
                <div class="percentage"><?php echo calculate_percentage($overall_email_stats['total_clicks'], $overall_email_stats['total_emails_sent']); ?> Click Rate</div>
            </div>
            <div class="stat-card">
                <h3>Total Unique Clicks</h3>
                <div class="value"><?php echo format_number($overall_email_stats['total_unique_clicks']); ?></div>
                <div class="percentage"><?php echo calculate_percentage($overall_email_stats['total_unique_clicks'], $overall_email_stats['total_opens']); ?> Click-to-Open Rate</div>
            </div>
        </div>

        <h2>Subscriber Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Subscribers</h3>
                <div class="value"><?php echo format_number($subscriber_status_distribution['total_subscribers'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Subscribed</h3>
                <div class="value"><?php echo format_number($subscriber_status_distribution['subscribed'] ?? 0); ?></div>
                <div class="percentage"><?php echo calculate_percentage($subscriber_status_distribution['subscribed'] ?? 0, $subscriber_status_distribution['total_subscribers'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Unsubscribed</h3>
                <div class="value"><?php echo format_number($subscriber_status_distribution['unsubscribed'] ?? 0); ?></div>
                <div class="percentage negative"><?php echo calculate_percentage($subscriber_status_distribution['unsubscribed'] ?? 0, $subscriber_status_distribution['total_subscribers'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Bounced</h3>
                <div class="value"><?php echo format_number($subscriber_status_distribution['bounced'] ?? 0); ?></div>
                <div class="percentage negative"><?php echo calculate_percentage($subscriber_status_distribution['bounced'] ?? 0, $subscriber_status_distribution['total_subscribers'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="value"><?php echo format_number($subscriber_status_distribution['pending'] ?? 0); ?></div>
                <div class="percentage"><?php echo calculate_percentage($subscriber_status_distribution['pending'] ?? 0, $subscriber_status_distribution['total_subscribers'] ?? 0); ?></div>
            </div>
        </div>

        <h2>Subscriber Growth Over Time</h2>
        <div class="chart-container">
            <canvas id="subscriberGrowthChart"></canvas>
        </div>

        <h2>Top 5 Campaigns by Opens</h2>
        <?php if (empty($top_campaigns_opens)): ?>
            <p>No campaign data available yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Campaign Name</th>
                        <th>Sent</th>
                        <th>Opens</th>
                        <th>Open Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_campaigns_opens as $campaign): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($campaign['campaign_name']); ?></td>
                            <td><?php echo format_number($campaign['sent_count']); ?></td>
                            <td><?php echo format_number($campaign['open_count']); ?></td>
                            <td><?php echo calculate_percentage($campaign['open_count'], $campaign['sent_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Top 5 Campaigns by Clicks</h2>
        <?php if (empty($top_campaigns_clicks)): ?>
            <p>No campaign data available yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Campaign Name</th>
                        <th>Sent</th>
                        <th>Unique Clicks</th>
                        <th>Click Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_campaigns_clicks as $campaign): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($campaign['campaign_name']); ?></td>
                            <td><?php echo format_number($campaign['sent_count']); ?></td>
                            <td><?php echo format_number($campaign['unique_click_count']); ?></td>
                            <td><?php echo calculate_percentage($campaign['unique_click_count'], $campaign['sent_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Overall Bounce Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Bounces Logged</h3>
                <div class="value"><?php echo format_number($overall_bounce_stats['total_bounces']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Hard Bounces</h3>
                <div class="value"><?php echo format_number($overall_bounce_stats['hard_bounces']); ?></div>
                <div class="percentage negative"><?php echo calculate_percentage($overall_bounce_stats['hard_bounces'], $overall_bounce_stats['total_bounces']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Soft Bounces</h3>
                <div class="value"><?php echo format_number($overall_bounce_stats['soft_bounces']); ?></div>
                <div class="percentage"><?php echo calculate_percentage($overall_bounce_stats['soft_bounces'], $overall_bounce_stats['total_bounces']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Complaints</h3>
                <div class="value"><?php echo format_number($overall_bounce_stats['complaints']); ?></div>
                <div class="percentage negative"><?php echo calculate_percentage($overall_bounce_stats['complaints'], $overall_bounce_stats['total_bounces']); ?></div>
            </div>
        </div>


        <script>
            // Data for Subscriber Growth Chart
            const subscriberGrowthData = <?php echo json_encode($subscriber_growth_data); ?>;
            const growthLabels = subscriberGrowthData.map(row => row.date);
            const growthCounts = subscriberGrowthData.map(row => row.count);

            const subscriberGrowthCtx = document.getElementById('subscriberGrowthChart').getContext('2d');
            new Chart(subscriberGrowthCtx, {
                type: 'line',
                data: {
                    labels: growthLabels,
                    datasets: [{
                        label: 'New Subscribers',
                        data: growthCounts,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Subscribers'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Subscriber Growth Over Time'
                        }
                    }
                }
            });
        </script>
    </div>
</body>
</html>
