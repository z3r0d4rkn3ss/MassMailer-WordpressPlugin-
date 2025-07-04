<?php
/**
 * Mass Mailer Admin Login Page
 *
 * Handles user authentication for the Mass Mailer admin area.
 *
 * @package Mass_Mailer
 * @subpackage Admin
 */

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure auth class is loaded
require_once dirname(__FILE__) . '/../includes/auth.php';
$auth = new MassMailerAuth();

$message = '';
$message_type = '';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($auth->login($username, $password)) {
        // Successful login, redirect to dashboard
        header('Location: dashboard.php');
        exit;
    } else {
        $message = 'Invalid username or password.';
        $message_type = 'error';
    }
}

// Handle logout request
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    $message = 'You have been logged out.';
    $message_type = 'success';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mass Mailer - Admin Login</title>
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f0f2f5; color: #333; }
        .login-container { background-color: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        h1 { color: #007cba; margin-bottom: 25px; font-size: 1.8em; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-group input[type="text"], .form-group input[type="password"] { width: calc(100% - 22px); padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 1em; }
        button { background-color: #007cba; color: #fff; border: none; padding: 12px 25px; border-radius: 5px; cursor: pointer; font-size: 1.1em; transition: background-color 0.2s; width: 100%; }
        button:hover { background-color: #005f93; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Mass Mailer Admin Login</h1>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login_submit">Login</button>
        </form>
    </div>
</body>
</html>
