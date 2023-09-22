<?php
/*
Plugin Name: Google Sheet Custom api and banner top
Description: Display data from Google Sheet in a top banner.
Version: 1.0
Author: Practical Logix Shantanu
*/

if ( ! defined( 'ABSPATH' ) ) exit;
// Add the top banner to the header
function enqueue_custom_banner_styles() {
    // Enqueue the custom stylesheet
    wp_enqueue_style('custom-banner-style', plugin_dir_url(__FILE__) . 'custom-banner-style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_banner_styles');
function custom_banner_top_banner() {
    // Check if the banner is enabled in the plugin settings
    $is_banner_enabled = get_option('custom_banner_enable', false);
    if ($is_banner_enabled) {
        echo '<div class="custom-banner">';
        echo do_shortcode('[google_sheet_banner]');
        // Add any other content or messages you want to display in the top banner
        echo '</div>';
    }
}
add_action('wp_head', 'custom_banner_top_banner');

// Fetch data from the Google Sheets API
function fetch_data_from_google_sheet() {
    //Dev Credentials
    // $api_key = 'AIzaSyDKZoT0pUmHelHKIhdSWz_pVcRRKLVfkbw';
    // $spreadsheet_id = '1_fpRlfmlFfab7RN2PqJ7dpLchTv501SBU0dYaluXo6o';
    // $range = "'a2023'!A2:I78";
    //Production Credentials
    $api_key = 'AIzaSyC6s6NYVHPnil7H0VHyLvARZ2fEueUa7sc';
    $spreadsheet_id = '1BwIIG7eXZB8tDA7I6suf7503kXf5phrKH_i87UywPwE';
    $range = "'2023'!A2:H390";

    $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$range}?key={$api_key}";
    $response = wp_remote_get($api_url);

    if (is_array($response) && !is_wp_error($response)) {
        $data = json_decode($response['body'], true);

        if (isset($data['values'])) {
            $start_dates = [];
            $end_dates = [];
            $banner_languages = [];

            foreach ($data['values'] as $row) {
                if (count($row) === 8) {
                    $start_dates[] = $row[1];
                    $end_dates[] = $row[2];
                    $banner_languages[] = $row[7];
                }
            }
            // Return an associative array with segregated data
            return array(
                'start_dates' => $start_dates,
                'end_dates' => $end_dates,
                'banner_languages' => $banner_languages,
            );
            // var_dump('$start_dates');
            // die('test');
        } else {
            return 'No data found in the Google Sheet.';
        }
    } else {
        return 'Failed to fetch data from the Google Sheet.';
    }
}


// the endpoint is /wp-json/myplugin/v1/google-sheet-data
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/google-sheet-data', array(
        'methods' => 'GET',
        'callback' => 'fetch_data_from_google_sheet',
    ));
});


// Custom shortcode to display the dynamic banner

function custom_google_sheet_banner_shortcode() {
    $data = fetch_data_from_google_sheet();

    if ($data !== false) {

        $start_dates = $data['start_dates']; // An array of start dates
        $end_dates =  $data['end_dates'];   // An array of end dates
        $banner_languages =  $data['banner_languages']; // An array of banner languages

        if (count($start_dates) === count($end_dates) && count($start_dates) === count($banner_languages)) {
            $current_date = new DateTime("now", new DateTimeZone('America/New_York'));
            $current_date = $current_date->format("Y-m-d");

            // Loop through each row
            for ($i = 0; $i < count($start_dates); $i++) {

                if (!preg_match('/^\d{1,2}\/\d{1,2}$/', $start_dates[$i]) || !preg_match('/^\d{1,2}\/\d{1,2}$/', $end_dates[$i])) {
                    // Skip entries with invalid date formats
                    continue;
                }
                $start_date_obj = DateTime::createFromFormat('m/d', $start_dates[$i])->format('Y-m-d');
                $end_date_obj = DateTime::createFromFormat('m/d', $end_dates[$i])->format('Y-m-d');

                // Check if the current date is within the range of start date and end date
                if ($current_date >= $start_date_obj && $current_date <= $end_date_obj) {

                    $banner_language = $banner_languages[$i];

                    if ($banner_language !== null) {
                        $learn_more_link = '<a href="https://www.saatva.com/" target="_blank" style="color: #fff !important; text-decoration: underline;">Learn More</a>';
                        $banner_language = str_replace('Learn More', $learn_more_link, $banner_language);
                        $banner_language = str_replace("| Ends XX/XX", '', $banner_language);

                    }

                    return $banner_language;
                } else if ($current_date == $start_date_obj || $current_date == $end_date_obj) {
                    $banner_language = $banner_languages[$i];

                    if ($banner_language !== null) {

                        $learn_more_link = '<a href="https://www.saatva.com/" target="_blank" style="color: #fff !important; text-decoration: underline;">Learn More</a>';
                        $banner_language = str_replace('Learn More', $learn_more_link, $banner_language);
                        $banner_language = str_replace("| Ends XX/XX", '', $banner_language);
                    }

                    // Display the corresponding banner language
                    return $banner_language.$start_date_obj;
                }
            }

        }

        // Default output if the current date is not within any range
        return 'No banner to display for the current date.';
    } else {
        return 'Failed to fetch data from the Google Sheet.';
    }
}
add_shortcode('google_sheet_banner', 'custom_google_sheet_banner_shortcode');



// Adding a custom setting field for banner toggle
function custom_banner_settings_init() {
    add_settings_section(
        'custom_banner_settings_section',
        'Custom Top Banner Plugin Settings',
        'custom_banner_settings_section_cb',
        'general'
    );

    add_settings_field(
        'custom_banner_enable',
        'Enable Banner',
        'custom_banner_enable_cb',
        'general',
        'custom_banner_settings_section',
        array(
            'label_for' => 'custom_banner_enable',
        )
    );

    register_setting('general', 'custom_banner_enable');
}
add_action('admin_init', 'custom_banner_settings_init');

// Display the settings section heading
function custom_banner_settings_section_cb() {
    echo '<p>Toggle the custom top banner on or off:</p>';
}

// Display the setting field for banner toggle
function custom_banner_enable_cb($args) {
    $custom_banner_enable = get_option('custom_banner_enable', false);
    $checked = checked($custom_banner_enable, true, false);
    echo '<input type="checkbox" id="' . esc_attr($args['label_for']) . '" name="custom_banner_enable" value="1" ' . $checked . '/>';
}

// Add a link to the plugin settings on the plugin page
function custom_banner_plugin_settings_link($links) {
    $settings_link = '<a href="options-general.php#custom_banner_enable">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'custom_banner_plugin_settings_link');






