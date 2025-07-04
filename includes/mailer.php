<?php
/**
 * Mass Mailer Mailer Class - Phase 5 Updates
 *
 * This file updates the core mailer logic to integrate email open and click tracking.
 *
 * @package Mass_Mailer
 * @subpackage Includes
 */

// Ensure DB class and managers are loaded
if (!class_exists('MassMailerDB')) {
    require_once dirname(__FILE__) . '/db.php';
}
if (!class_exists('MassMailerTemplateManager')) {
    require_once dirname(__FILE__) . '/template-manager.php';
}
if (!class_exists('MassMailerSubscriberManager')) {
    require_once dirname(__FILE__) . '/subscriber-manager.php';
}
// NEW: Include Tracker Manager
if (!class_exists('MassMailerTracker')) {
    require_once dirname(__FILE__) . '/tracker.php';
}

class MassMailerMailer {
    private $db;
    private $template_manager;
    private $subscriber_manager;
    private $tracker; // New tracker instance

    // Base URL for tracking pixel and click redirects
    // IMPORTANT: Replace with your actual domain where mass-mailer.php is accessible
    // e.g., 'http://yourdomain.com/mass-mailer.php'
    const TRACKING_BASE_URL = 'http://localhost/mass-mailer/mass-mailer.php'; // Adjust this for your setup!

    public function __construct() {
        $this->db = MassMailerDB::getInstance();
        $this->template_manager = new MassMailerTemplateManager();
        $this->subscriber_manager = new MassMailerSubscriberManager();
        $this->tracker = new MassMailerTracker(); // Initialize tracker
    }

    /**
     * Sends a single email to a recipient.
     * This is a basic simulation. In production, use a proper mailer library.
     *
     * @param string $to_email The recipient's email address.
     * @param string $subject The email subject.
     * @param string $html_body The HTML content of the email.
     * @param string $text_body The plain text content of the email (optional).
     * @param array $headers Additional email headers (e.g., From, Reply-To).
     * @return bool True on success, false on failure.
     */
    public function sendEmail($to_email, $subject, $html_body, $text_body = '', $headers = []) {
        // Basic email validation
        if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            error_log('MassMailerMailer: Invalid recipient email address: ' . $to_email);
            return false;
        }

        // Default headers
        $default_headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Your Name <your_email@example.com>', // IMPORTANT: Change this to a valid sender email
            'Reply-To: your_email@example.com', // IMPORTANT: Change this
            'X-Mailer: PHP/' . phpversion()
        ];

        // Merge custom headers, allowing overrides
        foreach ($headers as $key => $value) {
            $found = false;
            foreach ($default_headers as $i => $default_header) {
                if (stripos($default_header, $key . ':') === 0) {
                    $default_headers[$i] = $key . ': ' . $value; // Update existing
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $default_headers[] = $key . ': ' . $value; // Add new
            }
        }

        $final_headers = implode("\r\n", $default_headers);

        // For plain text alternative (good practice for email clients that don't render HTML)
        $boundary = md5(time());
        $final_headers = str_replace('Content-type: text/html', 'Content-type: multipart/alternative; boundary="' . $boundary . '"', $final_headers);

        $message_body = "--" . $boundary . "\r\n";
        $message_body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message_body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message_body .= $text_body . "\r\n\r\n";

        $message_body .= "--" . $boundary . "\r\n";
        $message_body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message_body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message_body .= $html_body . "\r\n\r\n";
        $message_body .= "--" . $boundary . "--";

        // PHP's mail() function. This requires your server to be configured to send mail.
        // For robust sending, consider SMTP libraries (PHPMailer, SwiftMailer) or transactional email services (SendGrid, Mailgun).
        $mail_sent = mail($to_email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $message_body, $final_headers);

        if ($mail_sent) {
            error_log('MassMailerMailer: Email sent successfully to ' . $to_email);
            return true;
        } else {
            error_log('MassMailerMailer: Failed to send email to ' . $to_email . '. Check server mail configuration.');
            return false;
        }
    }

    /**
     * Sends an email using a saved template to a specific subscriber, with tracking.
     *
     * @param int $campaign_id The ID of the campaign this email belongs to.
     * @param int $template_id The ID of the template to use.
     * @param int $subscriber_id The ID of the subscriber to send to.
     * @param string $campaign_subject The subject line for this specific campaign.
     * @return bool True on success, false on failure.
     */
    public function sendTemplateEmailToSubscriber($campaign_id, $template_id, $subscriber_id, $campaign_subject) {
        $template = $this->template_manager->getTemplate($template_id);
        $subscriber = $this->subscriber_manager->getSubscriber($subscriber_id);

        if (!$template) {
            error_log('MassMailerMailer: Template with ID ' . $template_id . ' not found.');
            return false;
        }
        if (!$subscriber) {
            error_log('MassMailerMailer: Subscriber with ID ' . $subscriber_id . ' not found.');
            return false;
        }
        if ($subscriber['status'] !== 'subscribed') {
            error_log('MassMailerMailer: Subscriber ' . $subscriber['email'] . ' is not in "subscribed" status. Skipping email.');
            return false;
        }

        // Personalization (replace placeholders)
        $replacements = [
            '{{first_name}}'       => htmlspecialchars($subscriber['first_name'] ?? ''),
            '{{last_name}}'        => htmlspecialchars($subscriber['last_name'] ?? ''),
            '{{email}}'            => htmlspecialchars($subscriber['email']),
            '{{unsubscribe_link}}' => self::TRACKING_BASE_URL . '?action=unsubscribe&email=' . urlencode($subscriber['email']) // Unsubscribe link
        ];

        $html_body = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template['template_html']
        );

        $text_body = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template['template_text']
        );

        $final_subject = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $campaign_subject
        );

        // --- NEW: Add Tracking Pixel for Opens ---
        $tracking_pixel = '<img src="' . self::TRACKING_BASE_URL . '?action=track_open&campaign_id=' . $campaign_id . '&subscriber_id=' . $subscriber_id . '" width="1" height="1" border="0" style="display:none !important; visibility:hidden !important; mso-hide:all;">';
        $html_body = $html_body . $tracking_pixel; // Append pixel to the end of HTML body

        // --- NEW: Wrap Links for Click Tracking ---
        $html_body = $this->wrapLinksForTracking($html_body, $campaign_id, $subscriber_id);


        return $this->sendEmail(
            $subscriber['email'],
            $final_subject,
            $html_body,
            $text_body
        );
    }

    /**
     * Wraps all anchor tags in HTML content with a click tracking URL.
     *
     * @param string $html_content The HTML content of the email.
     * @param int $campaign_id The ID of the campaign.
     * @param int $subscriber_id The ID of the subscriber.
     * @return string The HTML content with wrapped links.
     */
    private function wrapLinksForTracking($html_content, $campaign_id, $subscriber_id) {
        // Regex to find all href attributes in anchor tags
        // This regex is basic and might need refinement for complex HTML structures.
        return preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href=["\']([^"\']+)["\']([^>]*)>/i', function($matches) use ($campaign_id, $subscriber_id) {
            $original_url = $matches[1];
            $other_attributes = $matches[2];

            // Skip mailto, tel, and other non-http/https links
            if (empty($original_url) || !preg_match('/^https?:\/\//i', $original_url)) {
                return $matches[0]; // Return original tag if not an http/s link
            }

            // Encode the original URL and parameters for the tracking redirect
            $encoded_url = urlencode(base66_encode($original_url)); // Base66 is conceptual, use base64_encode for real
            $tracking_url = self::TRACKING_BASE_URL . '?action=track_click&campaign_id=' . $campaign_id . '&subscriber_id=' . $subscriber_id . '&url=' . $encoded_url;

            return '<a href="' . htmlspecialchars($tracking_url) . '"' . $other_attributes . '>';
        }, $html_content);
    }
}

/**
 * Custom Base66 encoding/decoding functions for obfuscation.
 * This is a conceptual implementation for demonstration.
 * In a real system, you might use more robust URL shortening/tracking services or encryption.
 *
 * Base66 is not a standard encoding. This is just to illustrate a concept.
 * For actual use, you should use base64_encode/decode or a dedicated URL shortener.
 */
function base66_encode($data) {
    return base64_encode($data); // Using base64 for practical purposes
}

function base66_decode($data) {
    return base64_decode($data); // Using base64 for practical purposes
}
