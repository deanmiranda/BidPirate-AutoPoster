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
    register_setting('auction_sync_settings', 'auction_openai_api_key');
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
                echo '<input type="checkbox" name="auction_enable_gpt_blurb" value="1" ' . checked(1, get_option('auction_enable_gpt_blurb', 0), false) . ' />';
                echo ' Generate AI-powered blurbs';
                echo '</td></tr>';

                // OpenAI API Key Input
                echo '<tr><th scope="row">OpenAI API Key</th><td>';
                echo '<input type="text" name="auction_openai_api_key" value="' . esc_attr(get_option('auction_openai_api_key', '')) . '" size="80" />';
                echo '<p class="description">Enter your OpenAI API Key here. Leave blank to disable GPT blurbs.</p>';
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
    $api_key = trim(get_option('auction_openai_api_key', ''));

    if (empty($api_key)) {
        error_log('OpenAI API key missing or not set.');
        return ''; // Disable blurb generation if no API key.
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

function sync_auction_data_from_google_sheet() {

    // Check if another process is already running
    if (get_transient('auction_sync_running')) {
        error_log("⚠️ Another sync process is already running. Skipping this run.");
        return ['error' => 'Another sync process is already running.'];
    }

    // Set a transient to indicate we're running (expires after 5 minutes)
    set_transient('auction_sync_running', true, 5 * MINUTE_IN_SECONDS);

    static $currently_processing = [];

    $results = [
        'new_posts'    => [],
        'review_posts' => [],
    ];

    try {
        $csv_url = get_option('auction_google_sheet_url', '');

        if (empty($csv_url)) {
            error_log('❌ Google Sheet URL is not set.');
            return ['error' => 'Google Sheet URL is not set. Please configure it in Auction Sync settings.'];
        }

        $rows_raw = [];
        if (($handle = fopen($csv_url, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                // Skip empty or invalid lines
                if (empty(array_filter($row))) {
                    continue;
                }
                $rows_raw[] = array_map('trim', $row);
            }
            fclose($handle);
        }

        if (empty($rows_raw) || count($rows_raw) < 2) {
            error_log('❌ No usable data found in the Google Sheet.');
            return ['error' => 'No usable data found in the Google Sheet.'];
        }

        // Extract headers
        $header = array_map('trim', array_shift($rows_raw));
        $seen_ids = [];

        foreach ($rows_raw as $row_index => $row) {
            // Ensure row matches header length
            if (count($row) !== count($header)) {
                error_log("⚠️ Row {$row_index} misaligned. Expected " . count($header) . ", got " . count($row));
                $row = array_pad($row, count($header), '');
                $row = array_slice($row, 0, count($header));
            }

            $data = array_combine($header, $row);
            if (!$data) {
                error_log("❌ array_combine failed on row {$row_index}");
                continue;
            }

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

            if (isset($currently_processing[$unique_id])) {
                error_log("⚠️ Already processing in this run: {$post_title}");
                continue;
            }

            $currently_processing[$unique_id] = true;

            error_log("🔍 Processing: {$post_title} | Unique ID: {$unique_id}");

            // Query for existing post
            $query = new WP_Query([
                'post_type'      => 'post',
                'post_status'    => ['publish', 'pending', 'draft'],
                'meta_query'     => [
                    [
                        'key'     => 'auction_sheet_id',
                        'value'   => $unique_id,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'cache_results'  => false,
                'no_found_rows'  => true,
            ]);

            $post_id = !empty($query->posts) ? $query->posts[0] : null;
            wp_reset_postdata();

            $custom_fields = get_option('auction_custom_fields', []);
            $blurb = '';

            if (get_option('auction_enable_gpt_blurb') && !empty(get_option('auction_openai_api_key'))) {
                $blurb = generate_auction_blurb($data);
            }
            

            $post_content = '';

            if (!empty($blurb)) {
                $post_content .= '<p><strong>Property Highlight:</strong> ' . esc_html($blurb) . '</p><hr>';
            }

            $post_content .= '<h2>Auction Details</h2><ul>';
    
            foreach ($data as $label => $value) {
                if (!empty($label)) {
                    $post_content .= '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
                }
            }
            

            $post_content .= '</ul>';

            if (!empty($data['Image'])) {
                $post_content .= '<p><img src="' . esc_url($data['Image']) . '" alt="Auction Image" /></p>';
            }

            if ($post_id) {
                // Fetch current post data
                $current_post = get_post($post_id);
                $existing_content = $current_post->post_content;

                // Fetch current meta values
                $current_status = get_post_meta($post_id, 'auction_status', true);
                $current_date   = get_post_meta($post_id, 'auction_date', true);
                $current_time   = get_post_meta($post_id, 'auction_time', true);
                $current_blurb  = get_post_meta($post_id, 'auction_blurb', true);

                // Flags to track changes
                $needs_update = false;
                $changes = [];

                // Check post content
                if (trim($existing_content) !== trim($post_content)) {
                    $needs_update = true;
                    $changes[] = 'Post content changed.';
                }

                // Check meta fields
                if ($current_status !== ($data['Status'] ?? '')) {
                    $needs_update = true;
                    $changes[] = 'Status changed.';
                }

                if ($current_date !== ($data['Date'] ?? '')) {
                    $needs_update = true;
                    $changes[] = 'Date changed.';
                }

                if ($current_time !== ($data['Time'] ?? '')) {
                    $needs_update = true;
                    $changes[] = 'Time changed.';
                }

                if ($current_blurb !== $blurb) {
                    $needs_update = true;
                    $changes[] = 'Blurb changed.';
                }

                if ($needs_update) {
                    error_log("✅ Updating post: {$post_title} (ID: {$post_id})");

                    wp_update_post([
                        'ID'           => $post_id,
                        'post_content' => $post_content,
                        'post_status'  => 'publish',
                    ]);

                    update_post_meta($post_id, 'auction_sheet_id', $unique_id);
                    update_post_meta($post_id, 'auction_status', $data['Status'] ?? '');
                    update_post_meta($post_id, 'auction_date', $data['Date'] ?? '');
                    update_post_meta($post_id, 'auction_time', $data['Time'] ?? '');
                    if (!empty($blurb)) {
                        update_post_meta($post_id, 'auction_blurb', $blurb);
                    }

                    $results['review_posts'][] = [
                        'title'   => $post_title,
                        'changes' => $changes,
                    ];
                } else {
                    error_log("✅ No changes detected for post: {$post_title} (ID: {$post_id})");
                }

            } else {
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
    } catch (Exception $e) {
        error_log("❌ Exception during sync: " . $e->getMessage());
        return ['error' => 'Unexpected error: ' . $e->getMessage()];
    } finally {
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

    // Load existing mappings
    $field_mappings = get_option('auction_custom_fields', []);

    // If no mappings exist, try to auto-populate from CSV headers
    if (empty($field_mappings)) {
        $csv_url = get_option('auction_google_sheet_url', '');

        if (!empty($csv_url)) {
            $csv = file_get_contents($csv_url);
            
            if ($csv) {
                $rows_raw = array_map('str_getcsv', explode("\n", $csv));
                $rows_raw = array_filter($rows_raw, function ($row) {
                    return count($row) && !empty(trim($row[0]));
                });

                if (!empty($rows_raw) && count($rows_raw) >= 2) {
                    $header = array_map('trim', array_shift($rows_raw));

                    // Auto-map headers to meta keys (sanitize keys)
                    foreach ($header as $label) {
                        $meta_key = sanitize_key(str_replace(' ', '_', strtolower($label)));
                        $field_mappings[$label] = $meta_key;
                    }

                    update_option('auction_custom_fields', $field_mappings);
                }
            }
        }
    }


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
