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
                $sync_results = sync_auction_data_from_google_sheet();

                echo '<div class="updated"><p><strong>Sync completed!</strong></p>';

                if (!empty($sync_results['error'])) {
                    echo '<p>Error: ' . esc_html($sync_results['error']) . '</p>';
                } else {
                    if (!empty($sync_results['new_posts'])) {
                        echo '<p>✅ New Posts Created:</p><ul>';
                        foreach ($sync_results['new_posts'] as $title) {
                            echo '<li>' . esc_html($title) . '</li>';
                        }
                        echo '</ul>';
                    }

                    if (!empty($sync_results['review_posts'])) {
                        echo '<p>⚠️ Posts Flagged for Review:</p><ul>';
                        foreach ($sync_results['review_posts'] as $title) {
                            echo '<li>' . esc_html($title) . '</li>';
                        }
                        echo '</ul>';
                    }

                    if (empty($sync_results['new_posts']) && empty($sync_results['review_posts'])) {
                        echo '<p>No changes detected.</p>';
                    }
                }

                echo '</div>';
            }
        },
        'dashicons-networking'
    );
});

// Main Sync Function
function sync_auction_data_from_google_sheet() {
    $results = [
        'new_posts'     => [],
        'review_posts'  => [],
    ];

    $csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSF_mV69DPo8Dl9JMjOm8w2WfTDRLIwHdDhymxQxHiJEYEZJI058qEOApbz9zvLB9NH7ReRwmXD4L1Z/pub?output=csv';

    $csv = file_get_contents($csv_url);

    if (!$csv) {
        return ['error' => 'Failed to fetch CSV from Google Sheets.'];
    }

    $rows = array_map("str_getcsv", explode("\n", $csv));

    if (empty($rows) || count($rows) < 2) {
        return ['error' => 'No data found in the Google Sheet.'];
    }

    $header = array_map('trim', array_shift($rows));

    foreach ($rows as $row_index => $row) {
        if (count($row) !== count($header)) {
            continue;
        }

        $data = array_combine($header, $row);

        $post_title_raw = $data['Address'] ?? '';
        if (empty($post_title_raw)) {
            continue;
        }

        $post_title = trim(sanitize_text_field($post_title_raw));
        $unique_id = sanitize_title($post_title . '-' . ($data['Zip'] ?? '') . '-' . ($data['Date'] ?? ''));

        // Check for existing post by meta key
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

        if ($post_id) {
            // Compare status for review flag
            $current_status = get_post_meta($post_id, 'auction_status', true);
            $sheet_status   = $data['Status'] ?? '';

            if ($current_status !== $sheet_status) {
                update_post_meta($post_id, 'needs_sync_review', true);
                $results['review_posts'][] = $post_title;
            }

            continue; // Don't update post content automatically
        }

        // Build the post content for a new post
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

        // Insert new post
        $new_post_id = wp_insert_post([
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        if ($new_post_id) {
            update_post_meta($new_post_id, 'auction_sheet_id', $unique_id);
            update_post_meta($new_post_id, 'auction_status', $data['Status'] ?? '');
            update_post_meta($new_post_id, 'needs_sync_review', false);
            $results['new_posts'][] = $post_title;
        }
    }

    return $results;
}
