<?php
// To include filters
require_once plugin_dir_path(__FILE__) . 'woocommerce-functions.php';

// Ensure the main log file is created and writable in the plugin directory
function ensure_main_log_file($filename = 'ninjavan-logs.txt') {
    $log_file_path = plugin_dir_path(__FILE__) . $filename; // Updated to use plugin directory
    if (!file_exists($log_file_path)) {
        file_put_contents($log_file_path, "Ninja Van Webhook Logs\n", FILE_APPEND);
    }
}

// Ensure the log file is created and writable in the plugin directory for emails
function ensure_email_log_file($filename = 'ninjavan-email-logs.txt') {
    $log_file_path = plugin_dir_path(__FILE__) . $filename;
    if (!file_exists($log_file_path)) {
        file_put_contents($log_file_path, "Email Logs\n", FILE_APPEND);
    }
}

// Separate action for handling webhook events
add_action('wp_ajax_ninja_van_webhook_receiver', 'ninja_van_webhook_receiver_handler');
add_action('wp_ajax_nopriv_ninja_van_webhook_receiver', 'ninja_van_webhook_receiver_handler');

function ninja_van_webhook_receiver_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ninja_van_logs';

    ensure_main_log_file(); // Ensure the main log file is available
    $log_file_path = plugin_dir_path(__FILE__) . 'ninjavan-logs.txt'; // Path to the plugin directory logs

    file_put_contents($log_file_path, "Webhook Receiver Triggered.\n", FILE_APPEND);

    $security_token = $_GET['security'] ?? null;
    if ($security_token !== '-') { 																// INSERT SECURITY TOKEN 
        file_put_contents($log_file_path, "Unauthorized access attempt.\n", FILE_APPEND);
        wp_die('Unauthorized', '', 403);
    }

    $webhook_data = file_get_contents('php://input');
    file_put_contents($log_file_path, "Webhook Data: " . $webhook_data . "\n", FILE_APPEND);

    $webhook_json = json_decode($webhook_data, true);
    if ($webhook_json === null) {
        file_put_contents($log_file_path, "Failed to decode JSON payload.\n", FILE_APPEND);
        wp_die('Invalid JSON data', '', 400);
    }

    file_put_contents($log_file_path, "Processed Webhook JSON: " . print_r($webhook_json, true) . "\n", FILE_APPEND);

    $tracking_id = $webhook_json['tracking_id'] ?? 'ZZZZ';
    $order_id = find_woocommerce_order_by_tracking_id($tracking_id);
    $status = $webhook_json['status'] ?? '';
    $shipper_order_ref_no = $webhook_json['shipper_order_ref_no'] ?? '';
    $timestamp = date('Y-m-d H:i:s');

    if ($order_id) {
        $order = wc_get_order($order_id);
        $order_created_date = $order ? $order->get_date_created()->date('Y-m-d H:i:s') : '0000-00-00 00:00:00';

        $wpdb->insert(
            $table_name,
            [
                'tracking_id' => $tracking_id,
                'order_id' => $order_id,
                'order_created_date' => $order_created_date,
                'shipper_order_ref_no' => $shipper_order_ref_no,
                'timestamp' => $timestamp,
                'status' => $status
            ]
        );
    }

    wp_die('Webhook processed', '', 200);
}

/*function ninja_van_webhook_receiver_handler() {
   global $wpdb;
   $table_name = $wpdb->prefix . 'ninja_van_logs';

   // Extract webhook data
   $webhook_data = file_get_contents('php://input');
   $webhook_json = json_decode($webhook_data, true);

   $tracking_id = $webhook_json['tracking_id'] ?? 'ZZZZ';
   $order_id = find_woocommerce_order_by_tracking_id($tracking_id);
   $status = $webhook_json['status'] ?? '';
   $shipper_order_ref_no = $webhook_json['shipper_order_ref_no'] ?? '';
   $timestamp = date('Y-m-d H:i:s');

   if ($order_id) {
       $order = wc_get_order($order_id);
       $order_created_date = $order ? $order->get_date_created()->date('Y-m-d H:i:s') : '0000-00-00 00:00:00';

       $wpdb->insert(
           $table_name,
           [
               'tracking_id' => $tracking_id,
               'order_id' => $order_id,
               'order_created_date' => $order_created_date,
               'shipper_order_ref_no' => $shipper_order_ref_no,
               'timestamp' => $timestamp,
               'status' => $status
           ]
       );
   }

   // Further processing and email logic
}*/

/*function ninja_van_webhook_receiver_handler() {
    ensure_main_log_file(); // Ensure main log file before logging
    $log_file_path = plugin_dir_path(__FILE__) . 'ninjavan-logs.txt'; // Update path to plugin directory

    file_put_contents($log_file_path, "Webhook Receiver Triggered.\n", FILE_APPEND);

    $security_token = $_GET['security'] ?? null;
    if ($security_token !== 'a63f9d05-7f05-44a7-a775-49808c656a42') {
        file_put_contents($log_file_path, "Unauthorized access attempt.\n", FILE_APPEND);
        wp_die('Unauthorized', '', 403);
    }

    $webhook_data = file_get_contents('php://input');
    file_put_contents($log_file_path, "Webhook Data: " . $webhook_data . "\n", FILE_APPEND);

    $webhook_json = json_decode($webhook_data, true);
    if ($webhook_json === null) {
        file_put_contents($log_file_path, "Failed to decode JSON payload.\n", FILE_APPEND);
        wp_die('Invalid JSON data', '', 400);
    }

    file_put_contents($log_file_path, "Processed Webhook JSON: " . print_r($webhook_json, true) . "\n", FILE_APPEND);

    // Further processing can be done here

    wp_die('Webhook processed', '', 200);
}*/

// Separate action for handling email processing
add_action('wp_ajax_ninja_van_email_processor', 'ninja_van_email_processor_handler');
add_action('wp_ajax_nopriv_ninja_van_email_processor', 'ninja_van_email_processor_handler');

function ninja_van_email_processor_handler() {
    ensure_email_log_file(); // Ensure email log file before logging
    $log_file_path = plugin_dir_path(__FILE__) . 'ninjavan-email-logs.txt';
    file_put_contents($log_file_path, "Processing email for status update.\n", FILE_APPEND);

    $security_token = $_GET['security'] ?? null;
    if ($security_token !== 'a63f9d05-7f05-44a7-a775-49808c656a42') {
        file_put_contents($log_file_path, "Unauthorized access attempt.\n", FILE_APPEND);
        wp_die('Unauthorized', '', 403);
    }

    $webhook_data = file_get_contents('php://input');
    file_put_contents($log_file_path, "AJAX Webhook Handler Triggered.\nWebhook Data: $webhook_data\n", FILE_APPEND);

    $webhook_json = json_decode($webhook_data, true);
    if ($webhook_json === null) {
        file_put_contents($log_file_path, "Failed to decode JSON payload.\n", FILE_APPEND);
        wp_die('Invalid JSON data', '', 400);
    }

    file_put_contents($log_file_path, "Decoded Webhook JSON:\n" . print_r($webhook_json, true) . "\n", FILE_APPEND);

    $order_status = $webhook_json['status'] ?? '';
    $tracking_id = $webhook_json['tracking_id'] ?? 'ZZZZ'; // Example dynamic tracking number

    // Find WooCommerce order by tracking ID
    $order_id = find_woocommerce_order_by_tracking_id($tracking_id);

    if (!$order_id) {
        file_put_contents($log_file_path, "No WooCommerce order found for tracking ID $tracking_id. Email not sent.\n", FILE_APPEND);
        wp_die('No corresponding WooCommerce order.', '', 200);
    }

    $sent_key = 'ninja_van_email_sent_' . $order_status;

    // Check if email for this status update has already been sent
    if (has_email_been_sent_for_status($order_id, $order_status)) {
        file_put_contents($log_file_path, "Email for order $order_id with status $order_status already sent.\n", FILE_APPEND);
        wp_die('Email already sent.', '', 200);
    }

    $statuses = array_map('trim', explode(',', get_option('ninja_van_email_statuses', 'Cancelled')));
    file_put_contents($log_file_path, "Configured Statuses: " . implode(', ', $statuses) . "\n", FILE_APPEND);

    $emails = array_map('trim', explode(',', get_option('ninja_van_email_recipients', 'epp.cs.general@gmail.com')));
    file_put_contents($log_file_path, "Configured Emails: " . implode(', ', $emails) . "\n", FILE_APPEND);

    if (in_array($order_status, $statuses)) {
        $subject = "Alert Parcel Event $order_status (Order ID: $order_id), (Tracking Number: $tracking_id)";
        $url_order_details = esc_url(admin_url('post.php?post=' . $order_id . '&action=edit')); // URL to order details in WooCommerce admin
        $url_tracking = "https://www.ninjavan.co/en-my/tracking?id=$tracking_id";

        $message = "
        Dear CS,

        There is one parcel that has reached Event \"$order_status\".

        Order ID: $order_id
        Click this link to view Order details: $url_order_details

        Tracking Number: $tracking_id
        Click this link to view the Order's current delivery status: $url_tracking

        This is a system-generated email. Please do not reply to this email.

        Best regards,
        System Auto Alert.";

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        foreach ($emails as $email) {
            if (!empty($email)) {
                $email_result = wp_mail($email, $subject, nl2br(esc_html($message)), $headers);
                file_put_contents($log_file_path, "Attempted to email $email: " . ($email_result ? "Success" : "Failed") . "\n", FILE_APPEND);
            }
        }

        // Mark email for this status as sent
        mark_email_as_sent($order_id, $order_status);
    } else {
        file_put_contents($log_file_path, "Order status '$order_status' not matched with configured statuses.\n", FILE_APPEND);
    }

    wp_die('Webhook processed', '', 200);
}


// Utility function to check if an email has been sent for a given order ID and status
function has_email_been_sent_for_status($order_id, $status) {
    $key = 'ninja_van_email_sent_' . $status;
    $sent_status = get_post_meta($order_id, $key, true);
    file_put_contents(plugin_dir_path(__FILE__) . 'ninjavan-email-logs.txt', "Checking sent status for order $order_id with status $status: $sent_status\n", FILE_APPEND);
    return $sent_status === '1';
}

function mark_email_as_sent($order_id, $status) {
    $key = 'ninja_van_email_sent_' . $status;
    update_post_meta($order_id, $key, '1');
    file_put_contents(plugin_dir_path(__FILE__) . 'ninjavan-email-logs.txt', "Marked as sent for order $order_id with status $status\n", FILE_APPEND);
}


?>