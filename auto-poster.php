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
            echo '<form method="post"><button name="sync" class="button-primary">Sync & Create/Update Blog Posts Now</button></form>';
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
    $csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSF_mV69DPo8Dl9JMjOm8w2WfTDRLIwHdDhymxQxHiJEYEZJI058qEOApbz9zvLB9NH7ReRwmXD4L1Z/pub?output=csv';

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

    foreach ($rows as $row_index => $row) {
        if (count($row) !== count($header)) {
            error_log("Skipping incomplete row at index {$row_index}");
            continue;
        }

        $data = array_combine($header, $row);

        $post_title_raw = $data['Address'] ?? '';

        if (empty($post_title_raw)) {
            error_log("Skipping row {$row_index} due to missing Address.");
            continue;
        }

        $post_title = trim(sanitize_text_field($post_title_raw));

        /**
         * ✅ Generate a unique identifier for the row.
         * You can improve this by adding another field if needed.
         * Example: Address + Zip + Date = more uniqueness
         */
        $unique_id = sanitize_title($post_title . '-' . ($data['Zip'] ?? '') . '-' . ($data['Date'] ?? ''));

        // 🔎 Search for existing post by `auction_sheet_id`
        $query = new WP_Query([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'meta_key'       => 'auction_sheet_id',
            'meta_value'     => $unique_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        $post_id = !empty($query->posts) ? $query->posts[0] : null;
        wp_reset_postdata();

        /**
         * ✅ Build the post content (unchanged)
         */
        $post_content = '<h2>Auction Details</h2>';
        $post_content .= '<ul>';
        $post_content .= '<li><strong>Status:</strong> ' . esc_html($data['Status'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>Auctioneer:</strong> ' . esc_html($data['Auctioneer'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>City:</strong> ' . esc_html($data['City'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>State:</strong> ' . esc_html($data['State'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>Zip:</strong> ' . esc_html($data['Zip'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>Deposit:</strong> ' . esc_html($data['Deposit'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>Date:</strong> ' . esc_html($data['Date'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>Time:</strong> ' . esc_html($data['Time'] ?? 'N/A') . '</li>';
        $post_content .= '<li><strong>Listing Link:</strong> <a href="' . esc_url($data['Listing Link'] ?? '#') . '">View Listing</a></li>';
        $post_content .= '<li><strong>Terms:</strong> ' . esc_html($data['Terms'] ?? 'N/A') . '</li>';
        $post_content .= '</ul>';

        if (!empty($data['Image'])) {
            $post_content .= '<p><img src="' . esc_url($data['Image']) . '" alt="Auction Image" /></p>';
        }

        /**
         * ✅ If post already exists
         */
        if ($post_id) {
            error_log("Found existing post (ID: {$post_id}) for unique_id: {$unique_id}");

            // Check if data has changed (example compares Status, expand as needed)
            $current_status = get_post_meta($post_id, 'auction_status', true);
            $sheet_status   = $data['Status'] ?? '';

            if ($current_status !== $sheet_status) {
                // ✅ Flag for review, do NOT overwrite
                update_post_meta($post_id, 'needs_sync_review', true);
                error_log("Post {$post_id} flagged for review: sheet status '{$sheet_status}' differs from current '{$current_status}'");
            } else {
                error_log("Post {$post_id} is already up-to-date.");
            }

            // ✅ Continue to next row without modifying the post content/meta
            continue;
        }

        /**
         * ✅ If post doesn't exist, create a new post
         */
        $new_post_id = wp_insert_post([
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        if (!$new_post_id) {
            error_log("Failed to create post for row {$row_index} ({$post_title})");
            continue;
        }

        // ✅ Add meta fields for new post
        update_post_meta($new_post_id, 'auction_sheet_id', $unique_id);
        update_post_meta($new_post_id, 'auction_status', $data['Status'] ?? '');
        update_post_meta($new_post_id, 'auctioneer', $data['Auctioneer'] ?? '');
        update_post_meta($new_post_id, 'auction_city', $data['City'] ?? '');
        update_post_meta($new_post_id, 'auction_state', $data['State'] ?? '');
        update_post_meta($new_post_id, 'auction_zip', $data['Zip'] ?? '');
        update_post_meta($new_post_id, 'auction_deposit', $data['Deposit'] ?? '');
        update_post_meta($new_post_id, 'auction_date', $data['Date'] ?? '');
        update_post_meta($new_post_id, 'auction_time', $data['Time'] ?? '');
        update_post_meta($new_post_id, 'auction_listing_link', $data['Listing Link'] ?? '');
        update_post_meta($new_post_id, 'auction_terms', $data['Terms'] ?? '');
        update_post_meta($new_post_id, 'auction_image', $data['Image'] ?? '');
        update_post_meta($new_post_id, 'auction_last_synced', current_time('mysql'));
        update_post_meta($new_post_id, 'needs_sync_review', false); // new posts don't need review yet

        error_log("Created new post ID: {$new_post_id} for {$post_title}");
    }

    error_log("Google Sheet sync complete!");
}


