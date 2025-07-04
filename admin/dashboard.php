<?php
/**
 * Mass Mailer Admin Dashboard Page
 *
 * This file serves as the main entry point for the Mass Mailer admin area.
 * It provides an overview and navigation links to other admin pages.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// This is a placeholder for your main admin dashboard.
// In a real application, you'd likely have authentication checks here.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Dashboard</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 20px; background-color: #f0f2f5; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h1 { color: #007cba; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 30px; }
        .nav-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .nav-item { background-color: #e9f7fe; padding: 25px; border-radius: 8px; text-align: center; border: 1px solid #d0effd; transition: transform 0.2s, box-shadow 0.2s; }
        .nav-item:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .nav-item a { text-decoration: none; color: #007cba; font-weight: bold; font-size: 1.2em; display: block; }
        .nav-item p { margin-top: 10px; color: #555; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mass Mailer Dashboard</h1>

        <div class="nav-grid">
            <div class="nav-item">
                <a href="lists.php">Manage Lists</a>
                <p>Create, edit, and view your subscriber lists.</p>
            </div>
            <div class="nav-item">
                <a href="subscribers.php">Manage Subscribers</a>
                <p>Add, edit, and view individual subscribers.</p>
            </div>
            <div class="nav-item">
                <a href="templates.php">Manage Templates</a>
                <p>Design and manage your email templates.</p>
            </div>
            <div class="nav-item">
                <a href="campaigns.php">Manage Campaigns</a>
                <p>Create, schedule, and send email campaigns.</p>
            </div>
            <div class="nav-item">
                <a href="analytics.php">View Analytics</a>
                <p>Track opens, clicks, and campaign performance.</p>
            </div>
            <div class="nav-item">
                <a href="automations.php">Manage Automations</a>
                <p>Set up automated email workflows.</p>
            </div>
            <div class="nav-item">
                <a href="settings.php">Settings</a>
                <p>Configure plugin settings (e.g., sender info).</p>
            </div>
            <!-- Add more navigation items as needed -->
        </div>
    </div>
</body>
</html>
