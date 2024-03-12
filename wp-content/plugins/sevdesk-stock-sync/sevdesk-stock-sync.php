<?php
/*
Plugin Name: sevDesk Stock Sync
Plugin URI: https://github.com/openstream/sevdesk-stock-sync
Description: Retrieve product stock quantities via sevDesk API and write them to a CSV file.
Version: 1.0
Author: Openstream Internet Solutions
Author URI: https://www.openstream.ch
License: GPL-3.0
*/

// Register activation hook to set up initial environment, schedule the cron job, and create log directory
register_activation_hook(__FILE__, 'sevdesk_stock_sync_activate');
function sevdesk_stock_sync_activate() {
    $upload = wp_upload_dir();
    $upload_dir = $upload['basedir'];
    $upload_dir .= '/sevdesk-stock-sync';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0700, true);
    }

    if (!wp_next_scheduled('sevdesk_stock_sync_hourly_event')) {
        wp_schedule_event(time(), 'hourly', 'sevdesk_stock_sync_hourly_event');
    }
}

// Register deactivation hook to clear scheduled cron job
register_deactivation_hook(__FILE__, 'sevdesk_stock_sync_deactivate');
function sevdesk_stock_sync_deactivate() {
    $timestamp = wp_next_scheduled('sevdesk_stock_sync_hourly_event');
    wp_unschedule_event($timestamp, 'sevdesk_stock_sync_hourly_event');
}

// Add submenu page under "Settings"
add_action('admin_menu', 'sevdesk_stock_sync_menu');
function sevdesk_stock_sync_menu() {
    add_options_page(
        'sevDesk Stock Sync Settings',
        'sevDesk Stock Sync',
        'manage_options',
        'sevdesk-stock-sync-settings',
        'sevdesk_stock_sync_settings_page'
    );
}

// Display the settings page
function sevdesk_stock_sync_settings_page() {
    ?>
    <div class="wrap">
        <h2>sevDesk Stock Sync Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('sevdesk-stock-sync-settings');
            do_settings_sections('sevdesk-stock-sync-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Initialize settings, sections, and fields
add_action('admin_init', 'sevdesk_stock_sync_settings_init');
function sevdesk_stock_sync_settings_init() {
    register_setting('sevdesk-stock-sync-settings', 'sevdesk_stock_sync_api_token');

    add_settings_section(
        'sevdesk_stock_sync_settings_section',
        'API Settings',
        'sevdesk_stock_sync_settings_section_callback',
        'sevdesk-stock-sync-settings'
    );

    add_settings_field(
        'sevdesk_stock_sync_api_token',
        'sevDesk API Token',
        'sevdesk_stock_sync_api_token_callback',
        'sevdesk-stock-sync-settings',
        'sevdesk_stock_sync_settings_section'
    );
}

function sevdesk_stock_sync_settings_section_callback() {
    echo '<p>Enter your sevDesk API Token below.</p>';
}

function sevdesk_stock_sync_api_token_callback() {
    $token = get_option('sevdesk_stock_sync_api_token');
    echo '<input type="text" id="sevdesk_stock_sync_api_token" name="sevdesk_stock_sync_api_token" value="' . esc_attr($token) . '" />';
}

// Logging function
function sevdesk_stock_sync_log($message) {
    $upload = wp_upload_dir();
    $log_file = $upload['basedir'] . '/sevdesk-stock-sync/sevdesk_stock_sync.log';

    if (!file_exists($log_file)) {
        $fp = fopen($log_file, 'a');
        fclose($fp);
    }

    $log_entry = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Function to fetch stock quantities from sevDesk API
function fetch_sevdesk_stock_quantities() {
    $apiToken = get_option('sevdesk_stock_sync_api_token');
    $response = wp_remote_get('https://my.sevdesk.de/api/v1/Part?token=' . $apiToken, array(
        'headers' => array(
            'Accept' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        sevdesk_stock_sync_log('Error fetching stock quantities: ' . $error_message);
        return; // Handle error
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data['objects'] ?? [];
}

// Function to write stock quantities to a CSV file
function write_stock_quantities_to_csv($stockItems) {
    $upload = wp_upload_dir();
    $file = $upload['basedir'] . '/sevdesk-stock-sync/stock_quantities.csv';

    $handle = fopen($file, 'w');
    fputcsv($handle, ['SKU', 'Stock Quantity']);

    foreach ($stockItems as $item) {
        fputcsv($handle, [$item['partNumber'], $item['stock']]);
    }

    fclose($handle);
    sevdesk_stock_sync_log('CSV file saved successfully.');
}

// Hook the synchronization function to the custom action
add_action('sevdesk_stock_sync_hourly_event', 'sync_sevdesk_stock');
function sync_sevdesk_stock() {
    $stockItems = fetch_sevdesk_stock_quantities();
    if (!empty($stockItems)) {
        write_stock_quantities_to_csv($stockItems);
    }
}
