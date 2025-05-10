<?php
/**
 * Plugin Name: Auction Blog Post Importer (Apt & Nimble LLC)
 * Description: Fetches auction listings from Google Sheet CSV and creates Auction Posts.
 * Version: 1.4
 * Author: Dean Miranda
 */

// --- Register Settings ---
add_action('admin_init', function () {
    register_setting('auction_sync_settings', 'auction_google_sheet_url');
    register_setting('auction_sync_settings', 'auction_enable_gpt_blurb');
    register_setting('auction_sync_settings', 'auction_openai_api_key');
});

// --- Register Custom Post Type ---
function register_auction_post_type()
{
    register_post_type('auction_post', [
        'label' => 'Auction Posts',
        'labels' => [
            'name' => 'Auction Posts',
            'singular_name' => 'Auction Post',
            'add_new' => 'Add New Auction',
            'add_new_item' => 'Add New Auction Post',
            'edit_item' => 'Edit Auction Post',
            'new_item' => 'New Auction Post',
            'view_item' => 'View Auction Post',
            'search_items' => 'Search Auction Posts',
            'not_found' => 'No Auction Posts found',
            'menu_name' => 'Auction Posts',
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_in_rest' => true,
        'menu_position' => 5,
        'supports' => ['title', 'editor', 'custom-fields'],
        'has_archive' => true,
        'rewrite' => ['slug' => 'auctions'],
        'menu_icon' => 'dashicons-hammer',
    ]);
}
add_action('init', 'register_auction_post_type');

// --- Admin Page ---
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

            echo '<tr><th scope="row">Google Sheet CSV URL</th><td>';
            echo '<input type="text" name="auction_google_sheet_url" value="' . esc_attr(get_option('auction_google_sheet_url', '')) . '" size="80" /></td></tr>';

            echo '<tr><th scope="row">Enable GPT Blurb</th><td>';
            echo '<input type="checkbox" name="auction_enable_gpt_blurb" value="1" ' . checked(1, get_option('auction_enable_gpt_blurb', 0), false) . ' /> Generate AI-powered blurbs</td></tr>';

            echo '<tr><th scope="row">OpenAI API Key</th><td>';
            echo '<input type="text" name="auction_openai_api_key" value="' . esc_attr(get_option('auction_openai_api_key', '')) . '" size="80" />';
            echo '<p class="description">Enter your OpenAI API Key here. Leave blank to disable GPT blurbs.</p></td></tr>';

            echo '</table>';
            submit_button('Save Settings');
            echo '</form>';

            echo '<form method="post"><button name="sync" class="button-primary">Sync & Create/Update Auction Posts</button></form>';

            if (isset($_POST['sync'])) {
                $result = sync_auction_data_from_google_sheet();
                echo '<div class="updated"><p><strong>Sync completed!</strong></p>';
                if (!empty($result['error'])) {
                    echo '<p>Error: ' . esc_html($result['error']) . '</p>';
                } elseif (!empty($result['new_posts'])) {
                    echo '<p>✅ New Auction Posts Created:</p><ul>';
                    foreach ($result['new_posts'] as $title) {
                        echo '<li>' . esc_html($title) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No new posts created.</p>';
                }
                echo '</div>';
            }
        },
        'dashicons-networking'
    );
});

// --- Blurb Generation ---
function generate_auction_blurb($data)
{
    $api_key = trim(get_option('auction_openai_api_key', ''));
    if (empty($api_key)) return '';

    $prompt = "Write a professional auction listing blurb under 100 words.\n\n";
    foreach (['Address', 'City', 'State', 'Zip', 'Date', 'Time', 'Auctioneer', 'Deposit', 'Terms'] as $field) {
        $prompt .= "$field: " . ($data[$field] ?? 'N/A') . "\n";
    }

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.7,
            'max_tokens' => 200,
        ]),
    ]);

    if (is_wp_error($response)) return '';
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return trim($body['choices'][0]['message']['content'] ?? '');
}

// --- Main Sync Function ---
function sync_auction_data_from_google_sheet()
{
    $csv_url = get_option('auction_google_sheet_url', '');
    if (empty($csv_url)) return ['error' => 'No CSV URL set.'];

    $rows = [];
    if (($handle = fopen($csv_url, 'r')) !== false) {
        while (($row = fgetcsv($handle)) !== false) {
            if (empty(array_filter($row))) continue;
            $rows[] = array_map('trim', $row);
        }
        fclose($handle);
    }

    if (count($rows) < 2) return ['error' => 'No data in CSV.'];
    $headers = array_map('trim', array_shift($rows));

    $results = ['new_posts' => []];
    foreach ($rows as $row) {
        $data = array_combine($headers, $row);
        $title = sanitize_text_field($data['Address'] ?? 'Untitled Auction');
        $unique_id = strtolower(sanitize_title($title));

        $existing = get_posts([
            'post_type' => 'auction_post',
            'meta_query' => [[
                'key' => 'auction_sheet_id',
                'value' => $unique_id,
            ]],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if ($existing) continue;

        $blurb = get_option('auction_enable_gpt_blurb') ? generate_auction_blurb($data) : '';

        $content = $blurb ? '<p><strong>Highlight:</strong> ' . esc_html($blurb) . '</p><hr>' : '';
        $content .= '<h2>Details</h2><ul>';
        foreach ($data as $label => $value) {
            $content .= '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
        }
        $content .= '</ul>';

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_type' => 'auction_post',
            'post_content' => $content,
            'post_status' => 'publish',
        ]);

        if ($post_id) {
            update_post_meta($post_id, 'auction_sheet_id', $unique_id);
            update_post_meta($post_id, 'auction_blurb', $blurb);
            foreach ($data as $label => $value) {
                update_post_meta($post_id, sanitize_key($label), sanitize_text_field($value));
            }
            $results['new_posts'][] = $title;
        }
    }

    return $results;
}

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('auction_post')) return; 

    $plugin_url = plugin_dir_url(__FILE__);
    $plugin_path = plugin_dir_path(__FILE__);

    wp_enqueue_script(
        'auto-poster-script',
        $plugin_url . 'auto-poster.js',
        [],
        filemtime($plugin_path . 'auto-poster.js'),
        true
    );
});

add_filter('single_template', function ($template) {
    global $post;

    if ($post->post_type === 'auction_post') {
        $plugin_template = plugin_dir_path(__FILE__) . 'single-auction_post.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    return $template;
});