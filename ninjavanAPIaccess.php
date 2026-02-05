<?php
/*
Plugin Name: Ninja Van Log Viewer
Description: A plugin to display Ninja Van webhook logs.
Version: 2.0
Author: chrisNW
*/

// Include smtp settings' files
require_once plugin_dir_path(__FILE__) . 'admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'webhook-handler.php';
require_once plugin_dir_path(__FILE__) . 'smtp-setup.php';
require_once plugin_dir_path(__FILE__) . 'woocommerce-functions.php';

// Create log file during plugin initialization
add_action('init', 'create_ninjavan_log_file');
function create_ninjavan_log_file() {
    ensure_main_log_file();
}

// Add the submenu for filtered logs
add_action('admin_menu', 'ninja_van_filtered_logs_menu');

function ninja_van_filtered_logs_menu() {
    add_menu_page(
        'Filtered Ninja Van Logs',  // Page title
        'Ninja Van Logs',           // Submenu title
        'manage_options',           // Capability
        'filtered-ninja-van-logs',  // Submenu slug
        'ninja_van_filtered_logs_page' // Callback function
    );
}

// Add the main menu for Ninja Van Logs
add_action('admin_menu', 'ninja_van_log_viewer_menu');

function ninja_van_log_viewer_menu() {
    add_submenu_page(
		'filtered-ninja-van-logs',	// Parent menu slug
        'Ninja Van Logs',           // Page title
        'Rawfile NV Logs',          // Menu title
        'manage_options',           // Capability
        'ninja-van-log-viewer',     // Menu slug
        'ninja_van_log_viewer_page' // Callback function
    );
}

add_action('admin_menu', 'ninja_van_log_migration_menu');

function ninja_van_log_migration_menu() {
    add_submenu_page(
        'filtered-ninja-van-logs', // Make sure this matches the main menu slug
        'Ninja Van Log Migration', // Page title
        'Log Migration', // Menu title
        'manage_options', // Capability
        'ninja-van-log-migration', // Menu slug
        'ninja_van_log_migration_page' // Callback function
    );
}
// Set up top-level menu and submenus

function ninja_van_log_migration_page() {
    echo '<div class="wrap">';
    echo '<h1>Ninja Van Log Migration</h1>';

    if (isset($_POST['migrate_logs'])) {
        import_log_file_to_database();
    }

    echo '<form method="POST">';
    echo '<input type="hidden" name="migrate_logs" value="1" />';
    echo get_submit_button('Migrate Logs to Database', 'primary', 'submit');
    echo '</form>';

    echo '</div>';
}

register_activation_hook(__FILE__, 'ninja_van_create_log_table');

function ninja_van_create_log_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ninja_van_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tracking_id varchar(100) NOT NULL,
        order_id bigint(20) UNSIGNED,
        order_created_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        shipper_order_ref_no varchar(100) NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status varchar(100) NOT NULL,
        PRIMARY KEY  (id),
        key tracking_id (tracking_id),
        key order_id (order_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function import_log_file_to_database() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ninja_van_logs';
    $log_file_path = plugin_dir_path(__FILE__) . 'ninjavan-logs.txt';
    $migration_log_file = plugin_dir_path(__FILE__) . 'migration-log.txt';

    // Initialize the migration log
    file_put_contents($migration_log_file, "Migration started...\n", FILE_APPEND);

    if (!file_exists($log_file_path)) {
        file_put_contents($migration_log_file, "Log file not found.\n", FILE_APPEND);
        echo "Log file not found.";
        return;
    }

    $logs = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $count = 0;
    foreach ($logs as $log) {
        preg_match('/"tracking_id":"(.*?)"/', $log, $tracking_id_match);
        preg_match('/"shipper_order_ref_no":"(.*?)"/', $log, $order_ref_match);
        preg_match('/"timestamp":"(.*?)"/', $log, $timestamp_match);
        preg_match('/"status":"(.*?)"/', $log, $status_match);

        $tracking_id = $tracking_id_match[1] ?? '';
        $order_id = find_woocommerce_order_by_tracking_id($tracking_id);

        if ($tracking_id) {
            file_put_contents($migration_log_file, "Processing Tracking ID: $tracking_id | Order ID: $order_id\n", FILE_APPEND);
        }

        if (!$order_id) {
            file_put_contents($migration_log_file, "No matching WooCommerce order for tracking ID: $tracking_id\n", FILE_APPEND);
            continue;
        }

        $shipper_order_ref_no = $order_ref_match[1] ?? '';
        $timestamp = $timestamp_match[1] ?? date('Y-m-d H:i:s');
        $status = $status_match[1] ?? '';

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

            file_put_contents($migration_log_file, "Entry imported for Tracking ID: $tracking_id\n", FILE_APPEND);
            $count++;
        }
    }

    file_put_contents($migration_log_file, "Migration completed. $count logs imported.\n", FILE_APPEND);

    echo "Migration completed. $count logs imported.";
}


// Function to display the log file
function ninja_van_log_viewer_page() {
    $log_file_path = plugin_dir_path(__FILE__) . 'ninjavan-logs.txt'; // Update path to plugin directory

    echo '<div class="wrap">';
    echo '<h1>Ninja Van Logs</h1>';

    if (file_exists($log_file_path)) {
        $logs = file_get_contents($log_file_path); // Read the file contents

        if (!empty($logs)) {
            echo '<pre style="background: #f4f4f4; padding: 15px; border: 1px solid #ccc; overflow: auto;">';
            echo esc_html($logs); // Display the logs with proper escaping
            echo '</pre>';
        } else {
            echo '<p>No logs found in the file.</p>';
        }
    } else {
        echo '<p>The log file <strong>' . esc_html($log_file_path) . '</strong> does not exist.</p>';
    }

    echo '</div>';
}

// FILTERED SECTION
function ninja_van_filtered_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ninja_van_logs';

    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $search_query = $_GET['search_query'] ?? '';

    $query = "
        SELECT tbl1.tracking_id, 
               tbl1.order_id, 
               tbl1.order_created_date, 
               tbl1.shipper_order_ref_no, 
               tbl1.timestamp AS last_timestamp, 
               tbl1.status,
               (SELECT GROUP_CONCAT(DISTINCT CONCAT_WS(' | ', status, DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s')) ORDER BY timestamp DESC SEPARATOR '\n') 
                FROM $table_name 
                WHERE tracking_id = tbl1.tracking_id) AS history
        FROM $table_name AS tbl1
        INNER JOIN (
            SELECT tracking_id, 
                   MAX(timestamp) AS last_timestamp
            FROM $table_name
            WHERE 1=1
            GROUP BY tracking_id
        ) AS tbl2 ON tbl1.tracking_id = tbl2.tracking_id AND tbl1.timestamp = tbl2.last_timestamp
        WHERE 1=1";

    if (!empty($start_date)) {
        $query .= $wpdb->prepare(" AND tbl1.timestamp >= %s", $start_date);
    }
    if (!empty($end_date)) {
        $query .= $wpdb->prepare(" AND tbl1.timestamp <= %s", $end_date);
    }
    if (!empty($status_filter)) {
        $query .= $wpdb->prepare(" AND tbl1.status = %s", $status_filter);
    }
    if (!empty($search_query)) {
        $query .= $wpdb->prepare(" AND (tbl1.tracking_id LIKE %s OR tbl1.shipper_order_ref_no LIKE %s)", "%$search_query%", "%$search_query%");
    }

    $query .= " ORDER BY tbl1.timestamp DESC";

    $current_page = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;
    $entries_per_page = 20;
    $offset = ($current_page - 1) * $entries_per_page;

    $total_entries = $wpdb->get_var("SELECT COUNT(DISTINCT tracking_id) FROM $table_name WHERE 1=1");
    $total_pages = ceil($total_entries / $entries_per_page);

    $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $entries_per_page, $offset);
    $logs = $wpdb->get_results($query);

    echo '<style>
            .hover-history {
                cursor: pointer;
                background-color: #e8f4f8; /* Light blue background for visibility */
                text-align: center;
                position: relative;
            }
            .hover-history:hover {
                background-color: #d0e8f0; /* Darker shade on hover */
            }
            .hover-history::after {
                content: "\f0c9"; /* FontAwesome info icon (or use suitable icon) */
                font-family: FontAwesome;
                position: absolute;
                right: 5px;
                top: 50%;
                transform: translateY(-50%);
                opacity: 0.6;
            }
          </style>';
    echo '<div style="margin: 20px;">';
    echo '<h1>Ninja Van Logs</h1>';

    // Filter form
    echo '<form method="GET" style="margin-bottom: 20px;">
          <input type="hidden" name="page" value="filtered-ninja-van-logs">
          <label for="start_date">Start Date:</label>
          <input type="date" name="start_date" value="' . esc_attr($start_date) . '">
          <label for="end_date">End Date:</label>
          <input type="date" name="end_date" value="' . esc_attr($end_date) . '">
          <label for="status">Status:</label>
          <input type="text" name="status" placeholder="Enter status" value="' . esc_attr($status_filter) . '">
          <label for="search_query">Search:</label>
          <input type="text" name="search_query" placeholder="Order Ref No, Tracking ID, or Woo Order ID" value="' . esc_attr($search_query) . '">
          <button type="submit">Filter</button>
          <button type="button" onclick="window.location.href=\'?page=filtered-ninja-van-logs\';">Reset</button>
          </form>';


    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<thead>';
    echo '<tr style="background-color: #0073aa; color: white; text-align: left;">';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Tracking ID</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Woo Order ID</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Order Created</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Order Ref No</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Timestamp</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Status</th>';
    echo '</tr>';
    echo '</thead>';

    echo '<tbody>';
    foreach ($logs as $log) {
        echo '<tr style="border: 1px solid #ddd;">';
        echo '<td style="border: 1px solid #ddd; padding: 8px;"><a href="https://www.ninjavan.co/en-my/tracking?id=' . esc_attr($log->tracking_id) . '" target="_blank">' . esc_html($log->tracking_id) . '</a></td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;"><a href="' . esc_url(admin_url('post.php?post=' . $log->order_id . '&action=edit')) . '" target="_blank">' . esc_html($log->order_id) . '</a></td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($log->order_created_date) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($log->shipper_order_ref_no) . '</td>';
        echo '<td class="hover-history" style="border: 1px solid #ddd; padding: 8px;" title="' . esc_attr(str_replace("\n", '&#10;', $log->history)) . '">' . esc_html($log->last_timestamp) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($log->status) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Pagination controls
    echo '<div style="margin-top: 20px; text-align: center;">';

    if ($current_page > 1) {
        echo '<a href="?page=filtered-ninja-van-logs&page_num=' . ($current_page - 1) . '" style="margin-right: 10px;">Previous</a>';
    }

    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            echo '<span style="margin: 0 5px; font-weight: bold;">' . $i . '</span>';
        } else {
            echo '<a href="?page=filtered-ninja-van-logs&page_num=' . $i . '" style="margin: 0 5px;">' . $i . '</a>';
        }
    }

    if ($current_page < $total_pages) {
        echo '<a href="?page=filtered-ninja-van-logs&page_num=' . ($current_page + 1) . '" style="margin-left: 10px;">Next</a>';
    }

    echo '</div>'; // Pagination div
    echo '</div>'; // Main container div
}



// Helper function to check if tracking ID matches a WooCommerce order
function is_woocommerce_order($tracking_id) {
    global $wpdb;
    $meta_key = '_tracking_number'; // Replace with your WooCommerce meta key for tracking numbers
    $query = $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $meta_key, $tracking_id);
    $result = $wpdb->get_var($query);
    return !empty($result);
}

// Helper function to parse a log entry
function parse_log_entry($entry) {
    $data = [];

    if (preg_match('/"tracking_id":"(.*?)"/', $entry, $matches)) {
        $data['tracking_id'] = $matches[1];
    }

    if (preg_match('/"shipper_order_ref_no":"(.*?)"/', $entry, $matches)) {
        $data['shipper_order_ref_no'] = $matches[1];
    }

    if (preg_match('/"timestamp":"(.*?)"/', $entry, $matches)) {
        $data['timestamp'] = $matches[1];
    }

    if (preg_match('/"status":"(.*?)"/', $entry, $matches)) {
        $data['status'] = $matches[1];
    }

    return $data;
}

/*function ninja_van_filtered_logs_page() {
    $log_file_path = plugin_dir_path(__FILE__) . 'ninjavan-logs.txt'; // Update path to plugin directory

    if (!file_exists($log_file_path)) {
        echo "<h2>No logs found at {$log_file_path}</h2>";
        return;
    }

    // Determine if reset action is required
    $is_reset = isset($_GET['reset']) && $_GET['reset'] == '1';

    // Default parameters for filtering and searching
    $start_date = $is_reset ? '' : (isset($_GET['start_date']) ? $_GET['start_date'] : '');
    $end_date = $is_reset ? '' : (isset($_GET['end_date']) ? $_GET['end_date'] : '');
    $status_filter = $is_reset ? '' : (isset($_GET['status']) ? $_GET['status'] : '');
    $search_query = $is_reset ? '' : (isset($_GET['search_query']) ? strtolower($_GET['search_query']) : '');

    // Read and process the logs
    $logs = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $unique_entries = [];

    foreach ($logs as $log) {
        preg_match('/"tracking_id":"(.*?)"/', $log, $tracking_id_match);
        if (!isset($tracking_id_match[1])) {
            continue;
        }

        $tracking_id = $tracking_id_match[1];
        $order_id = find_woocommerce_order_by_tracking_id($tracking_id); // Check WooCommerce order existence

        // Only add entries that have a corresponding WooCommerce order id
        if ($order_id !== false) {
            preg_match('/"shipper_order_ref_no":"(.*?)"/', $log, $order_ref_match);
            preg_match('/"timestamp":"(.*?)"/', $log, $timestamp_match);
            preg_match('/"status":"(.*?)"/', $log, $status_match);

            $order_ref = isset($order_ref_match[1]) ? $order_ref_match[1] : 'N/A';
            $timestamp = isset($timestamp_match[1]) ? $timestamp_match[1] : 'N/A';
            $status = isset($status_match[1]) ? $status_match[1] : 'N/A';

            // Retrieve the order created date
            $order = wc_get_order($order_id);
            $order_created_date = $order ? $order->get_date_created()->date('Y-m-d H:i:s') : 'N/A';

            if (!isset($unique_entries[$tracking_id]) || strtotime($timestamp) > strtotime($unique_entries[$tracking_id]['timestamp'])) {
                $unique_entries[$tracking_id] = [
                    'tracking_id' => $tracking_id,
                    'order_id' => $order_id, // Added WooCommerce order ID
                    'order_created_date' => $order_created_date, // Added order created date
                    'shipper_order_ref_no' => $order_ref,
                    'timestamp' => $timestamp,
                    'status' => $status
                ];
            }
        }
    }

    // Apply filtering if not reset
    $filtered_entries = array_filter($unique_entries, function ($entry) use ($start_date, $end_date, $status_filter, $search_query) {
        $timestamp = strtotime($entry['timestamp']);

        // Date filter
        if ((!empty($start_date) && $timestamp < strtotime($start_date)) ||
            (!empty($end_date) && $timestamp > strtotime($end_date))) {
            return false;
        }

        // Status filter
        if (!empty($status_filter) && strtolower($entry['status']) !== strtolower($status_filter)) {
            return false;
        }

        // Search filter
        if (!empty($search_query) && 
            strpos(strtolower($entry['shipper_order_ref_no']), $search_query) === false &&
            strpos(strtolower($entry['tracking_id']), $search_query) === false &&
            strpos(strtolower($entry['order_id']), $search_query) === false) {
            return false;
        }

        return true;
    });

    // Sort by timestamp descending
    usort($filtered_entries, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Pagination setup
    $entries_per_page = 20; 
    $current_page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
    $total_entries = count($filtered_entries);
    $total_pages = ceil($total_entries / $entries_per_page);

    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages) {
        $current_page = $total_pages;
    }

    $start_index = ($current_page - 1) * $entries_per_page;
    $current_logs = array_slice($filtered_entries, $start_index, $entries_per_page);

    // Display the form and table
    echo '<div style="margin: 20px;">';
    echo '<h1>Ninja Van Logs</h1>';

    // Filter form
    echo '<form method="GET" style="margin-bottom: 20px;">
          <input type="hidden" name="page" value="filtered-ninja-van-logs">
          <label for="start_date">Start Date:</label>
          <input type="date" name="start_date" value="' . esc_attr($start_date) . '">
          <label for="end_date">End Date:</label>
          <input type="date" name="end_date" value="' . esc_attr($end_date) . '">
          <label for="status">Status:</label>
          <input type="text" name="status" placeholder="Enter status" value="' . esc_attr($status_filter) . '">
          <label for="search_query">Search:</label>
          <input type="text" name="search_query" placeholder="Order Ref No, Tracking ID, or Woo Order ID" value="' . esc_attr($search_query) . '">
          <button type="submit">Filter</button>
          <button type="submit" name="reset" value="1">Reset</button>
          </form>';

    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<thead>';
    echo '<tr style="background-color: #0073aa; color: white; text-align: left;">';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Tracking ID</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Woo Order ID</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Order Created</th>'; // New column for Order Created Date
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Order Ref No</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Timestamp</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Status</th>';
    echo '</tr>';
    echo '</thead>';

    echo '<tbody>';
    foreach ($current_logs as $entry) {
        echo '<tr style="border: 1px solid #ddd;">';
        echo '<td style="border: 1px solid #ddd; padding: 8px;"><a href="https://www.ninjavan.co/en-my/tracking?id=' . esc_attr($entry['tracking_id']) . '" target="_blank">' . esc_html($entry['tracking_id']) . '</a></td>';
        // Hyperlink to WooCommerce order detail page
        echo '<td style="border: 1px solid #ddd; padding: 8px;"><a href="' . esc_url(admin_url('post.php?post=' . $entry['order_id'] . '&action=edit')) . '" target="_blank">' . esc_html($entry['order_id']) . '</a></td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($entry['order_created_date']) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($entry['shipper_order_ref_no']) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($entry['timestamp']) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($entry['status']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Pagination controls
    echo '<div style="margin-top: 20px; text-align: center;">';

    if ($current_page > 1) {
        echo '<a href="?page=filtered-ninja-van-logs&page_num=' . ($current_page - 1) . '" style="margin-right: 10px;">Previous</a>';
    }

    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current_page) {
            echo '<span style="margin: 0 5px; font-weight: bold;">' . $i . '</span>';
        } else {
            echo '<a href="?page=filtered-ninja-van-logs&page_num=' . $i . '" style="margin: 0 5px;">' . $i . '</a>';
        }
    }

    if ($current_page < $total_pages) {
        echo '<a href="?page=filtered-ninja-van-logs&page_num=' . ($current_page + 1) . '" style="margin-left: 10px;">Next</a>';
    }

    echo '</div>'; // Pagination div
    echo '</div>'; // Main container div
}*/