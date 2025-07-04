<?php
/**
 * Mass Mailer Frontend Form Builder View
 *
 * This file contains the HTML structure for the Mass Mailer subscription form.
 * It is designed to be included by mass-mailer.php or a similar entry point.
 *
 * @package Mass_Mailer
 * @subpackage Views
 */

// Ensure $atts are available, passed from the calling function (e.g., mass_mailer_subscription_form)
if (!isset($atts)) {
    $atts = []; // Default empty array if not passed
}

// Extract attributes with defaults
$title = isset($atts['title']) ? $atts['title'] : 'Subscribe to Our Newsletter';
$description = isset($atts['description']) ? $atts['description'] : 'Stay updated with our latest news and offers!';
$show_name_fields = isset($atts['show_name_fields']) ? (bool)$atts['show_name_fields'] : true;
$list_id = isset($atts['list_id']) ? intval($atts['list_id']) : null; // Hidden field for specific list subscription

// --- Include the CSS file (ensure path is correct relative to your HTML page) ---
// In a real application, you would enqueue this CSS correctly based on your framework.
// For a simple HTML page, you might link it directly in the <head>.
// For this example, we'll assume it's linked in the main page where this form is displayed.
// <link rel="stylesheet" href="/path/to/mass-mailer/assets/css/form.css">
?>

<div class="mass-mailer-form-wrap">
    <?php if (!empty($title)) : ?>
        <h2><?php echo htmlspecialchars($title); ?></h2>
    <?php endif; ?>

    <?php if (!empty($description)) : ?>
        <p class="mass-mailer-form-description"><?php echo htmlspecialchars($description); ?></p>
    <?php endif; ?>

    <div class="mass-mailer-form-messages" id="mass-mailer-form-messages">
        <!-- Messages (success/error) will be displayed here via JavaScript -->
    </div>

    <form id="mass-mailer-subscription-form" class="mass-mailer-subscription-form" method="POST" action="">
        <?php if ($show_name_fields) : ?>
            <div class="mass-mailer-form-field">
                <label for="mass_mailer_first_name">First Name</label>
                <input type="text" id="mass_mailer_first_name" name="first_name" placeholder="Your first name">
            </div>

            <div class="mass-mailer-form-field">
                <label for="mass_mailer_last_name">Last Name</label>
                <input type="text" id="mass_mailer_last_name" name="last_name" placeholder="Your last name">
            </div>
        <?php endif; ?>

        <div class="mass-mailer-form-field">
            <label for="mass_mailer_email">Email Address <span class="required">*</span></label>
            <input type="email" id="mass_mailer_email" name="email" placeholder="your.email@example.com" required>
        </div>

        <?php if ($list_id) : ?>
            <input type="hidden" name="list_id" value="<?php echo intval($list_id); ?>">
        <?php endif; ?>

        <div class="mass-mailer-form-submit">
            <button type="submit" name="mass_mailer_form_submit">Subscribe</button>
        </div>
    </form>
</div>

<!--
    IMPORTANT: The JavaScript file (form-handler.js) needs to be loaded
    after this HTML in your main page, typically before the closing </body> tag.
    <script src="/path/to/mass-mailer/assets/js/form-handler.js"></script>
-->
