<?php

/**
 * Plugin Name: Auction Blog Post Importer (Apt & Nimble LLC)
 * Description: Fetches auction listings from Google Sheet CSV and creates blog posts.
 * Version: 1.3
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

// Allow WP user to update URL field
add_action('admin_init', function () {
    register_setting('auction_sync_settings', 'auction_google_sheet_url');
    register_setting('auction_sync_settings', 'auction_enable_gpt_blurb');

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
            echo '<h2>Auction Import Settings</h2>';

            echo '<form method="post" action="options.php">';
            settings_fields('auction_sync_settings');
            echo '<table class="form-table">';
            
                // Custom URL Field
                echo '<tr><th scope="row">Google Sheet CSV URL</th><td>';
                echo '<input type="text" name="auction_google_sheet_url" value="' . esc_attr(get_option('auction_google_sheet_url', '')) . '" size="80" />';
                echo '</td></tr>';
                
                // Toggle GPT Blurb
                echo '<tr><th scope="row">Enable GPT Blurb</th><td>';
                echo '<input type="checkbox" name="auction_enable_gpt_blurb" value="1" ' . checked(1, get_option('auction_enable_gpt_blurb', 1), false) . ' />';
                echo ' Generate AI-powered blurbs';
                echo '</td></tr>';

            echo '</table>';
            submit_button('Save Settings');
            echo '</form>';

            // Sync Button
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
                        foreach ($sync_results['review_posts'] as $review_post) {
                            echo '<li><strong>' . esc_html($review_post['title']) . '</strong><ul>';
                            foreach ($review_post['changes'] as $change) {
                                echo '<li>' . esc_html($change) . '</li>';
                            }
                            echo '</ul></li>';
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

function generate_auction_blurb($data)
{
    $api_key = defined('OPENAI_API_KEY') ? trim(OPENAI_API_KEY) : '';

    if (empty($api_key)) {
        error_log('OpenAI API key missing.');
        return '';
    }

    $prompt = "Write a professional, engaging real estate auction listing blurb. Make it under 100 words.\n\n";
    $prompt .= "Address: " . ($data['Address'] ?? 'N/A') . "\n";
    $prompt .= "City: " . ($data['City'] ?? 'N/A') . "\n";
    $prompt .= "State: " . ($data['State'] ?? 'N/A') . "\n";
    $prompt .= "Zip: " . ($data['Zip'] ?? 'N/A') . "\n";
    $prompt .= "Auction Date: " . ($data['Date'] ?? 'N/A') . "\n";
    $prompt .= "Time: " . ($data['Time'] ?? 'N/A') . "\n";
    $prompt .= "Auctioneer: " . ($data['Auctioneer'] ?? 'N/A') . "\n";
    $prompt .= "Deposit: " . ($data['Deposit'] ?? 'N/A') . "\n";
    $prompt .= "Terms: " . ($data['Terms'] ?? 'N/A') . "\n";

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model'    => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens'  => 200,
        ]),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        error_log('OpenAI API request failed: ' . $response->get_error_message());
        return '';
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['choices'][0]['message']['content'])) {
        error_log('OpenAI API response missing content.');
        return '';
    }

    return trim($body['choices'][0]['message']['content']);
}

// Main Sync Function
function sync_auction_data_from_google_sheet()
{
    // Check if another process is already running
    if (get_transient('auction_sync_running')) {
        error_log("⚠️ Another sync process is already running. Skipping this run.");
        return ['error' => 'Another sync process is already running.'];
    }

    // Set a transient to indicate we're running (expires after 5 minutes)
    set_transient('auction_sync_running', true, 5 * MINUTE_IN_SECONDS);

    // Static variable to track IDs being processed in this run
    static $currently_processing = [];

    $results = [
        'new_posts'     => [],
        'review_posts'  => [],
    ];

    try {
        // TEST CSL URL Link
        // $csv_url = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSF_mV69DPo8Dl9JMjOm8w2WfTDRLIwHdDhymxQxHiJEYEZJI058qEOApbz9zvLB9NH7ReRwmXD4L1Z/pub?output=csv';

        $csv_url = get_option('auction_google_sheet_url', '');

        if (empty($csv_url)) {
            error_log('❌ Google Sheet URL is not set.');
            return ['error' => 'Google Sheet URL is not set. Please configure it in Auction Sync settings.'];
        }

        $csv = file_get_contents($csv_url);

        if (!$csv) {
            error_log('❌ Failed to fetch Google Sheet CSV!');
            return ['error' => 'Failed to fetch CSV from Google Sheets.'];
        }

        $rows_raw = array_map('str_getcsv', explode("\n", $csv));
        $rows_raw = array_filter($rows_raw, function ($row) {
            return count($row) && !empty(trim($row[0]));
        });

        if (empty($rows_raw) || count($rows_raw) < 2) {
            error_log('❌ No usable data found in the Google Sheet.');
            return ['error' => 'No usable data found in the Google Sheet.'];
        }

        $header = array_map('trim', array_shift($rows_raw));
        $seen_ids = [];

        foreach ($rows_raw as $row_index => $row) {

   
            if (count($row) !== count($header)) {
                if (count($row) < count($header)) {
                    error_log("⚠️ Row {$row_index} has fewer columns. Filling missing values.");
                } else {
                    error_log("⚠️ Row {$row_index} has extra columns. Trimming excess values.");
                }
            
                $row = array_pad($row, count($header), '');
                $row = array_slice($row, 0, count($header));
            }
            
            $data = array_combine($header, $row);
            

            $post_title_raw = $data['Address'] ?? '';
            $post_title = trim(sanitize_text_field($post_title_raw));
            $unique_id = strtolower(sanitize_title($post_title));

            if (empty($unique_id)) {
                error_log("❌ Skipping row {$row_index}: no unique_id.");
                continue;
            }

            if (isset($seen_ids[$unique_id])) {
                error_log("⚠️ Duplicate in CSV: {$post_title} already processed.");
                continue;
            }
            $seen_ids[$unique_id] = true;

            // Check if we're already processing this ID in this run
            if (isset($currently_processing[$unique_id])) {
                error_log("⚠️ Already processing in this run: {$post_title}");
                continue;
            }
            $currently_processing[$unique_id] = true;

            error_log("🔍 Processing: {$post_title} | Unique ID: {$unique_id}");

            $query = new WP_Query([
                'post_type'      => 'post',
                'post_status'    => ['publish', 'pending', 'draft'], // Check all statuses
                'meta_query'     => [
                    [
                        'key'     => 'auction_sheet_id',
                        'value'   => $unique_id,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'cache_results'  => false, // Don't cache to ensure fresh results
                'no_found_rows'  => true,  // Performance optimization
            ]);

            $post_id = !empty($query->posts) ? $query->posts[0] : null;
            wp_reset_postdata();

            if ($post_id) {
                // ✅ Update existing post
                error_log("Updating post: {$post_title} (ID: {$post_id})");
                // Custom fields (meta)
                $custom_fields = get_option('auction_custom_fields', []);
                foreach ($custom_fields as $csv_label => $meta_key) {
                    if (isset($data[$csv_label])) {
                        update_post_meta($post_id, $meta_key, sanitize_text_field($data[$csv_label]));
                    }
                }

                // Blurb
                $blurb = '';
                if (get_option('auction_enable_gpt_blurb')) {
                    $blurb = generate_auction_blurb($data);
                }
                
                $post_content = '';

                if (!empty($blurb)) {
                    $post_content .= '<p><strong>Property Highlight:</strong> ' . esc_html($blurb) . '</p><hr>';
                }
                
                // Post content
                $post_content .= '<h2>Auction Details</h2><ul>';
                $post_content .= '<li><strong>Status:</strong> ' . esc_html($data['Status'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Auctioneer:</strong> ' . esc_html($data['Auctioneer'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>City:</strong> ' . esc_html($data['City'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>State:</strong> ' . esc_html($data['State'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Zip:</strong> ' . esc_html($data['Zip'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Date:</strong> ' . esc_html($data['Date'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Time:</strong> ' . esc_html($data['Time'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Listing Link:</strong> <a href="' . esc_url($data['Listing Link'] ?? '#') . '">View Listing</a></li>';
                $post_content .= '<li><strong>Terms:</strong> ' . esc_html($data['Terms'] ?? 'N/A') . '</li>';

                foreach ($custom_fields as $csv_label => $meta_key) {
                    if (isset($data[$csv_label])) {
                        $post_content .= '<li><strong>' . esc_html($csv_label) . ':</strong> ' . esc_html($data[$csv_label]) . '</li>';
                    }
                }

                $post_content .= '</ul>';

                wp_update_post([
                    'ID'           => $post_id,
                    'post_content' => $post_content,
                    'post_status'  => 'publish',
                ]);


                continue; // skips to next row
            } else {
                // ✅ Create new post

                $blurb = '';
                if (get_option('auction_enable_gpt_blurb')) {
                    $blurb = generate_auction_blurb($data);
                }
                
                $post_content = '';

                if (!empty($blurb)) {
                    $post_content .= '<p><strong>Property Highlight:</strong> ' . esc_html($blurb) . '</p><hr>';
                }
                
                $post_content .= '<h2>Auction Details</h2><ul>';
                $post_content .= '<li><strong>Status:</strong> ' . esc_html($data['Status'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Auctioneer:</strong> ' . esc_html($data['Auctioneer'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>City:</strong> ' . esc_html($data['City'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>State:</strong> ' . esc_html($data['State'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Zip:</strong> ' . esc_html($data['Zip'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Date:</strong> ' . esc_html($data['Date'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Time:</strong> ' . esc_html($data['Time'] ?? 'N/A') . '</li>';
                $post_content .= '<li><strong>Listing Link:</strong> <a href="' . esc_url($data['Listing Link'] ?? '#') . '">View Listing</a></li>';
                $post_content .= '<li><strong>Terms:</strong> ' . esc_html($data['Terms'] ?? 'N/A') . '</li>';

                $custom_fields = get_option('auction_custom_fields', []);
                foreach ($custom_fields as $csv_label => $meta_key) {
                    if (isset($data[$csv_label])) {
                        $post_content .= '<li><strong>' . esc_html($csv_label) . ':</strong> ' . esc_html($data[$csv_label]) . '</li>';
                    }
                }

                $post_content .= '</ul>';

                if (!empty($data['Image'])) {
                    $post_content .= '<p><img src="' . esc_url($data['Image']) . '" alt="Auction Image" /></p>';
                }

                $new_post_id = wp_insert_post([
                    'post_title'   => $post_title,
                    'post_content' => $post_content,
                    'post_status'  => 'publish',
                    'post_type'    => 'post',
                ]);

                if ($new_post_id) {
                    update_post_meta($new_post_id, 'auction_sheet_id', $unique_id);
                    update_post_meta($new_post_id, 'auction_status', $data['Status'] ?? '');
                    update_post_meta($new_post_id, 'auction_date', $data['Date'] ?? '');
                    update_post_meta($new_post_id, 'auction_time', $data['Time'] ?? '');
                    if (!empty($blurb)) {
                        update_post_meta($new_post_id, 'auction_blurb', $blurb);
                    }

                    foreach ($custom_fields as $csv_label => $meta_key) {
                        if (isset($data[$csv_label])) {
                            update_post_meta($new_post_id, $meta_key, sanitize_text_field($data[$csv_label]));
                        }
                    }

                    error_log("🆕 Created post: {$post_title} | Post ID: {$new_post_id}");
                    $results['new_posts'][] = $post_title;
                } else {
                    error_log("❌ Failed to create post for: {$post_title}");
                }
            }
        }

        return $results;
    } finally {
        // Always clear the transient when done, even if an error occurred
        delete_transient('auction_sync_running');
    }
}

add_action('admin_menu', function () {
    add_submenu_page(
        'auction-sync',
        'Custom Fields',
        'Custom Fields',
        'manage_options',
        'auction-custom-fields',
        'render_custom_fields_page'
    );
});

function render_custom_fields_page()
{
    if (!current_user_can('manage_options')) return;

    // Get existing mappings
    $field_mappings = get_option('auction_custom_fields', []);

    // Handle form submit
    if (isset($_POST['save_fields'])) {
        $labels = array_map('sanitize_text_field', $_POST['column_label'] ?? []);
        $meta_keys = array_map('sanitize_text_field', $_POST['meta_key'] ?? []);

        $new_mappings = [];
        foreach ($labels as $index => $label) {
            if (!empty($label) && !empty($meta_keys[$index])) {
                $new_mappings[$label] = $meta_keys[$index];
            }
        }

        update_option('auction_custom_fields', $new_mappings);
        $field_mappings = $new_mappings;

        echo '<div class="updated"><p>Fields updated!</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>Custom Field Mapping</h1>';
    echo '<form method="post">';
    echo '<table class="form-table"><tr><th>CSV Column Label</th><th>Post Meta Key</th></tr>';

    // Render existing fields
    if (!empty($field_mappings)) {
        foreach ($field_mappings as $label => $meta_key) {
            echo '<tr>';
            echo '<td><input type="text" name="column_label[]" value="' . esc_attr($label) . '"></td>';
            echo '<td><input type="text" name="meta_key[]" value="' . esc_attr($meta_key) . '"></td>';
            echo '</tr>';
        }
    }

    // Blank row for new entry
    echo '<tr>';
    echo '<td><input type="text" name="column_label[]" value=""></td>';
    echo '<td><input type="text" name="meta_key[]" value=""></td>';
    echo '</tr>';

    echo '</table>';

    submit_button('Save Fields', 'primary', 'save_fields');

    echo '</form>';
    echo '</div>';
}
