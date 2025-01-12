<?php
/*
Plugin Name: Track Ref Param
Plugin URI: https://github.com/sandeep2rawat/yourls-plugins/tree/main/track-ref-param
Description: Logs additional query parameter (e.g., `r`) in YOURLS logs for analytics.
Version: 1.0
Author: Sandeep Rawat
Author URI: https://github.com/sandeep2rawat
*/

if (!defined('YOURLS_ABSPATH')) die();

yourls_add_filter('shunt_log_redirect', 'track_ref_param_log_redirect');

function track_ref_param_log_redirect($pre, $keyword) {
    // Check for the query parameter
    $r_param = isset($_GET['r']) ? $_GET['r'] : null;
    if ($r_param === null) {
        return false; // Allow default logging if `r` is not present
    }

    if (!yourls_do_log_redirect()) {
        return true;
    }

    // Get required info
    $table = YOURLS_DB_TABLE_LOG;
    $ip = yourls_get_IP();
    $binds = [
        'now'      => date('Y-m-d H:i:s'),
        'keyword'  => yourls_sanitize_keyword($keyword),
        'referrer' => substr(yourls_get_referrer(), 0, 200),
        'ua'       => substr(yourls_get_user_agent(), 0, 255),
        'ip'       => $ip,
        'location' => yourls_geo_ip_to_countrycode($ip),
        'r_param'  => substr($r_param, 0, 255),
    ];

    // Log the data
    try {
        $result = yourls_get_db()->fetchAffected(
            "INSERT INTO `$table` (click_time, shorturl, referrer, user_agent, ip_address, country_code, r_param) 
             VALUES (:now, :keyword, :referrer, :ua, :ip, :location, :r_param)",
            $binds
        );
    } catch (Exception $e) {
        $result = 0;
    }

    return $result;
}

// Add the `r_param` column and index on activation
yourls_add_action('activated_track-ref-param/plugin.php', function () {
    $table = YOURLS_DB_TABLE_LOG;
    $db = yourls_get_db();

    try{
        // Add the column
        $add_column_sql = "ALTER TABLE `$table` ADD COLUMN `r_param` VARCHAR(255) NOT NULL DEFAULT 'direct'";
        $db->fetchAffected($add_column_sql);

        // Add the index
        $add_index_sql = "CREATE INDEX `idx_r_param` ON `$table` (`r_param`)";
        $db->fetchAffected($add_index_sql);
    } catch (Exception $e) {
        yourls_debug_log("Field 'r_param' already exists.");
    }
});

// Delete table when plugin is deactivated
yourls_add_action('deactivated_track-ref-param/plugin.php', function () {
	$r_prarm_drop = yourls_get_option('r_prarm_drop');
	if ( $r_prarm_drop !== 'false' ) {
		$table = YOURLS_DB_TABLE_LOG;
        $db = yourls_get_db();
        // Drop the index if it exists
        try {
            $drop_index_sql = "DROP INDEX `idx_r_param` ON `$table`";
            $db->fetchAffected($drop_index_sql);
        } catch (Exception $e) {
            // Handle the exception (likely index doesn't exist)
            yourls_debug_log("Index `idx_r_param` does not exist or cannot be dropped.");
        }
        // Drop the column if it exists
        try {
            $drop_column_sql = "ALTER TABLE `$table` DROP COLUMN `r_param`";
            $db->fetchAffected($drop_column_sql);
        } catch (Exception $e) {
            // Handle the exception (likely column doesn't exist)
            yourls_debug_log("Column `r_param` does not exist or cannot be dropped.");
        }

	}
});


## API

/**
 * Parse request parameter to handle both array and comma-separated values.
 *
 * @param mixed $input The input parameter to parse.
 * @return array Parsed array of values.
 */
function parse_params($input) {
    if (is_array($input)) {
        return $input; // Already an array
    }
    if (is_string($input)) {
        return explode(',', $input); // Comma-separated values
    }
    return []; // Return an empty array if input is invalid
}


// Add action to handle API request for referrer stats
yourls_add_filter( 'api_action_ref-stats', 'handle_ref_stats_api' );

/**
 * API function wrapper: Return stats of a shorturl
 *
 * @since 1.9
 * @return array Result of API call
 */
function handle_ref_stats_api() {
    // Check if the shorturl parameter is set
    if( !isset( $_REQUEST['shorturl'] ) ) {
        return [
            'statusCode' => 400,
            'message'    => 'Missing shorturl parameter',
        ];
    }
    
    $shorturl = $_REQUEST['shorturl'];

    // Pagination parameters
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
    $per_page = isset($_REQUEST['per_page']) ? (int)$_REQUEST['per_page'] : 10;
    $r_params = isset($_REQUEST['r-params']) ? parse_params($_REQUEST['r-params']) : [];
    $rparam_filter = !empty($r_params) ? "AND r_param IN ('" . implode("', '", $r_params) . "')" : "";

    // Cap per_page max value to 100
    $per_page = min($per_page, 100);

    // Get the database connection
    global $ydb;
    
    // SQL query with pagination and optional r_param filter
    $offset = ($page - 1) * $per_page;

    $binds = array( 'shorturl' => $shorturl );
    $query = "SELECT 
        r_param, 
        COUNT(*) AS clicks 
        FROM ". YOURLS_DB_TABLE_LOG ." 
        WHERE shorturl = :shorturl ". $rparam_filter ." 
        GROUP BY r_param 
        ORDER BY clicks DESC 
        LIMIT $offset, $per_page";

    $results = $ydb->fetchAll($query, $binds);

    // Count the distinct r_param values
    $count_query = "
    SELECT COUNT(DISTINCT r_param) AS total_r_params
    FROM ". YOURLS_DB_TABLE_LOG ." 
    WHERE shorturl = :shorturl ". $rparam_filter ;

    $total = $ydb->fetchValue($count_query, $binds);

    // Calculate total pages
    $total_pages = ceil($total / $per_page);


    // TODO: Add pagination
    return [
        'status' => 'success',
        'statusCode' => 200,
        'data' => $results,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages,
        ]
    ];
}


yourls_add_action("pre_yourls_infos", "load_ref_stats");

function load_ref_stats($shorturl) {
    global $ydb;
    $query = "
        SELECT 
        r_param,
        COUNT(*) AS clicks
        FROM " . YOURLS_DB_TABLE_LOG . "
        WHERE shorturl = :shorturl
        GROUP BY r_param
        ORDER BY clicks DESC;
    ";
    $binds = ['shorturl' => $shorturl];
    $results = $ydb->fetchAll($query, $binds);

    // Create table rows
    $rows = '';
    foreach ($results as $row) {
        $rows .= "<tr><td>{$row['r_param']}</td><td>{$row['clicks']}</td></tr>";
    }

    $loc = yourls_plugin_url(dirname(__FILE__));
    $css_file = $loc . "/assets/style.css";
    $ref_stats_label = yourls__("Ref Stats");
    // Add the tab and content dynamically
    echo <<<HTML
        <link rel="stylesheet" href="$css_file">
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Locate the tabs container
            let tabsContainer = document.querySelector("#tabs > .wrap_unfloat > ul");
            if (!tabsContainer) {
                console.error("Tabs container not found.");
                return;
            }

            // Create the new tab
            let refTab = document.createElement("li");
            refTab.innerHTML = '<a href="#stat_tab_ref"><h2>{$ref_stats_label}</h2></a>';
            tabsContainer.appendChild(refTab);

            // Add the new content section
            let newTabContent = document.createElement("div");
            newTabContent.id = "stat_tab_ref";
            newTabContent.classList.add("tab");
            newTabContent.innerHTML = `
                <h2>{$ref_stats_label}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>r_param</th>
                            <th>clicks</th>
                        </tr>
                    </thead>
                    <tbody>
                        $rows
                    </tbody>
                </table>
            `;
            document.querySelector("#tabs").appendChild(newTabContent);
        });
        </script>
    HTML;
}
