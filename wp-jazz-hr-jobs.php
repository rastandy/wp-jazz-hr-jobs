<?php
/*
   Plugin Name: Jazz HR Jobs Listings Plugin
   Description: Jazz HR is an online software that helps companies post jobs online, manage applicants and hire great employees.
   Plugin URI: http://www.niklasdahlqvist.com
   Author: Niklas Dahlqvist
   Author URI: http://www.niklasdahlqvist.com
   Version: 1.0.1
   Requires at least: 4.8.3
   License: GPL
 */

/*
   Copyright 2021  Niklas Dahlqvist  (email : dalkmania@gmail.com)

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License, version 2, as
   published by the Free Software Foundation.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

function joinFilteredStrings(array $data, string $delimiter = ', '): string
{
    // Filter out empty strings and strings with only whitespaces or tabs
    $filteredData = array_filter($data, function ($value) {
        return trim($value) !== '';
    });

    // Join the filtered data with the specified delimiter
    return implode($delimiter, $filteredData);
}

/**
 * Ensure class doesn't already exist
 */
if (! class_exists("JazzHRJobs")) {
    class JazzHRJobs
    {
        private $options;
        private $apiBaseUrl;

        /**
         * Start up
         */
        public function __construct()
        {
            $this->options = get_option('jazz_hr_settings');
            $this->api_key = $this->options['api_key'];
            $this->subdomain = 'https://' . $this->options['subdomain'] .'.applytojob.com';
            $this->job_url = $this->options['url_select'];
            $this->apiBaseUrl = 'https://api.resumatorapi.com/v1/';

            add_action('admin_menu', array( $this, 'add_plugin_page' ));
            add_action('admin_init', array( $this, 'page_init' ));
            add_action('wp_enqueue_scripts', [$this, 'plugin_styles']);
            add_action('admin_enqueue_scripts', [$this, 'admin_plugin_styles']);
            add_action('wp_ajax_cache_clear', [$this, 'clearCache']);
            add_shortcode('jazz_hr_job_listings', array( $this,'JobsShortCode'));
        }

        public function plugin_admin_styles()
        {
            wp_enqueue_style('jazz_jobs-admin-styles', $this->getBaseUrl() . '/assets/css/plugin-admin-styles.css');
        }

        public function plugin_styles()
        {
            global $post;

            if (is_404()) {
                return;
            }

            $shortcode = false;
            $fields = get_post_meta($post->ID);

            if (!empty($fields)) {
                foreach ($fields as $key => $val) {
                    if (substr($key, 0, 1) !== '_') {
                        if (preg_match('/jazz_hr_job_listings/', $val[0], $match)) {
                            $shortcode = true;
                        }
                    }
                }
            }

            if ($shortcode) {
                wp_enqueue_style('jazz-jobs-styles', $this->getBaseUrl() . '/assets/css/jazz-postings-styles.css');
                wp_enqueue_script('jazz-jobs-script', $this->getBaseUrl() . '/assets/js/jazz-jobs-filter.js', '1.0.0', true);
            }

            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'jazz_hr_job_listings')) {
                wp_enqueue_style('jazz-jobs-styles', $this->getBaseUrl() . '/assets/css/jazz-postings-styles.css');
                wp_enqueue_script('jazz-jobs-script', $this->getBaseUrl() . '/assets/js/jazz-jobs-filter.js', '1.0.0', true);
            }
        }

        public function admin_plugin_styles()
        {
            wp_enqueue_style('jazz-jobs-admin-styles', $this->getBaseUrl() . '/assets/css/jazz-postings-admin-styles.css', '1.0.0', true);
            wp_enqueue_script('jazz-admin', $this->getBaseUrl() . '/assets/js/jazz-admin.js', ['jquery'], '1.0.0', true);
        }

        /**
         * Add options page
         */
        public function add_plugin_page()
        {
            // This page will be under "Settings"
            add_management_page(
                'Jazz HR Settings Admin',
                'Jazz HR Settings',
                'manage_options',
                'jazz_hr-settings-admin',
                array( $this, 'create_admin_page' )
            );
        }

        /**
         * Options page callback
         */
        public function create_admin_page()
        {
            // Set class property
            $this->options = get_option('jazz_hr_settings'); ?>
    <div class="wrap jazz_jobs-settings">
        <h2>Jazz HR Settings</h2>
        <form method="post" action="options.php">
            <?php
            // This prints out all hidden setting fields
            settings_fields('jazz_hr_settings_group');
            do_settings_sections('jazz_hr-settings-admin');
            submit_button();
            submit_button('Clear Cache', 'delete', 'clear_cache', false); ?>
        </form>
    </div>
<?php
}

/**
 * Register and add settings
 */
public function page_init()
{
    register_setting(
        'jazz_hr_settings_group', // Option group
        'jazz_hr_settings', // Option name
        array( $this, 'sanitize' ) // Sanitize
    );

    add_settings_section(
        'jazz_hr_section', // ID
        'Jazz HR Settings', // Title
        array( $this, 'print_section_info' ), // Callback
        'jazz_hr-settings-admin' // Page
    );

    add_settings_field(
        'subdomain', // ID
        'Jazz HR Subdomain', // Title
        array( $this, 'jazz_hr_subdomain_callback' ), // Callback
        'jazz_hr-settings-admin', // Page
        'jazz_hr_section' // Section
    );

    add_settings_field(
        'api_key', // ID
        'Jazz HR API Key', // Title
        array( $this, 'jazz_hr_api_key_callback' ), // Callback
        'jazz_hr-settings-admin', // Page
        'jazz_hr_section' // Section
    );

    add_settings_field(
        'url_select', // ID
        'Jazz HR Job Posting URL', // Title
        array( $this, 'jazz_hr_url_select_callback' ), // Callback
        'jazz_hr-settings-admin', // Page
        'jazz_hr_section' // Section
    );
}

/**
 * Sanitize each setting field as needed
 *
 * @param array $input Contains all settings fields as array keys
 */
public function sanitize($input)
{
    $new_input = array();
    if (isset($input['api_key'])) {
        $new_input['api_key'] = sanitize_text_field($input['api_key']);
    }

    if (isset($input['subdomain'])) {
        $new_input['subdomain'] = sanitize_text_field($input['subdomain']);
    }

    if (isset($input['url_select'])) {
        $new_input['url_select'] = sanitize_text_field($input['url_select']);
    }

    return $new_input;
}

/**
 * Print the Section text
 */
public function print_section_info()
{
    echo '<p>Enter your settings below:';
    echo '<br />and then use the <strong>[jazz_hr_job_listings]</strong> shortcode to display the content.</p>';
    echo '<p>Use the <strong>[jazz_hr_job_listings sort_by=date sort_order=asc]</strong> shortcode to display the content ordered by created at date (Ascending). </p>';
    echo '<p>Use the <strong>[jazz_hr_job_listings sort_by=date sort_order=desc]</strong> shortcode to display the content ordered by created at date (Descending). </p>';
    echo '<p>Use the <strong>[jazz_hr_job_listings sort_by=title sort_order=asc]</strong> shortcode to display the content ordered by title (Ascending). </p>';
    echo '<p>Use the <strong>[jazz_hr_job_listings sort_by=title sort_order=desc]</strong> shortcode to display the content ordered by title (Descending). </p>';
}

/**
 * Get the settings option array and print one of its values
 */
public function jazz_hr_api_key_callback()
{
    printf(
        '<input type="text" id="api_key" class="narrow-fat" name="jazz_hr_settings[api_key]" value="%s" />',
        isset($this->options['api_key']) ? esc_attr($this->options['api_key']) : ''
    );
}

public function jazz_hr_subdomain_callback()
{
    printf(
        '<small>https://</small><input type="text" id="subdomain" class="narrow-fat" name="jazz_hr_settings[subdomain]" value="%s" /><small>.applytojob.com</small>',
        isset($this->options['subdomain']) ? esc_attr($this->options['subdomain']) : ''
    );
}

public function jazz_hr_url_select_callback()
{
?>
    <select id="url_select" name="jazz_hr_settings[url_select]">
        <option value="default" <?php selected($this->options['url_select'], "default"); ?>>Jazz HR Job Details Page</option>
        <option value="custom" <?php selected($this->options['url_select'], "custom"); ?>>Jazz HR Custom Job Page</option>
    </select>
<?php
}

public function JobsShortCode($atts)
{
    global $post;
    $args = shortcode_atts(array(
        'sort_by' => "",
        'sort_order' => ''
    ), $atts);
    
    if ((isset($this->api_key) && $this->api_key != '' && (isset($this->subdomain) && $this->subdomain != ''))) {
        $output = '';
        $positions = $this->get_jazz_positions();
        $positions = $this->sortJobs($positions, $args["sort_by"], $args["sort_order"]);

        $output .= "
                    <div class='job-filters'>
                      {$this->generateFilterDropdowns()}
                    </div>
                    <div class='filter-results'>
                      <div class='no-results-message hidden'>
                        <p class='sub-title'>There are currently no jobs matching your criteria.</p>
                      </div>";

        $output .= '<ul class="job-listings">';
        foreach ($positions as $position) {
            list($code, $title) = explode(' - ', $position['title'], 2);
            $output .= "<li class='job-listing' data-posting-id='{$position['id']}' data-filter-date='{$position['createdAt']}' data-filter-location='{$position['location']}' data-filter-department='{$position['department']}' data-filter-commitment='{$position['commitment']}' data-featured='0' data-show='true'>
                            <div class='posting'>
                             <div class='posting-title-wrapper'>
                              <div class='posting-title'>
                                <h4><a class='code' href='{$position['applyUrl']}' target='_blank'>{$code}</a></h4>
                                <h4><a class='title' href='{$position['applyUrl']}' target='_blank'>{$title}</a></h4>
                              </div>
                             </div>
                              <div class='posting-categories'>";

            if ($position['location'] && trim($position['location']) !== "")
                $output .= "
                                <div class='posting-category posting-location'>
                                  <svg class=\"icon svg-icon posting-location\" xmlns=\"http://www.w3.org/2000/svg\" width=\"18\" height=\"26\" viewBox=\"0 0 18 26\" fill=\"none\">
                                    <path d=\"M9 0C4.02429 0 0 4.069 0 9.1C0 15.925 9 26 9 26C9 26 18 15.925 18 9.1C18 4.069 13.9757 0 9 0ZM9 12.35C7.22571 12.35 5.78571 10.894 5.78571 9.1C5.78571 7.306 7.22571 5.85 9 5.85C10.7743 5.85 12.2143 7.306 12.2143 9.1C12.2143 10.894 10.7743 12.35 9 12.35Z\" fill=\"#08125A\"/>
                                  </svg><span href='#' class='sort-by-location posting-category posting-location'>{$position['location']}</span>
                                </div>";
            if ($position['department'] && trim($position['department']) !== "")
                $output .= "
                                <div class='posting-category department posting-department'>
                                    <svg class=\"icon svg-icon posting-team\" xmlns=\"http://www.w3.org/2000/svg\" width=\"23\" height=\"23\" viewBox=\"0 0 23 23\" fill=\"none\">
                                      <g clip-path=\"url(#clip0_2001_52)\">
                                        <path d=\"M15.3333 10.5416C16.9242 10.5416 18.1987 9.25746 18.1987 7.66663C18.1987 6.07579 16.9242 4.79163 15.3333 4.79163C13.7425 4.79163 12.4583 6.07579 12.4583 7.66663C12.4583 9.25746 13.7425 10.5416 15.3333 10.5416ZM7.66667 10.5416C9.2575 10.5416 10.5321 9.25746 10.5321 7.66663C10.5321 6.07579 9.2575 4.79163 7.66667 4.79163C6.07583 4.79163 4.79167 6.07579 4.79167 7.66663C4.79167 9.25746 6.07583 10.5416 7.66667 10.5416ZM7.66667 12.4583C5.43375 12.4583 0.958332 13.5795 0.958332 15.8125V18.2083H14.375V15.8125C14.375 13.5795 9.89958 12.4583 7.66667 12.4583ZM15.3333 12.4583C15.0554 12.4583 14.7392 12.4775 14.4037 12.5062C15.5154 13.3112 16.2917 14.3941 16.2917 15.8125V18.2083H22.0417V15.8125C22.0417 13.5795 17.5662 12.4583 15.3333 12.4583Z\" fill=\"#08125A\"/>
                                      </g>
                                      <defs>
                                        <clipPath id=\"clip0_2001_52\">
                                          <rect width=\"23\" height=\"23\" fill=\"white\"/>
                                        </clipPath>
                                      </defs>
                                    </svg> <span href='#' class='posting-department'>{$position['department']}</span>
                                </div>";
            if ($position['commitment'] && trim($position['commitment']) !== "")
                $output .= "
                                <div class='posting-category posting-commitment'>
                                    <span href='#' class='sort-by-commitment posting-category posting-commitment'>Type: {$position['commitment']}</span>
                                </div>";
            $output .= "
                            </div>
                            <div class='posting-apply'>
                                <a class='apply-button' href='{$position['applyUrl']}' target='_blank'>Apply</a>
                            </div>
                            </div> 
                            
                          </li>
            ";
        }
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';

        $output_wrapped = "<div class='jazz_jobs_wrapper'>
                                    <div class='output'>
                                        {$output}
                                    </div>
                                  </div>";
        return $output_wrapped;
    }
}

public function generateFilterDropdowns()
{
    // Location Filter
    $locations = $this->get_jazz_locations();
    $locations_array = array();
    $location_options = "<option value=''> Location </option>";
    foreach ($locations as $location) {
        if(strpos($location, ",") !== false) {
            list($job_locations, $region) = explode(', ', $location, 2);
        } else {
            $job_locations = $location;
        }
        foreach (explode(' - ', $job_locations) as $job_location) {
            $locations_array[] = $job_location;
        }
    }
    $unique_locations = array_unique($locations_array, );
    asort($unique_locations);
    foreach ($unique_locations as $location) {
        $location_options .= "<option value='{$location}'>$location</option>";
    }
    $location_output = "
            <select class='form-control filter' data-filter='location'>
              {$location_options}
            </select>
    ";

    // Teams
    $teams = $this->get_jazz_teams();
    $team_options = "<option value=''> - Team - </option>";
    foreach ($teams as $team) {
        $team_options .= "<option value='{$team}'>$team</option>";
    }
    $team_output = "
            <select class='form-control filter' data-filter='team'>
              {$team_options}
            </select>
    ";

    // Departments
    $depts = $this->get_jazz_departments();
    $dept_options = "<option value=''> - Department - </option>";
    foreach ($depts as $dept) {
        $dept_options .= "<option value='{$dept}'>$dept</option>";
    }
    $dept_output = "
            <select class='form-control filter' data-filter='department'>
              {$dept_options}
            </select>
    ";

    // Job Type / Commitment
    $commitments = $this->get_jazz_commitments();
    $commitment_options = "<option value=''> - Job Type - </option>";
    foreach ($commitments as $commitment) {
        $commitment_options .= "<option value='{$commitment}'>$commitment</option>";
    }
    $commitment_output = "
            <select class='form-control filter' data-filter='commitment'>
              {$commitment_options}
            </select>
    ";

    return "<div class='filter-row'>
              <div class='filter-input filter-location'>
                {$location_output} 
              </div>
              <div class='filter-input filter-department'>
                {$dept_output}
              </div>
              <div class='filter-input filter-commitment'>
                {$commitment_output}
              </div>
            </div>";
}

// Send Curl Request to Lever Endpoint and return the response
public function sendRequest()
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->apiBaseUrl . 'jobs/status/open?apikey=' .$this->api_key);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = json_decode(curl_exec($ch));
    return $response;
}

public function get_jazz_positions()
{
    // Get any existing copy of our transient data
    if (false === ($jobs = get_transient('jazz_positions'))) {
        // It wasn't there, so make a new API Request and regenerate the data
        $positions = $this->sendRequest();
        $jobs = [];

        if ($positions != '') {
            if (is_array($positions)) {
                foreach ($positions as $item) {
                    $jazz_position = [
                        'id' => $item->id,
                        'title' => $item->title,
                        'location' => joinFilteredStrings([$item->city, $item->state]),
                        'commitment' => $item->type,
                        'department' => $item->department,
                        'team' => $item->team_id ? $item->team_id : "",
                        'description' => preg_replace('#(<[a-z ]*)(style=("|\')(.*?)("|\'))([a-z ]*>)#', '\\1\\6', $item->description),
                        'applyUrl' => $this->generateApplyUrl($item),
                        'createdAt' => strtotime($item->original_open_date)
                    ];
                    //Ignore Spontaneous Application
                    if($item->title !== "Spontaneous Application")
                        array_push($jobs, $jazz_position);
                }
            } else {
                $jazz_position = [
                    'id' => $positions->id,
                    'title' => $positions->title,
                    'location' => joinFilteredStrings([$positions->city, $positions->state], ": "),
                    'commitment' => $positions->type,
                    'department' => $positions->department,
                    'team' => $positions->team_id,
                    'description' => preg_replace('#(<[a-z ]*)(style=("|\')(.*?)("|\'))([a-z ]*>)#', '\\1\\6', $positions->description),
                    'applyUrl' => $this->generateApplyUrl($positions),
                    'createdAt' => strtotime($positions->original_open_date)
                ];
                if($positions->title !== "Spontaneous Application")
                    array_push($jobs, $jazz_position);
            }

            // Cache the Response
            $this->storeJazzPostions($jobs);
        }
    } else {
        // Get any existing copy of our transient data
        $jobs = unserialize(get_transient('jazz_positions'));
    }
    // Finally return the data
    return $jobs;
}

public function get_jazz_locations()
{
    $locations = array();
    $positions = $this->get_jazz_positions();

    foreach ($positions as $position) {
        $locations[]  = $position['location'];
    }

    $locations = array_unique($locations);
    sort($locations);

    return $locations;
}

public function get_jazz_commitments()
{
    $commitments = array();
    $positions = $this->get_jazz_positions();

    foreach ($positions as $position) {
        $commitments[]  = $position['commitment'];
    }

    $commitments = array_unique($commitments);
    sort($commitments);

    return $commitments;
}

public function get_jazz_teams()
{
    $teams = array();
    $positions = $this->get_jazz_positions();

    foreach ($positions as $position) {
        $teams[]  = $position['team'];
    }

    $teams = array_unique($teams);
    sort($teams);

    return $teams;
}

public function get_jazz_departments()
{
    $depts = [];
    $positions = $this->get_jazz_positions();

    foreach ($positions as $position) {
        $depts[] = $position['department'];
    }

    $depts = array_unique($depts);
    sort($depts);

    return $depts;
}

public function sortJobs($jobs, $sortBy, $sortOrder) {
    if($sortBy === "title" && $sortOrder === "asc") {
        
        usort($jobs, function($a, $b)
            {
                return strtolower($a["title"]) > strtolower($b["title"]);
            });
    }

    if($sortBy === "title" && $sortOrder === "desc") {
        usort($jobs, function($a, $b)
            {
                return strtolower($a["title"]) < strtolower($b["title"]);
            });
    }

    if($sortBy === "date" && $sortOrder === "asc") {
        usort($jobs, function($a, $b) {
            return strtotime($a["createdAt"]) - strtotime($b["createdAt"]);
        });
    }

    if($sortBy === "date" && $sortOrder === "desc") {
        usort($jobs, function($a, $b) {
            return strtotime($b["createdAt"]) - strtotime($a["createdAt"]);
        });
    }

    return $jobs;

    
}

public function generateApplyUrl($position) {
    if($this->job_url == 'custom') {
        return $this->subdomain . '/apply/' . $position->board_code . '/' . sanitize_title($position->title);
    } else {
        return $this->subdomain . '/apply/jobs/details/' . $position->board_code;
    }
}

public function storeJazzPostions($positions)
{
    // Get any existing copy of our transient data
    if (false === ($jazz_data = get_transient('jazz_positions'))) {
        // It wasn't there, so regenerate the data and save the transient for 1 hour
        $jazz_data = serialize($positions);
        set_transient('jazz_positions', $jazz_data, 1 * HOUR_IN_SECONDS);
    }
}

public function flushStoredInformation()
{
    //Delete transient to force a new pull from the API
    delete_transient('jazz_positions');
}

public function clearCache()
{
    if (isset($_POST['action']) && $_POST['action'] === 'cache_clear') {
        $this->flushStoredInformation();
        $output = ['cache_cleared' => true, 'message' => 'The Transients for the Job Listings have been cleared'];
        echo json_encode($output);
        exit;
    }
}

//Returns the url of the plugin's root folder
protected function getBaseUrl()
{
    return plugins_url(null, __FILE__);
}

//Returns the physical path of the plugin's root folder
protected function getBasePath()
{
    $folder = basename(dirname(__FILE__));
    return WP_PLUGIN_DIR . "/" . $folder;
}
} //End Class

/**
 * Instantiate this class to ensure the action and shortcode hooks are hooked.
 * This instantiation can only be done once (see it's __construct() to understand why.)
 */
new JazzHRJobs();
} // End if class exists statement
