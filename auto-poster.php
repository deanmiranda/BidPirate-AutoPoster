<?php
/**
 * Plugin Name: Auction Blog Post Importer (Apt & Nimble LLC)
 * Description: Fetches auction listings from Google Sheet CSV and creates blog posts.
 * Version: 1.1
 * Author: Dean Miranda
 */

// Run on Cron
add_action('init', function () {
    if (defined('DOING_CRON') && DOING_CRON) {
        sync_auction_data_from_google_sheet();
    }
});

// Schedule Cron Event if Not Already
add_action('wp', function () {
    if (!wp_next_scheduled('auction_import_cron')) {
        wp_schedule_event(time(), 'hourly', 'auction_import_cron');
    }
});

// Hook Cron Event
add_action('auction_import_cron', 'sync_auction_data_from_google_sheet');

// Admin Page & Button
add_action('admin_menu', function () {
    add_menu_page(
        'BidBlender Posts',
        'BidBlender Posts',
        'manage_options',
        'auction-sync',
        function () {
            echo '<form method="post"><button name="sync" class="button-primary">Sync & Create All Blog Posts Now</button></form>';
            if (isset($_POST['sync'])) {
                sync_auction_data_from_google_sheet();
                echo '<div class="updated"><p>Sync completed!</p></div>';
            }
        },
        'dashicons-networking'
    );
});

// Main Sync Function
function sync_auction_data_from_google_sheet() {
    $csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSF_mV69DPo8Dl9JMjOm8w2WfTDRLIwHdDhymxQxHiJEYEZJI058qEOApbz9zvLB9NH7ReRwmXD4L1Z/pub?output=csv'; // Your link

    error_log("Fetching CSV from Google Sheets: {$csv_url}");

    $csv = file_get_contents($csv_url);

    if (!$csv) {
        error_log("Failed to fetch CSV from Google Sheets.");
        return;
    }

    $rows = array_map("str_getcsv", explode("\n", $csv));

    if (empty($rows) || count($rows) < 2) {
        error_log("No data found in the Google Sheet.");
        return;
    }

    $header = array_map('trim', array_shift($rows));

    foreach ($rows as $row) {
        if (count($row) !== count($header)) {
            error_log("Skipping incomplete row: " . print_r($row, true));
            continue;
        }

        $data = array_combine($header, $row);

        error_log("Row data: " . print_r($data, true));

        $post_title_raw = $data['Address'] ?? '';
        $post_content_raw = $data['Terms'] ?? '';

        if (empty($post_title_raw)) {
            error_log("Skipping row with no Address.");
            continue;
        }

        $post_title = trim(sanitize_text_field($post_title_raw));
        $post_content = wp_kses_post($post_content_raw);

        $existing_post = get_page_by_title($post_title, OBJECT, 'post');

        if ($existing_post) {
            error_log("Post already exists: {$post_title}");
            continue;
        }

        $post_id = wp_insert_post([
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        if ($post_id) {
            error_log("Created post ID: {$post_id}");

            update_post_meta($post_id, 'auction_status', $data['Status'] ?? '');
            update_post_meta($post_id, 'auctioneer', $data['Auctioneer'] ?? '');
            update_post_meta($post_id, 'auction_city', $data['City'] ?? '');
            update_post_meta($post_id, 'auction_state', $data['State'] ?? '');
            update_post_meta($post_id, 'auction_zip', $data['Zip'] ?? '');
            update_post_meta($post_id, 'auction_deposit', $data['Deposit'] ?? '');
            update_post_meta($post_id, 'auction_date', $data['Date'] ?? '');
            update_post_meta($post_id, 'auction_time', $data['Time'] ?? '');
            update_post_meta($post_id, 'auction_listing_link', $data['Listing Link'] ?? '');
            update_post_meta($post_id, 'auction_terms', $data['Terms'] ?? '');
            update_post_meta($post_id, 'auction_image', $data['Image'] ?? '');

            error_log("Meta fields updated for post ID: {$post_id}");
        }
    }

    error_log("Google Sheet sync complete!");
}
