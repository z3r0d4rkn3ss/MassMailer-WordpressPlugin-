<?php
/**
 * Mass Mailer Public API Endpoint
 *
 * This file serves as the entry point for external applications to interact
 * with the Mass Mailer system programmatically.
 *
 * Authentication: Requires an API key passed in the 'X-API-Key' header.
 *
 * @package Mass_Mailer
 * @subpackage API
 */

// Define ABSPATH for consistent pathing if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Include core configuration and necessary managers
require_once ABSPATH . 'config.php';
require_once ABSPATH . 'includes/db.php';
require_once ABSPATH . 'includes/api-manager.php';
require_once ABSPATH . 'includes/subscriber-manager.php';
require_once ABSPATH . 'includes/campaign-manager.php';
require_once ABSPATH . 'includes/tracker.php';
require_once ABSPATH . 'includes/list-manager.php'; // Needed for add_subscriber to list

// Initialize managers
$api_manager = new MassMailerAPIManager();
$subscriber_manager = new MassMailerSubscriberManager();
$campaign_manager = new MassMailerCampaignManager();
$tracker = new MassMailerTracker();
$list_manager = new MassMailerListManager();

// Set content type for JSON response
header('Content-Type: application/json');

// --- API Key Authentication ---
$api_key_header = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($api_key_header)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'API Key is missing.']);
    exit;
}

$valid_key = $api_manager->validateApiKey($api_key_header);
if (!$valid_key) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive API Key.']);
    exit;
}

// --- Request Parsing ---
$request_method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ''; // API action is typically specified in the URL query parameter
$data = json_decode(file_get_contents('php://input'), true); // Get JSON payload for POST/PUT

$response = ['status' => 'error', 'message' => 'Invalid API request.'];

switch ($action) {
    case 'add_subscriber':
        if ($request_method === 'POST') {
            $email = $data['email'] ?? null;
            $first_name = $data['first_name'] ?? null;
            $last_name = $data['last_name'] ?? null;
            $list_id = $data['list_id'] ?? null; // Optional: add to a specific list

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['status' => 'error', 'message' => 'Valid email is required.'];
            } else {
                $subscriber_id = $subscriber_manager->addOrUpdateSubscriber($email, $first_name, $last_name, 'subscribed');
                if ($subscriber_id) {
                    if ($list_id) {
                        $list = $list_manager->getList($list_id);
                        if ($list) {
                            $added_to_list = $subscriber_manager->addSubscriberToList($subscriber_id, $list_id, 'active');
                            if ($added_to_list) {
                                $response = ['status' => 'success', 'message' => 'Subscriber added and linked to list.', 'subscriber_id' => $subscriber_id];
                            } else {
                                $response = ['status' => 'error', 'message' => 'Subscriber added, but failed to link to list.'];
                            }
                        } else {
                            $response = ['status' => 'error', 'message' => 'Subscriber added, but specified list does not exist.'];
                        }
                    } else {
                        $response = ['status' => 'success', 'message' => 'Subscriber added.', 'subscriber_id' => $subscriber_id];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to add or update subscriber.'];
                }
            }
        } else {
            http_response_code(405); // Method Not Allowed
            $response = ['status' => 'error', 'message' => 'Method not allowed for this action. Use POST.'];
        }
        break;

    case 'get_campaign_stats':
        if ($request_method === 'GET') {
            $campaign_id = $_GET['campaign_id'] ?? null;
            if (empty($campaign_id) || !is_numeric($campaign_id)) {
                $response = ['status' => 'error', 'message' => 'Campaign ID is required.'];
            } else {
                $stats = $tracker->getCampaignStats(intval($campaign_id));
                if ($stats) {
                    $response = ['status' => 'success', 'data' => $stats];
                } else {
                    $response = ['status' => 'error', 'message' => 'Campaign not found or no stats available.'];
                }
            }
        } else {
            http_response_code(405); // Method Not Allowed
            $response = ['status' => 'error', 'message' => 'Method not allowed for this action. Use GET.'];
        }
        break;

    case 'get_subscriber_status':
        if ($request_method === 'GET') {
            $email = $_GET['email'] ?? null;
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['status' => 'error', 'message' => 'Valid email is required.'];
            } else {
                $subscriber = $subscriber_manager->getSubscriber($email);
                if ($subscriber) {
                    $response = ['status' => 'success', 'data' => ['email' => $subscriber['email'], 'status' => $subscriber['status']]];
                } else {
                    $response = ['status' => 'error', 'message' => 'Subscriber not found.'];
                }
            }
        } else {
            http_response_code(405); // Method Not Allowed
            $response = ['status' => 'error', 'message' => 'Method not allowed for this action. Use GET.'];
        }
        break;

    // Add more API actions here as needed (e.g., create_campaign, get_lists, unsubscribe_subscriber)
    // Example: Unsubscribe a subscriber via API
    case 'unsubscribe_subscriber':
        if ($request_method === 'POST') {
            $email = $data['email'] ?? null;
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['status' => 'error', 'message' => 'Valid email is required.'];
            } else {
                $subscriber = $subscriber_manager->getSubscriber($email);
                if ($subscriber) {
                    if ($subscriber_manager->addOrUpdateSubscriber($email, null, null, 'unsubscribed')) {
                        $response = ['status' => 'success', 'message' => 'Subscriber unsubscribed.'];
                    } else {
                        $response = ['status' => 'error', 'message' => 'Failed to unsubscribe subscriber.'];
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Subscriber not found.'];
                }
            }
        } else {
            http_response_code(405); // Method Not Allowed
            $response = ['status' => 'error', 'message' => 'Method not allowed for this action. Use POST.'];
        }
        break;

    default:
        http_response_code(400); // Bad Request
        $response = ['status' => 'error', 'message' => 'Unknown API action.'];
        break;
}

echo json_encode($response);
exit;
