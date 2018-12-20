<?php
/*
 * Add the following routes:
 * - '/nifty_backups/v1/ping_backup'  
 */


add_action('rest_api_init', 'nifty_backups_rest_routes_init');
function nifty_backups_rest_routes_init() {
	register_rest_route(
		'nifty_backups/v1','/ping_backup', array(
		'methods' => 'GET, POST',
		'callback' => 'nifty_backups_ping_backup'
	));

	do_action("nifty_backups_api_route_hook");
}

add_action("nifty_backups_general_settings_output_hook","nifty_backups_hook_control_general_settings_output_hook",10);
function nifty_backups_hook_control_general_settings_output_hook($nifty_backup_options) {
	echo "<h3>".__("REST API","nifty-backups")."</h3>";
	echo "<table class='wp-list-table widefat fixed'>";
	echo "<tr>";
	echo "<td>";
	echo __("REST API Secret Token","nifty-backups");
	echo "</td>";
	echo "<td>";
	echo "<input type='text' style='width:100%;' readonly value='".get_option("nifty_backups_api_secret_token")."' />";
	echo "</td>";
	echo "</tr>";
	echo "</table>";
}

/**
 * Pings the backup script to see if there is any work to be done
 * @param  WP_REST_Request $request 
 * @return array                    	Success or failure output
 */
function nifty_backups_ping_backup(WP_REST_Request $request){
	$return_array = array();
	if(isset($request)){
		if(isset($request['token'])){
			$check_token = get_option('nifty_backups_api_secret_token');
			if($check_token !== false && $request['token'] === $check_token){
				if (class_exists("CodeCabinBackups")) {
					global $nifty_backup_class;
					$nifty_backup_class->nifty_backups_cron();
					$return_array['response'] = "success";
					$return_array['code'] = "200";
					
				} else {
					$return_array['response'] = "Nifty Backups class not found";
					$return_array['code'] = "401";
					$return_array['requirements'] = array("class" => "NIFTYBACKUPS");
				}
			
		 	} else {
				$return_array['response'] = "Secret token is invalid";
				$return_array['code'] = "401";
			}
		}else{
			$return_array['response'] = "No secret 'token' found";
			$return_array['code'] = "401";
			$return_array['requirements'] = array("token" => "YOUR_SECRET_TOKEN");
		}
	}else{
		$return_array['response'] = "No request data found";
		$return_array['code'] = "400";
		$return_array['requirements'] = array("token" => "YOUR_SECRET_TOKEN");
	}
	
	return $return_array;
}