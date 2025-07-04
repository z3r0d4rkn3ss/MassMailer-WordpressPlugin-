<?php
/**
 * Mass Mailer Frontend Subscription Form Builder - Double Opt-in Notice
 *
 * This file generates the HTML for the subscription form, now updated
 * to include a notice about double opt-in.
 *
 * @package Mass_Mailer
 * @subpackage Views
 */

// Ensure no direct access if it's not included via mass-mailer.php
if (!defined('ABSPATH')) {
    // Define a fallback for standalone testing if needed, but typically included via mass-mailer.php
    // define('ABSPATH', dirname(__FILE__) . '/../');
    // For this context, we assume it's always included.
}

// $atts array is passed from mass_mailer_subscription_form() function
// $atts = array_merge([
//     'list_id' => null,
//     'title' => 'Subscribe to Our Newsletter',
//     'description' => 'Stay updated with our latest news and offers!',
//     'show_name_fields' => true,
// ], $atts);

// Note: This file is included by mass-mailer.php, so $atts is available.
// No need for direct file includes here.

?>
<style>
    .mass-mailer-form-container {
        font-family: 'Inter', sans-serif;
        max-width: 450px;
        margin: 20px auto;
        padding: 30px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .mass-mailer-form-container h2 {
        color: #007cba;
        text-align: center;
        margin-bottom: 20px;
    }
    .mass-mailer-form-container p {
        text-align: center;
        color: #555;
        margin-bottom: 25px;
        line-height: 1.5;
    }
    .mass-mailer-form-group {
        margin-bottom: 15px;
    }
    .mass-mailer-form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    .mass-mailer-form-group input[type="text"],
    .mass-mailer-form-group input[type="email"] {
        width: calc(100% - 22px);
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 1em;
    }
    .mass-mailer-form-group input[type="text"]:focus,
    .mass-mailer-form-group input[type="email"]:focus {
        border-color: #007cba;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
    }
    .mass-mailer-submit-btn {
        width: 100%;
        padding: 12px;
        background-color: #007cba;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-size: 1.1em;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .mass-mailer-submit-btn:hover {
        background-color: #005f93;
    }
    .mass-mailer-message {
        margin-top: 20px;
        padding: 10px;
        border-radius: 5px;
        text-align: center;
        font-weight: bold;
        display: none; /* Hidden by default, shown by JS */
    }
    .mass-mailer-message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .mass-mailer-message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .mass-mailer-privacy-notice {
        font-size: 0.85em;
        color: #777;
        margin-top: 20px;
        text-align: center;
        line-height: 1.4;
    }
</style>

<div class="mass-mailer-form-container">
    <h2><?php echo htmlspecialchars($atts['title']); ?></h2>
    <p><?php echo htmlspecialchars($atts['description']); ?></p>

    <form id="massMailerSubscriptionForm" action="<?php echo htmlspecialchars(rtrim($this->settings_manager->getSetting('tracking_base_url'), '/') . '/mass-mailer.php?action=mass_mailer_subscribe'); ?>" method="POST">
        <?php if ($atts['list_id']): ?>
            <input type="hidden" name="list_id" value="<?php echo htmlspecialchars($atts['list_id']); ?>">
        <?php endif; ?>

        <?php if ($atts['show_name_fields']): ?>
            <div class="mass-mailer-form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" placeholder="Your first name">
            </div>
            <div class="mass-mailer-form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" placeholder="Your last name">
            </div>
        <?php endif; ?>

        <div class="mass-mailer-form-group">
            <label for="email">Email Address: <span style="color: red;">*</span></label>
            <input type="email" id="email" name="email" placeholder="your@example.com" required>
        </div>

        <button type="submit" class="mass-mailer-submit-btn" name="mass_mailer_form_submit">Subscribe</button>

        <div id="massMailerMessage" class="mass-mailer-message"></div>

        <p class="mass-mailer-privacy-notice">
            By subscribing, you agree to receive emails from us. We use a double opt-in process to confirm your subscription. Please check your inbox for a confirmation email after submitting. You can unsubscribe at any time.
        </p>
    </form>
</div>

<script>
    document.getElementById('massMailerSubscriptionForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        const form = e.target;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('massMailerMessage');

        messageDiv.style.display = 'none'; // Hide previous messages
        messageDiv.classList.remove('success', 'error'); // Clear previous styles

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            messageDiv.textContent = data.message;
            messageDiv.style.display = 'block';
            if (data.success) {
                messageDiv.classList.add('success');
                form.reset(); // Clear form on success
            } else {
                messageDiv.classList.add('error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.textContent = 'An unexpected error occurred. Please try again.';
            messageDiv.classList.add('error');
            messageDiv.style.display = 'block';
        });
    });
</script>
