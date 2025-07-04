Mass Mailer - A Comprehensive Email Marketing Plugin for WordPress

Mass Mailer is a powerful and flexible WordPress plugin designed to help you manage your email marketing efforts directly from your WordPress dashboard. Create mailing lists, manage subscribers, design beautiful email templates, send campaigns, track performance, and automate your email workflows.
Features

    Mailing List Management: Create and organize multiple mailing lists.

    Subscriber Management: Add, edit, and delete subscribers. Includes double opt-in for consent management.

    Email Template Designer: Create and manage HTML and plain-text email templates with a basic built-in editor.

    Campaign Management: Create, schedule, and send email campaigns to specific lists or segments.

    Subscriber Segmentation: Define dynamic segments based on various criteria (e.g., list membership, subscription status, custom fields).

    A/B Testing: Optimize campaigns by testing different subject lines or content variants.

    Email Automations: Set up automated email sequences based on triggers (e.g., new subscription, specific actions).

    Tracking & Analytics: Track email opens, clicks, and view comprehensive reports on campaign performance.

    Bounce & Complaint Handling: Process bounced emails and complaints to maintain list hygiene (requires IMAP setup).

    API Integration: Programmatic access to core functionalities via a secure API.

    GDPR Compliance Tools: Features for data export (Right to Access) and data erasure (Right to be Forgotten), along with double opt-in.

    WordPress Integration: Leverages WordPress's database, cron system, and admin interface.

Installation

    Download the plugin files: Obtain all the plugin files and folders (e.g., mass-mailer/, mass-mailer/admin/, mass-mailer/includes/, mass-mailer/assets/, mass-mailer/css/, mass-mailer/views/).

    Upload to WordPress:

        Connect to your WordPress site via FTP/SFTP or use your hosting provider's file manager.

        Navigate to the wp-content/plugins/ directory.

        Upload the entire mass-mailer folder (containing all the plugin's files) into wp-content/plugins/.

    Activate the Plugin:

        Log in to your WordPress admin dashboard.

        Go to Plugins > Installed Plugins.

        Locate "Mass Mailer" in the list and click "Activate".

        Upon activation, the plugin will automatically create all necessary database tables and set up default configurations.

Configuration

After activation, navigate to the "Mass Mailer" menu in your WordPress admin sidebar.

    Initial Login:

        The plugin creates a default admin user if none exists.

        IMPORTANT: For security, immediately change the default admin password (password123) after your first successful login. You might need to do this via your WordPress user management or directly in the mm_users table if a dedicated user management page isn't available yet.

    General Settings:

        Go to Mass Mailer > Settings.

        Default From Name & Email: Set the sender name and email address for your campaigns.

        Tracking Base URL: Ensure this URL is correctly set to the root of your mass-mailer plugin directory (e.g., http://yourdomain.com/wp-content/plugins/mass-mailer/). This is crucial for open, click, and unsubscribe tracking.

    Mailer Settings (SMTP Recommended):

        By default, the plugin might use PHP's mail() function, which is often unreliable.

        To use SMTP (recommended for better deliverability):

            Download PHPMailer: Get the latest stable version of PHPMailer.

            Place Files: Extract the src directory from the PHPMailer download and place it inside your plugin folder, for example, wp-content/plugins/mass-mailer/vendor/PHPMailer/src/.

            Uncomment Code: You might need to manually uncomment and adjust the require_once and use statements in mass-mailer/includes/mailer.php to point to your PHPMailer installation.

            Configure in Settings: In Mass Mailer > Settings, set the "Mailer Type" to "SMTP" and fill in your SMTP Host, Port, Username, Password, and Encryption details.

    Bounce Handling (IMAP):

        If you wish to automatically process bounced emails, enable "Bounce Handling" in Mass Mailer > Settings.

        PHP IMAP Extension: Ensure the php-imap extension is installed and enabled on your web server. You might need to contact your hosting provider or edit your php.ini (extension=imap or extension=php_imap.dll).

        IMAP Credentials: Provide your IMAP Host, Port, Username, Password, and Flags in the settings.

Usage

    Admin Panel: Access all plugin features via the "Mass Mailer" menu in your WordPress admin sidebar.

    Subscription Form: Use the [mass_mailer_form] shortcode in any WordPress post or page to display a customizable subscription form.

        Example: [mass_mailer_form list_id="1" title="Join Our Newsletter" description="Get our latest updates!" show_name_fields="true"]

Troubleshooting

    Plugin Not Activating / Database Errors:

        Ensure your WordPress wp-config.php has correct database credentials.

        Check your web server's PHP error logs for specific database connection errors.

        Verify that your MySQL version is 5.7.8 or higher, as the plugin uses JSON column types.

    Admin Pages are Blank/Not Working:

        This usually indicates missing or empty plugin files. Ensure all files from the admin/ directory (and other directories) are correctly uploaded and contain their full code.

    Emails Not Sending:

        PHPMailer Integration: Confirm you have downloaded PHPMailer, placed it correctly, and uncommented/adjusted the require_once and use statements in mass-mailer/includes/mailer.php.

        SMTP Settings: Double-check your SMTP host, port, username, password, and encryption in Mass Mailer > Settings.

        PHP mail() function: If not using SMTP, ensure your server's mail() function is configured and working.

        Check your server's mail logs for sending errors.

    Tracking Not Working (Opens/Clicks/Unsubscribe):

        Verify the Tracking Base URL in Mass Mailer > Settings is correct and accessible.

        Ensure your mass-mailer.php file is accessible directly via its URL (e.g., http://yourdomain.com/wp-content/plugins/mass-mailer/mass-mailer.php).

    Cron Jobs Not Running:

        WordPress's built-in cron (wp-cron.php) relies on site visits. For more reliable sending/processing, consider setting up a real system cron job to hit wp-cron.php periodically, or directly call mass-mailer/cron-worker.php if your hosting allows.

Contributing

Contributions are welcome! Please feel free to fork the repository, make improvements, and submit pull requests.
License

This plugin is released under the GPL2 License. See the LICENSE file for more details.
