<?php
// woocommerce-functions.php

// Function to extract the tracking number from serialized AST data
function get_tracking_number($shiptrackitems) {
    // Replace escaped quotes with normal quotes
    $newvalue = str_replace('\"', '"', $shiptrackitems);

    // Unserialize the array, suppressing potential errors with @
    $data = @unserialize($newvalue);

    // Check if unserialization was successful and $data is an array
    if (is_array($data) && isset($data[0])) {
        $tracking_number = isset($data[0]['tracking_number']) ? $data[0]['tracking_number'] : "No tracking number";
        return $tracking_number;
    } else {
        return "Invalid or Unreadable Data";
    }
}

// Function to find WooCommerce order by tracking ID
function find_woocommerce_order_by_tracking_id($tracking_id) {
    global $wpdb;
    $meta_key = '_wc_shipment_tracking_items'; // Adjust to the actual meta key used by AST

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $meta_key
    ));

    foreach ($results as $result) {
        $order_id = $result->post_id;
        $serialized_data = $result->meta_value;

        $extracted_tracking_id = get_tracking_number($serialized_data);
        if ($extracted_tracking_id === $tracking_id) {
            return $order_id;
        }
    }

    return false;
}
?>
