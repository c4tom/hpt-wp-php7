<?php
/*
  Plugin Name: Nifty Backups
  Plugin URI: http://niftybackups.com
  Description: The easiest to use and most reliable WordPress Backup Plugin.
  Version: 1.08
  Author: NickDuncan
  Author URI: http://codecabin.co.za
  Text Domain: nifty-backups
  Domain Path: /languages
 */


/*
 * 1.08 - 2016-11-25
 * Added text domain and the first language pack (English)
 * Stopped emails from being sent out incorrectly when the zipping of files fails
 * Changed default backup table rows from 200 to 2000
 * Added the correct filter to allow for HTML emails
 * Added more stringent checks when zipping a file
 * 
 * 1.07 - 2016-11-23
 * Fixed a bug where we referenced $this incorrectly
 * 
 * 1.06 - 2016-11-21
 * Added a welcome page
 * 
 * 1.05 - 2016-11-16
 * Added "send backup files to email" as an offsite backup option
 * Added comprehensive email notifications with a customisable backup email template
 * Removed default verbose logging during backups
 * Fixed PHP warning bugs
 * 
 * 
 * 1.04 - 2016-11-14
 * Added a link to a tutorial for users experiencing the "ZipArchive" module not found warning
 * 
 * 1.03
 * Modifications to the buttons
 * Added integrity checking to the DB sequence
 * Changed the style of the UI
 * 
 * 1.02
 * Maintenance mode now introduced when restoring a backup
 * Added functionality to ignore backing up nifty system files
 * The backup now automatically ignores "Thumbs.db" which causes issues on windows systems
 * You now get notified via email when a backup is complete and when a restore is complete
 *
 * 1.01
 * Added integrity checks for all files that have been backed up. The system now checks and makes sure it has backed up all the files it wanted to backup originally. If it cannot backup a file for any reason, it will display a list of all the files that couldnt be zipped
 *
 * 1.00
 * Launch
 *
 * 
 */




define("NIFTY_BU_DEBUG",true);
define("NIFTY_BU_VERBOSE_DEBUG",false);
// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}
if (!defined('NIFTY_DASHBOARD_PAGE')) { define('NIFTY_DASHBOARD_PAGE','nifty-backups'); }


include "includes/REST_api.php";
include "includes/module_notifications.php";
include "includes/module_emails.php";
include "includes/module_offsite.php";

class CodeCabinBackups{

	var $upload_dir;
	var $upload_url;
	var $destination;
	var $file_integrity;
	var $filename;
	var $zip_filename_db;
	var $nice_zip_filename_db;
	var $zip_filename_files;
	var $nice_zip_filename_files;
	var $nicename;

	var $max_allowed_rows_per_session;
	var $max_read_rows;
	var $max_allowed_files_per_session;

	var $backup_directory;
	var $backup_url;

	var $restored_file_sql;

	var $current_version;

	public function __construct(){



		$this->current_version = "1.08";

		$this->upload_dir =(defined('WP_CONTENT_DIR')) ? WP_CONTENT_DIR . $this->DS().'uploads' : ABSPATH . 'wp-content' . $this->DS() . 'uploads';
		$this->upload_url =(defined('WP_CONTENT_URL')) ? WP_CONTENT_URL . '/uploads' : get_option('siteurl') . '/wp-content/uploads';
		
		$this->admin_scripts();
		add_action( 'admin_menu', array( $this, 'backup_menu_items' ) );

		add_action('wp_ajax_nifty_backup', array( $this, 'action_callback' ));
		add_action('wp_ajax_nifty_backup_start', array( $this, 'action_callback' ));
		add_action('wp_ajax_nifty_backup_info', array( $this, 'action_callback' ));
		add_action('wp_ajax_nifty_restore', array( $this, 'action_callback' ));
		add_action('wp_ajax_nifty_restore_file', array( $this, 'action_callback' ));
		add_action('wp_ajax_nifty_cancel_backup', array( $this, 'action_callback' ));
		add_action('wp_ajax_nifty_restore_external', array( $this, 'action_callback' ));

		add_action('wp_ajax_nifty_delete_file', array( $this, 'action_callback'));
		add_action('wp_ajax_nifty_cloud_upload', array( $this, 'action_callback' ));
		add_action('wp_ajax_view_change', array( $this, 'action_callback'));
		add_action('wp_ajax_nifty-save-settings', array( $this, 'action_callback'));

		add_action("nifty_backup_db_settings", array($this, 'view_db_settings')); 
		add_action("nifty_backup_support_page", array($this, 'view_support_page')); 
		add_action("nifty_backup_schedule_settings", array($this, 'view_schedule_settings')); 
		add_action("nifty_backup_general_settings", array($this, 'view_general_settings')); 
		add_action("nifty_backup_file_settings", array($this, 'view_file_settings')); 


		add_action( "nifty_backups_db_settings_output_hook", array($this, "nifty_backups_hook_control_db_settings_output_hook") , 10, 1 );
		add_action( "nifty_backups_file_settings_output_hook", array($this, "nifty_backups_hook_control_file_settings_output_hook") , 10, 1 );
		add_action( "nifty_backups_schedule_settings_output_hook", array($this, "nifty_backups_hook_control_schedule_settings_output_hook") , 10, 1 );

		


		add_action("nifty_bu_build_button", array($this,"nifty_build_button"));
		register_activation_hook( __FILE__, array($this, 'plugin_activate') );
		register_deactivation_hook( __FILE__, array($this, 'plugin_deactivate') );

		add_action( 'nifty_cron_hook', array($this, 'nifty_backups_cron') );

		add_filter( 'cron_schedules', array($this, 'nifty_backups_cron_add_minutely') );

		add_filter( 'nifty_backup_filter_main_menu', array( $this , 'nifty_backup_menu_control_dashboard') ,1,1 );
		add_filter( 'nifty_backup_filter_main_menu', array( $this , 'nifty_backup_menu_control_settings') ,2,1 );
		add_filter( 'nifty_backup_filter_main_menu', array( $this , 'nifty_backup_menu_control_schedules') ,8,1 );
		add_filter( 'nifty_backup_filter_main_menu', array( $this , 'nifty_backup_menu_control_support') ,15,1 );

		add_filter( 'nifty_backup_filter_button_handling', array($this, 'nifty_backup_filter_control_button_handling'), 10, 1 );
    
		add_filter( "nifty_backup_filter_skip_db", array($this, "check_skip_db"),10,1 );
		add_filter( "nifty_backup_filter_skip_files", array($this, 'check_skip_files'),10,1 );


		add_action("init", array($this, "check_versions"));

		add_action( "nifty_backup_action_footer", array($this,"nifty_backup_action_control_footer"), 10, 1);

		add_action( "activated_plugin", array($this, "redirect_on_activate") );

		add_filter( "nifty_backups_filter_save_settings",array($this, "nifty_backups_filter_control_save_settings_general"),9,1);
		add_filter( "nifty_bu_filter_include_file",array($this, "nifty_backups_filter_control_exclude_system_files"),10,3);

		add_action( "admin_head", array($this, "nifty_bu_post" ) , 10 );

		$upload_dir = wp_upload_dir();
		$dir = $upload_dir['basedir'].$this->DS()."nifty-backups".$this->DS();
		define("nifty_bu_upload_dir",$dir);
		$upload_url = $upload_dir['baseurl']."/nifty-backups/";
		define("nifty_bu_upload_url",$upload_url);

		$this->nifty_handle_directory();

		$this->max_allowed_rows_per_session = 1000;
		$this->max_read_rows = 100;
		$this->max_allowed_files_per_session = 500;



		$this->file_integrity = $this->upload_dir . $this->DS() . 'file_list_integrity_check.txt';


		$this->filename = $this->upload_dir . $this->DS() . 'dump.sql';
		$this->nicename = 'dump.sql';



	}

	function nifty_bu_post() {
		if (isset($_POST['action']) && $_POST['action'] == 'nifty_bu_submit_find_us') {
		    if (function_exists('curl_version')) {

		        $request_url = "http://www.niftybackups.com/apif/rec.php";
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, $request_url);
		        curl_setopt($ch, CURLOPT_POST, 1);
		        curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
		        curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		        $output = curl_exec($ch);
		        curl_close($ch);

		    }

		}
	}

	public function nifty_backup_action_control_footer($post_data) {
		echo "<em><center>".__("* Nifty Backups encourages you to make use of best practice when backing up your important data. Backup solutions should be stacked - in other words, you should make sure that you use more than one backup solution when dealing with sensitive or important data. Nifty Backups should be one of a few solutions you use when backing up your data.","nifty-backups")."</em></center>";
	}
	public function debug_log($data) {
		$file = $this->upload_dir . $this->DS() . 'nifty_debug.json';
		$dest = fopen($file, "a+");
		@fwrite($dest, json_encode($data, JSON_PRETTY_PRINT)."\n\r");
		@fclose($dest);

	}

	function nifty_backups_cron() {
		do_action("nifty_backups_cron_hook");

		$this->debug_log(array("Running backup check"=>date("Y-m-d H:i:s")));

		/* only run if we are in progress with a backup */
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		if ($backups_in_progress) {
			$this->debug_log(array("backup in progress!"=>date("Y-m-d H:i:s")));
			$this->test_function();
		} else {
			$this->debug_log(array("no backup in progress"=>date("Y-m-d H:i:s")));
		}

		return true;

	}

	/**
	 * Runs on the init and fires only when the plugin has been updated (new version number)
	 * 
	 * @return void
	 * @since  1.00
	 */
	function check_versions() {
		
		$current_option = get_option("nifty_bu_version");
		if ($current_option !== $this->current_version) {
			/* new version, lets do some updates on the variables etc */

			$this->handle_defaults();
			update_option("nifty_bu_version",$this->current_version);

	    	if (!get_option("nifty_backups_api_secret_token")) {
				$token = $this->nifty_backups_api_key_create();
		        add_option("nifty_backups_api_secret_token", $token);
		    }
		}


	}


	function redirect_on_activate( $plugin ) {
		if( $plugin == plugin_basename( __FILE__ ) ) {
		    exit( wp_redirect( admin_url( 'admin.php?page=nifty-backups' ) ) );
		}
	}

	/**
	 * Runs when the plugin has been activated
	 * 
	 * @return void
	 * @since  1.00
	 */
	function plugin_activate() {
		$this->handle_defaults();
		$nifty_bu_cron_timestamp = wp_next_scheduled( 'nifty_cron_hook' );
     	if( $nifty_bu_cron_timestamp == false ){
	        wp_schedule_event( time(), 'every_minute', 'nifty_cron_hook' );  
    	}

    	if (!get_option("nifty_backups_api_secret_token")) {
			$token = $this->nifty_backups_api_key_create();
	        add_option("nifty_backups_api_secret_token", $token);
	    }


	}


	/**
	 * Create a secret API key for the REST API
	 * 
	 * @return 	string 		API Key
	 * @since  1.00
	 */
	private function nifty_backups_api_key_create() {
		$the_code = rand(0, 1000) . rand(0, 1000) . rand(0, 1000) . rand(0, 1000) . rand(0, 1000);
		$the_time = time();
		$token = md5($the_code . $the_time);
		return $token;
	}


	/**
	 * Adds a 'every minute' check to the WP CRON
	 * 
	 * @param  array 	$schedules 	Schedule array
	 * @return array            	Modified schedule array
	 * @since  1.00
	 */
	function nifty_backups_cron_add_minutely($schedules) {

		// Adds once weekly to the existing schedules.
	 	$schedules['every_minute'] = array(
	 		'interval' => 60,
	 		'display' => __( 'Every Minute' )
	 	);
	 	return $schedules;

	}



	function plugin_deactivate() {
	    wp_clear_scheduled_hook('nifty_backups_cron');
	}



	function handle_defaults() {
		$nifty_backup_options = get_option("nifty_backup_options");
		if (isset($params['nifty_db_rows'])) { $nifty_backup_options['nifty_db_rows'] = intval($this->max_allowed_rows_per_session); }
		if (isset($params['nifty_files'])) { $nifty_backup_options['nifty_files'] = intval($this->max_allowed_files_per_session); }

		update_option("nifty_backup_options",$nifty_backup_options);
	}


	public function action_callback() {
		global $wpdb;
	    $check = check_ajax_referer('nifty_backup', 'security');

	    if ($check == 1) {
	    	error_reporting(E_ERROR);


	    	if ($_POST['action'] == "nifty_backup_info") {
	    		$this->backup_info();
	    	}
	    	if( $_POST['action'] == 'nifty_backup' ) {
	    		$this->test_function();
    		}
	    	if( $_POST['action'] == 'nifty_backup_start' ) {
	    		$this->start_backup();
    		}
	    	if( $_POST['action'] == 'nifty_restore_external' ) {
				$nb_ext_file = $this->fetch_external($_POST['ext_file']);
				
				if ($nb_ext_file == "-1") {
					echo json_encode(array("err"=>"The file was fetched yet we could not copy it to the backups folder. Does it already exist?"));
					die();
				}
				if (strpos($nb_ext_file,"-db-") !== false) { $restore_type = "db"; }
				else if (strpos($nb_ext_file,"-files-") !== false) { $restore_type = "files"; }
				else {
					echo json_encode(array("err"=>"This doesnt look like a Nifty Backup file. Stopping process. Please contact support"));
					die();
				}


				echo json_encode(array("filen" => $nb_ext_file,"filetype" => $restore_type));
				die();

    		}

    		if ($_POST['action'] == "nifty_delete_file") {
    			if ($_POST['bid']) {
    			if (file_exists(nifty_bu_upload_dir.$_POST['bid'])) {
    					$filename = $_POST['bid'];
						$this->delete(nifty_bu_upload_dir.$filename);
						echo "1";
					} else {
						die("0");
					}
    			} else {
    				die("-1");
    			}
    		}

    		if ($_POST['action'] == "nifty_cloud_upload") {
				if ($_POST['bid']) {
	    			if (file_exists(nifty_bu_upload_dir.$_POST['bid'])) {
						$filename = $_POST['bid'];
						$data = array(
							'filelocation' => nifty_bu_upload_dir.$_POST['bid'],
							'filename' => $_POST['bid']
						);

						do_action("nifty_backups_send_to_cloud_hook",$data);


						echo "1";
						die();
					} else {
						die("0");
					}
				} else {
					die("File not found");
				}
	    	}

    		if ($_POST['action'] == "nifty_restore") {
    			if ($_POST['bid']) {

    				if (file_exists(nifty_bu_upload_dir.$_POST['bid'])) {
    					$filename = $_POST['bid'];
						$this->restore(nifty_bu_upload_dir.$filename);
					} else {
						die("0");
					}
    			} else {
    				die("-1");
    			}

    		}
    		if ($_POST['action'] == "nifty_cancel_backup") {
    			$this->nifty_bu_delete_option("nifty_backups_in_progress");
    			$this->nifty_bu_delete_option("nifty_backups_in_progress_files");
    			$this->nifty_bu_delete_option("nifty_backups_in_progress_sql");
				if (@file_exists($this->file_integrity)) { @unlink($this->file_integrity); }
				if (@file_exists($this->filename)) { @unlink($this->filename); }
    			die("1");
    		}

	    	else if( $_POST['action'] == 'view_change' ) {
	    		if (isset($_POST['menu_item'])) {
	    			if ($_POST['menu_item'] == "dashboard") { 
	    				include plugin_dir_path(__FILE__)."/templates/dashboard.php";
	    			}
	    			if ($_POST['menu_item'] == "general_settings") { 
	    				include plugin_dir_path(__FILE__)."/templates/general-settings.php";
	    			}
	    			if ($_POST['menu_item'] == "db_settings") { 
	    				include plugin_dir_path(__FILE__)."/templates/db_settings.php";
	    			}
	    			if ($_POST['menu_item'] == "file_settings") { 
	    				include plugin_dir_path(__FILE__)."/templates/file_settings.php";
	    			}
	    			if ($_POST['menu_item'] == "schedules") { 
	    				include plugin_dir_path(__FILE__)."/templates/schedules.php";
	    			}
	    			if ($_POST['menu_item'] == "support") { 
	    				include plugin_dir_path(__FILE__)."/templates/support.php";
	    			}
	    			if ($_POST['menu_item'] == "restoremenu") { 
	    				include plugin_dir_path(__FILE__)."/templates/restore.php";
	    			}
	    			if ($_POST['menu_item'] == "restore_file") { 
	    				include plugin_dir_path(__FILE__)."/templates/restore_file.php";
	    			}
	    			if ($_POST['menu_item'] == "restore_db") { 
	    				include plugin_dir_path(__FILE__)."/templates/restore_db.php";
	    			}
	    			if ($_POST['menu_item'] == "backupmenu") {
			    		$this->backup_info(true);
	    			}

	    			do_action("nifty_backup_action_view_change",$_POST);
	    			
	    			wp_die();
	    		}

			} else if ($_POST['action'] == 'nifty-save-settings') {
				$params = array();
				parse_str($_POST['input_data'], $params);
				do_action("nifty_backups_filter_save_settings",$params);
				wp_die();
			}

	    }
	    die();
	}


	
	function nifty_backups_filter_control_save_settings_general($params) {
		$nifty_backup_options = get_option("nifty_backup_options");

		if (isset($params['nifty_db_rows'])) {
			if (intval($params['nifty_db_rows']) > 5000) { $params['nifty_db_rows'] = 5000; }
			$nifty_backup_options['nifty_db_rows'] = intval($params['nifty_db_rows']);
		}
		if (isset($params['nifty_files'])) {
			if (intval($params['nifty_files']) > 3000) { $params['nifty_files'] = 3000; }
			$nifty_backup_options['nifty_files'] = intval($params['nifty_files']);

		}

		if (isset($params['db_settings'])) {
			if (isset($params['nifty_exclude_db_backup'])) {
				$nifty_backup_options['nifty_exclude_db_backup'] = $params['nifty_exclude_db_backup'];	
			} else {
				unset($nifty_backup_options['nifty_exclude_db_backup']);
			}
		}
		if (isset($params['file_settings'])) {
			if (isset($params['nifty_exclude_files_backup'])) {
				$nifty_backup_options['nifty_exclude_files_backup'] = $params['nifty_exclude_files_backup'];	
			} else {
				unset($nifty_backup_options['nifty_exclude_files_backup']);
			}

		}

		$nifty_backup_options = apply_filters("nifty_backups_filter_settings_paramter_intercept",$nifty_backup_options,$params);


		update_option("nifty_backup_options",$nifty_backup_options);
	}


	private function admin_scripts() {
		add_action('admin_print_scripts', array($this, 'load_admin_scripts'));
		add_action('admin_print_styles', array($this, 'load_admin_styles'));




	}

	function view_general_settings() {
		$nifty_backup_options = get_option("nifty_backup_options");
		if (isset($nifty_backup_options['nifty_db_rows'])) { $rows = $nifty_backup_options['nifty_db_rows']; } else { $rows = 2000; }
		if (isset($nifty_backup_options['nifty_files'])) { $files = $nifty_backup_options['nifty_files']; } else { $files = 1500; }
		echo "<h3>".__("General settings","nifty-backups")."</h3>";
		echo "<table class='wp-list-table widefat fixed '>";
		echo "<tr>";
		echo "	<td>";
		echo "		<label>".__("Table rows per backup iteration","nifty-backups")."</label> ";
		echo "	</td>";
		echo "	<td>";
		echo '		<input type="number" max="5000" name="nifty_db_rows" style="width:60px;" value="'.$rows.'" /><strong>'.__('rows','nifty-backups').'</strong> <small><em>'.__('Max: 5000 (The higher the number, the more resources are required.)','nifty-backups')."</em></small>";
		echo "	</td>";
		echo "	</tr>";
		echo "<tr>";
		echo "	<td>";
		echo "		<label>".__("Files per backup iteration","nifty-backups")."</label> ";
		echo "	</td>";
		echo "	<td>";
		echo '		<input type="number" max="3000" name="nifty_files" style="width:60px;" value="'.$files.'" /><strong>'.__('files','nifty-backups').'</strong> <small><em>'.__('Max: 3000 (The higher the number, the more resources are required.)','nifty-backups')."</em></small>";
		echo "	</td>";
		echo "	</tr>";
		echo "	</table>";
		echo "&nbsp;";
		
		do_action("nifty_backups_general_settings_output_hook",$nifty_backup_options);
		$this->nifty_backups_return_button("save-general-settings");

	}
	public function view_db_settings() {

		$nifty_backup_options = get_option("nifty_backup_options");
		echo "<input type='hidden' val='1' name='db_settings' />";


		echo "<table  class='wp-list-table widefat fixed'>";

		if (isset($nifty_backup_options['nifty_exclude_db_backup']) && $nifty_backup_options['nifty_exclude_db_backup'] == '1') {
			$excl_db = 'checked="checked"';
		} else {
			$excl_db = '';
		}
		echo "<tr>";
		echo '<td><input type="checkbox" name="nifty_exclude_db_backup" '.$excl_db.' value="1" />'.__('Do not backup the database','').'</td><td></td>';
		echo "</tr>";
		echo "</table>";
		echo "&nbsp;";

		do_action("nifty_backups_db_settings_output_hook",$nifty_backup_options);



		$this->nifty_backups_return_button("save-db");

	}

	function nifty_backups_hook_control_db_settings_output_hook($nifty_backup_options) {
		echo "<h4>".__("Exclude these tables:","nifty-backups")."</h4>";
		echo "<p><span class='update-nag'>".sprintf(
			__("Exclude tables when running a backup with the <a href='%s'>Pro Version</a> for only %s once off. Updates and support included forever!","nifty-backups"),'http://niftybackups.com/?utm_source=plugin&utm_medium=link&utm_campaign=table_exclude','$19.99').'</span></p>';

		$tables = $this->get_table_sizes(true);
		global $wpdb;
		$sql = 'SELECT table_name AS "Table", round(((data_length + index_length) / 1024 / 1024), 2) as size FROM information_schema.TABLES WHERE table_schema = "'.DB_NAME.'"';		
		$table_sizes = $wpdb->get_results($sql,ARRAY_A);

		foreach($table_sizes as $key => $val) {
			$table_size_array[$val['Table']] = $val['size'];
		}
		echo '<table  class="wp-list-table widefat fixed">';
		echo '<thead>';
		echo '<tr>';
		echo '<th align="left">'.__("Table name","nifty-backups").'</th>';
		echo '<th align="left">'.__("Estimated size","nifty-backups").'</th>';
		echo '<th align="left">'.__("Number of records","nifty-backups").'</th>';
		echo '</tr>';
		echo '</thead>';
		foreach ($tables['tables'] as $table => $val) {
		
			echo '<tr>';
			echo '<td><input disabled type="checkbox" value="'.$table.'" />'.$table.' </td>';
			echo '<td>'.$table_size_array[$table].' Mb</td>';
			echo '<td>'.$val.'</td>';
			echo '</tr>';
		}
		echo '</table>';





	}
	public function view_support_page() {
		echo "<h4>".__("Nifty Backups Support","nifty-backups")."</h4>";
	}	
	public function view_schedule_settings() {
		$nifty_backup_options = get_option("nifty_backup_options");
		echo "<p>".__("Select when you would like your backup to automatically run.","nifty-backups")."</p>";

		do_action("nifty_backups_schedule_settings_output_hook",$nifty_backup_options);
		$this->nifty_backups_return_button("save-schedule");

	}	

	function nifty_backups_hook_control_schedule_settings_output_hook($nifty_backup_options) {
		echo "<h4>".__("Scheduling settings:","nifty-backups")."</h4>";
		echo "<p>Schedule both file and database backups with the ".$this->return_pro_link('http://niftybackups.com/','scheduling',__('pro version.','nifty-backups'))."</p>";

	}
	function view_file_settings() {
		$nifty_backup_options = get_option("nifty_backup_options");


		echo "<input type='hidden' val='1' name='file_settings' />";
		echo "<table  class='wp-list-table widefat fixed'>";

		if (isset($nifty_backup_options['nifty_exclude_files_backup']) && $nifty_backup_options['nifty_exclude_files_backup'] == '1') {
			$excl_files = 'checked="checked"';
		} else {
			$excl_files = '';
		}


		echo "<tr>";
		echo '<td><input type="checkbox" name="nifty_exclude_files_backup" '.$excl_files.' value="1" />'.__('Do not backup files','').'</td><td></td>';
		echo "</tr>";
		echo "</table>";
		

		echo "&nbsp;";

		do_action("nifty_backups_file_settings_output_hook",$nifty_backup_options);



		$this->nifty_backups_return_button("save-files");

	}

	function nifty_backups_hook_control_file_settings_output_hook($nifty_backup_options) {


		echo "<p><span class='update-nag'>".sprintf(
			__("Customize advanced file backup settings with the<a href='%s'>Pro Version</a> for only %s once off. Updates and support included forever!","nifty-backups"),'http://niftybackups.com/?utm_source=plugin&utm_medium=link&utm_campaign=size_exclude','$19.99').'</span></p>';

		echo "<table  class='wp-list-table widefat fixed'>";
		echo "<tr>";
		echo "<td>";		
		echo "<label>".__("Exclude files over","nifty-backups")."</label> ";
		echo "</td>";
		echo "<td>";
		echo '<input type="text" name="nifty_size_exclude" disabled style="width:40px;  background-color:#eee;" value="" />'.__('Mb','nifty-backups').' '.__('(Leave blank to backup all file sizes)','nifty-backups');
		echo "</td>";
		echo "</tr>";
		echo "<tr>";
		echo "<td>";
		echo "<label>".__("Exclude these file extensions","nifty-backups")."</label>";
		echo "</td>";
		echo "<td>";
		echo '<textarea disabled readonly style="width:220px; height:90px; background-color:#eee;" >';
		echo "</textarea><br /><em>";
		echo __("Example:<br />.jpg<br />.zip<br />.log",'nifty-backups');
		echo "</em>";	
		echo "</td>";
		echo "</tr>";
		echo "</table>";

	}

	function return_pro_link($url,$type,$text) {
	 	$link = sprintf( __( '<a href="%s" title="%s" target="_BLANK">%s</a>', 'nifty-backups' ),
                $url."?utm_source=plugin&utm_medium=link&utm_campaign=".$type,
                $text,
                $text

        );
        return $link;
	}
	function load_admin_scripts() {

		if (isset($_GET['page']) && defined('NIFTY_DASHBOARD_PAGE') && $_GET['page'] == NIFTY_DASHBOARD_PAGE) {
	        wp_register_script('nifty-bu-admin-js', plugins_url(plugin_basename(dirname(__FILE__)))."/js/admin.js", true);
	        wp_enqueue_script( 'nifty-bu-admin-js' );
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_save_string', __("Saving...","nifty-backups"));
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_save_successful', __("Settings saved.","nifty-backups"));

			$html = "<h1>".__("Backup Started","nifty-backups")."</h1>";
			$html .= "<p>&nbsp;</p>";
			$html .= "<p>".__("The backup has begun. Navigating away or closing this window will not affect the backup in any way.","nifty-backups")."</p>";
			$html .= "";
			$html .= "";
			$html .= "";
			$html .= "";

			$text = "<h1>".__("Backup in progress","nifty-backups")."</h1><p>".__("There is a backup in progress. Information about the backup will appear here periodically.","nifty-backups")."</p>";
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_localize_backup_started_html', $html );
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_localize_restore_external', __("Your file is currently being fetched. Please be patient.", "nifty-backups") );
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_localize_restore_maintenance', __("Your site has been put into maintenance mode until the restore is complete", "nifty-backups") );
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_localize_backup_started_information', $text );


			do_action("nifty_backups_admin_js");


			$ajax_nonce = wp_create_nonce("nifty_backup");
			wp_localize_script( 'nifty-bu-admin-js', 'nifty_backup_nonce', $ajax_nonce);

			$ajax_url = admin_url('admin-ajax.php');
			wp_localize_script('nifty-bu-admin-js', 'nifty_backup_ajaxurl', $ajax_url);

    		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
    		if ($backups_in_progress) {
				$progress = $this->progress_update();
				

			
				if (isset($progress['db'])) {
					wp_localize_script( 'nifty-bu-admin-js', 'nifty_backup_perc', (string)$progress['db']);
				}

				if (isset($progress['files'])) {
					wp_localize_script( 'nifty-bu-admin-js', 'nifty_backup_perc', (string)$progress['files']);
				}

				



    		}

		}
	}
	function load_admin_styles() {
		if (isset($_GET['page']) && defined('NIFTY_DASHBOARD_PAGE') && $_GET['page'] == NIFTY_DASHBOARD_PAGE) {
	        wp_register_style('nifty-bu-admin-css', plugins_url(plugin_basename(dirname(__FILE__)))."/css/admin.css", true);
	        wp_enqueue_style( 'nifty-bu-admin-css' );
	        wp_register_style('nifty-bu-admin-menu-css', plugins_url(plugin_basename(dirname(__FILE__)))."/css/admin-menu.css", true);
	        wp_enqueue_style( 'nifty-bu-admin-menu-css' );
	        wp_register_style('font-awesome', "https://maxcdn.bootstrapcdn.com/font-awesome/4.6.1/css/font-awesome.min.css", true);
	        wp_enqueue_style( 'font-awesome' );

		}
	}

	function DS() {
		if(strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
			return '/';
		} else {
			return '/';
			
		}
	}

	public function backup_menu_items(){

		add_menu_page( __( 'Backups', 'nifty-backups' ), __( 'Backups', 'nifty-backups' ), 'manage_options', 'nifty-backups', array( $this, 'backup_dashboard' ), plugins_url("/images/niftybackups_18px_grey.png", __FILE__) );


	}


	function backup_in_progress() {

		/* have we gone through all the tables? */
		$backups_in_progress = $this->nifty_bu_get_option('nifty_backups_in_progress');
		if ($backups_in_progress) {
			return true;
		} else {
			return false;
		}			

	}
	/**
	 * Thank you Omry Yadan for your code supplied on http://stackoverflow.com/questions/2354633/retrieve-wordpress-root-directory-path
	 *
	 */
	function nifty_get_wp_config_path() {
	    $base = dirname(__FILE__);
	    $path = false;

	    if (@file_exists(dirname(dirname($base)).$this->DS()."wp-config.php"))
	    {
	        $path = dirname(dirname($base));
	    }
	    else
	    if (@file_exists(dirname(dirname(dirname($base))).$this->DS()."wp-config.php"))
	    {
	        $path = dirname(dirname(dirname($base)));
	    }
	    else
	    $path = false;

	    if ($path != false)
	    {
	        $path = str_replace("\\", $this->DS(), $path);
	        $path = str_replace("/", $this->DS(), $path);
	    }
	    return $path;
	}

	private function delete($filename) {
		@unlink($filename);
	}

	private function fetch_external($filename) {

		$ext_file = $filename;
		$parsed_url = parse_url($filename);
		$path = $parsed_url['path'];
		$ext_newfile_tmp = basename($path);



		
		$ext_newfile = nifty_bu_upload_dir.$ext_newfile_tmp;

		$checker = @copy($ext_file, $ext_newfile);
		if (!$checker) {
			return "-1";
		}
		$filename = $ext_newfile;
		return $ext_newfile_tmp;

	}


	function nb_set_html_mail_content_type() {
		return 'text/html';
	}


	/**
	 * [notify description]
	 * @param  string  			$subject     	The subject of the email notification
	 * @param  string  			$message     	The message to be emailed
	 * @param  string|array  	$headers     	Header array or blank string
	 * @param  boolean 			$attachments 	Attachment array
	 * @return boolean							True or false
	 */
	public static function notify($subject,$message,$headers = '',$attachments = false) {
		add_filter( 'wp_mail_content_type', array( $this, 'nb_set_html_mail_content_type' ) );
		$nifty_backup_options = get_option("nifty_backup_options");

		if (isset($nifty_backup_options['nifty_notification_email_array']) && $nifty_backup_options['nifty_notification_email_array'] != '') {
			$emails = $nifty_backup_options['nifty_notification_email_array'];
		} else {
			$emails = get_option("admin_email");
		}
		if ($emails) {
			$data = array(
				'message' =>$message['body'],
				'header' => $message['header'],
				'footer' => $message['footer']
			);
			$message = apply_filters("nifty_backup_email_wrapper",$data);
			wp_mail($emails,$subject,$message,$headers, $attachments);

		} else {
			return false;
		}
	}


	/**
	 * Restores the ZIP file that is sent to the function
	 *
	 * If the filename contains '-db-' it is considered a MYSQL backup
	 * If the filename contains '-files-' it is considered a FILE backup
	 *
	 * 
	 * @param  string $filename The file to be restored (MUST BE A NIFTY ZIP FILE)
	 * @return boolean
	 */
	private function restore($filename) {
		if (!class_exists("ZipArchive")) { return; }
		@set_time_limit(0);

		if (strpos($filename,"-db-") !== false) { $restore_type = "db"; }
		else if (strpos($filename,"-files-") !== false) { $restore_type = "files"; }
		else { die('no'); }

		$delete_external = false;

		

		$zip = new ZipArchive;



		$res = $zip->open($filename);
		if ($res === TRUE) {
			/* engage maintenance mode */
			$this->maintenance_mode_start();

			if ($restore_type == "db") {

				

				$check = $zip->extractTo($this->upload_dir.$this->DS()."nifty-backups".$this->DS());
				$this->restored_file_sql = $this->upload_dir.$this->DS()."nifty-backups".$this->DS().'dump.sql';
				$zip->close();



				$this->restore_sql();

				$subject = __("Nifty Backups - Database Restore Successful","nifty-backups");
				$body_header = __("Restore complete","nifty-backups");
				$body = __("The restoration of '$filename' has been successfully completed.","nifty-backups");
				$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
				$message_array = array(
					"body" => $body,
					"header" => $body_header,
					"footer" => $body_footer
				);
				$this->notify($subject,$message_array,'',false);
				
				
				
				$this->maintenance_mode_end();


				

			} else if ($restore_type == "files") {


				$zip_arr_list = array();

				$home_path = get_home_path();
				if (!$home_path) { $home_path = $this->nifty_get_wp_config_path; }
				$home_path = rtrim($home_path,$this->DS());
				if (!$home_path || $home_path == "") {
					die("problem finding home path");
				} else {



					                    
				    for($i = 0; $i < $zip->numFiles; $i++) {
		                
		                $stat = $zip->statIndex( $i ); 
		                
				    	$zip_arr_list[rtrim($stat['name'],$this->DS())] = 1;

				        $zip->extractTo(nifty_bu_upload_dir, array($zip->getNameIndex($i)));
				                        
				        // here you can run a custom function for the particular extracted file
				                        
				    }

				    foreach ($zip_arr_list as $key => $val) {
				    	
				    	$nick = new ZipArchive;
				    	$nickres = $nick->open(nifty_bu_upload_dir.$key);
				    	if ($res === TRUE) {
				    		$check = $nick->extractTo($home_path);	
				    	} else {
				    		$this->maintenance_mode_end();
				    		echo __("There was an error opening the ZIP package. Please contact support (Line number: ".__LINE__.")","nifty-backups");
				    	}
						
				    	
				    }
				                    
				    
				                    

					

				}
				$subject = __("Nifty Backups - File Restore Successful","nifty-backups");
				$body_header = __("File Restore complete","nifty-backups");
				$body = __("The restoration of '$filename' has been successfully completed.","nifty-backups");
				$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
				$message_array = array(
					"body" => $body,
					"header" => $body_header,
					"footer" => $body_footer
				);
				$this->notify($subject,$message_array,'',false);

				if ($delete_external) { $this->delete($filename); }
				echo "1";
				/* take us out of maintenance mode number one */
				$this->maintenance_mode_end();
			}

		} else {
		  $this->maintenance_mode_end();
		  echo __("There was an error unzipping the package. Please contact support (Line number: ".__LINE__.")","nifty-backups");
		}
		return;
	}


	function remove_comments(&$output) {
	   $lines = explode("\n", $output);
	   $output = "";

	   // try to keep mem. use down
	   $linecount = count($lines);

	   $in_comment = false;
	   for($i = 0; $i < $linecount; $i++)
	   {
	      if( preg_match("/^\/\*/", preg_quote($lines[$i])) )
	      {
	         $in_comment = true;
	      }

	      if( !$in_comment )
	      {
	         $output .= $lines[$i] . "\n";
	      }

	      if( preg_match("/\*\/$/", preg_quote($lines[$i])) )
	      {
	         $in_comment = false;
	      }
	   }

	   unset($lines);
	   return $output;
	}

	//
	// remove_remarks will strip the sql comment lines out of an uploaded sql file
	//
	function remove_remarks($sql) {
	   $lines = explode("\n", $sql);

	   // try to keep mem. use down
	   $sql = "";

	   $linecount = count($lines);
	   $output = "";

	   for ($i = 0; $i < $linecount; $i++) {
	      if (($i != ($linecount - 1)) || (strlen($lines[$i]) > 0)) {
	         if (isset($lines[$i][0]) && $lines[$i][0] != "#") {
	            $output .= $lines[$i] . "\n";
	         }
	         else {
	            $output .= "\n";
	         }
	         // Trading a bit of speed for lower mem. use here.
	         $lines[$i] = "";
	      }
	   }

	   return $output;

	}

	//
	// split_sql_file will split an uploaded sql file into single sql statements.
	// Note: expects trim() to have already been run on $sql.
	//
	function split_sql_file($sql, $delimiter)
	{
	   // Split up our string into "possible" SQL statements.
	   $tokens = explode($delimiter, $sql);

	   // try to save mem.
	   $sql = "";
	   $output = array();

	   // we don't actually care about the matches preg gives us.
	   $matches = array();

	   // this is faster than calling count($oktens) every time thru the loop.
	   $token_count = count($tokens);
	   for ($i = 0; $i < $token_count; $i++) {
	      // Don't wanna add an empty string as the last thing in the array.
	      if (($i != ($token_count - 1)) || (strlen($tokens[$i] > 0))) {
	         // This is the total number of single quotes in the token.
	         $total_quotes = preg_match_all("/'/", $tokens[$i], $matches);
	         // Counts single quotes that are preceded by an odd number of backslashes,
	         // which means they're escaped quotes.
	         $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);

	         $unescaped_quotes = $total_quotes - $escaped_quotes;

	         // If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
	         if (($unescaped_quotes % 2) == 0) {
	            // It's a complete sql statement.
	            $output[] = $tokens[$i];
	            // save memory.
	            $tokens[$i] = "";
	         }
	         else
	         {
	            // incomplete sql statement. keep adding tokens until we have a complete one.
	            // $temp will hold what we have so far.
	            $temp = $tokens[$i] . $delimiter;
	            // save memory..
	            $tokens[$i] = "";

	            // Do we have a complete statement yet?
	            $complete_stmt = false;

	            for ($j = $i + 1; (!$complete_stmt && ($j < $token_count)); $j++) {
	               // This is the total number of single quotes in the token.
	               $total_quotes = preg_match_all("/'/", $tokens[$j], $matches);
	               // Counts single quotes that are preceded by an odd number of backslashes,
	               // which means they're escaped quotes.
	               $escaped_quotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);

	               $unescaped_quotes = $total_quotes - $escaped_quotes;

	               if (($unescaped_quotes % 2) == 1) {
	                  // odd number of unescaped quotes. In combination with the previous incomplete
	                  // statement(s), we now have a complete statement. (2 odds always make an even)
	                  $output[] = $temp . $tokens[$j];

	                  // save memory.
	                  $tokens[$j] = "";
	                  $temp = "";

	                  // exit the loop.
	                  $complete_stmt = true;
	                  // make sure the outer loop continues at the right point.
	                  $i = $j;
	               }
	               else {
	                  // even number of unescaped quotes. We still don't have a complete statement.
	                  // (1 odd and 1 even always make an odd)
	                  $temp .= $tokens[$j] . $delimiter;
	                  // save memory.
	                  $tokens[$j] = "";
	               }

	            } // for..
	         } // else
	      }
	   }

	   $new_array = array();
	   foreach ($output as $key => $val) {
	   		if ($val != '') {
	   			$new_array[] = trim($val);
	   		}
	   		// free mem
	   		$output[$key] = '';
	   }

	   return $new_array;
	}

	private function restore_sql() {
		if (isset($this->restored_file_sql)) {
			$use_wp_db = true;
			$link = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
			if (!$link) {
				$use_wp_db = true;
				global $wpdb;
			} else {
				mysql_select_db(DB_NAME, $link);
				$use_wp_db = false;
			}

			$use_wp_db = false;




			
			set_time_limit(0);

			$max_chunks_per_loop = 500;

			/* check if we are continuing from a previous ping.. */
			$previous_chunk = $this->nifty_bu_get_option("restore_current_chunk");
			if ($previous_chunk) {
				/* continuation */

			} else {
				$previous_chunk = 0;
			}

			$dbms_schema = $this->restored_file_sql;

			$handle = fopen($dbms_schema, "r");
			$sql_query = array();
			$sql_string = '';
			if ($handle) {
			    while (($buffer = fgets($handle)) !== false) {
					
					$buffer = trim($buffer);
					
					if ($buffer != '') {
						if  ($buffer[0] != '#') {
							// this is not a comment, we can add this to the main string check
							$last_char = substr($buffer, -1);
							$sql_string .= $buffer;
							if ($last_char == ";") {
								// this is the end of the SQL query so add it to the sql query array
								$sql_query[] = $sql_string;
								// reset the sql string
								$sql_string = '';
							}

						}
					}
			        


			    }

			    fclose($handle);
	    
			} else {
			    // error opening the file.
			    return false;
			} 


			$sql_chunks = array();

			$total_chunks = count($sql_query);


			foreach ($sql_query as $sql) {
				$sql = trim($sql);
				if (strlen($sql) > 1) { 
					if ($use_wp_db) { 
						$wpdb->show_errors();
						$wpdb->query($sql);
						if($wpdb->last_error !== '') {
										
							$subject = __("Nifty Backups - Error Importing Chunk","nifty-backups");
							$body_header = __("Chunk Import Error","nifty-backups");
							$body = sprintf(__("Problem importing a chunk of SQL while restoring your backup","The following SQL could not be imported. We suggest that you manually import it via PHPMyAdmin or equivalent.<br /><br />%s<br /><br/>Error from WP: %s"),$sql,$wpdb->print_error());
							$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
							$message_array = array(
								"body" => $body,
								"header" => $body_header,
								"footer" => $body_footer
							);
							$this->notify($subject,$message_array,'',false);
							
		    				
		    			}
					}
					else {
						$checker = mysql_query($sql,$link);
						if (mysql_error($link)) {
							$subject = __("Nifty Backups - Error Importing Chunk","nifty-backups");
							$body_header = __("Chunk Import Error","nifty-backups");
							$body = sprintf(__("Problem importing a chunk of SQL while restoring your backup","The following SQL could not be imported. We suggest that you manually import it via PHPMyAdmin or equivalent.<br /><br />%s<br /><br/>Error from WP: %s"),$sql,$mysql_error($link));
							$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
							$message_array = array(
								"body" => $body,
								"header" => $body_header,
								"footer" => $body_footer
							);
							$this->notify($subject,$message_array,'',false);
							
						}
					}

	    		}


			}

			$this->nifty_bu_delete_option("restore_current_chunk",__LINE__);
			echo "1";
			return;

/*


			if ($previous_chunk >= $total_chunks) {
				// we are complete! 
				$this->nifty_bu_delete_option("restore_current_chunk",__LINE__);
				echo "1";
				return;
			}

			$new_max_chunks_limit = $previous_chunk + $max_chunks_per_loop;
			
			
			while ($previous_chunk < $new_max_chunks_limit) {
				if ($previous_chunk >= $total_chunks) {
					// we are complete! 
					$this->nifty_bu_delete_option("restore_current_chunk",__LINE__);
					echo "1";
					return;
				}	
				
				//echo "processing $previous_chunk\n";
				$sql = trim($sql_query[$previous_chunk]);
				if (strlen($sql) > 1) { 
					if ($use_wp_db) { 
						$wpdb->show_errors();
						$wpdb->query($sql);
						if($wpdb->last_error !== '') {
							$admin_email = get_option( 'admin_email' );
		    				wp_mail($admin_email,"Problem importing a chunk of SQL while restoring your backup","The following SQL could not be imported. We suggest that you manually import it via PHPMyAdmin or equivalent. \n\n\r\r ".$sql." \n\n\r\rError from WP: ".$wpdb->print_error());
		    			}
					}
					else {
						$checker = mysql_query($sql,$link);
						if (mysql_error($link)) {
							var_dump(mysql_error($link));
							exit();
							$admin_email = get_option( 'admin_email' );
		    				wp_mail($admin_email,"Problem importing a chunk of SQL while restoring your backup","The following SQL could not be imported. We suggest that you manually import it via PHPMyAdmin or equivalent. \n\n\r\r ".$sql." \n\n\r\rError from WP: ".mysql_error($link));
						}
					}

	    		}

				//echo $sql_query[$previous_chunk];
				//echo "<br /><br />";
			
				$previous_chunk++;
				$this->maintenance_mode_start(); // reset the time in the maintenance file
				
			}

			$this->nifty_bu_update_option("restore_current_chunk",$previous_chunk);
			//var_dump($this->nifty_bu_get_option("restore_current_chunk"));
			return;

	*/








		} else {
			_e("There was an error locating the unzipped SQL file. Please contact support.","nifty-backups");
		}
	}

	public function backup_dashboard() {

        if(!get_option('nifty-bu-first-time')){
            update_option('nifty-bu-first-time', true);
            include('templates/welcome.php');
        } else {


			if (isset($_GET['action']) && $_GET['action'] == "nifty_restore" && isset($_GET['id'])) {
					$t = nifty_bu_upload_dir.$_GET['id'];
					echo $t;
					$this->restore($t);
			}


			?>

			<div id='nifty_backup_wrapper_header'>
				<h1><?php _e("Nifty Backups","nifty-backups"); ?></h1>
			</div>
			<div id='nifty_backup_wrapper'>
				<div id='nifty_backup_menu'>
					<div id='niftymenu'>
						<ul>
							<?php echo apply_filters("nifty_backup_filter_main_menu",""); ?>
						</ul>
					</div>
				</div>
				<div id='nifty_dashboard'>
					<div id='nifty_dashboard_inner'>
						<?php 

	    				//$this->backup_files();

						include plugin_dir_path(__FILE__)."/templates/dashboard.php";
						?>
					</div>
				</div>
			</div>
			<div id='nifty_backup_wrapper_footer'>
				<?php do_action("nifty_backup_action_footer",$_POST); ?>
			</div>
		


		<?php
		}
	}
	function nifty_backup_menu_control_dashboard($text) {
	?>
	   <li class='active'><a class='nifty_menu_item' menuitem="dashboard" href='#'><i class="fa fa-dashboard fa-lg"></i> &nbsp; <?php _e("Dashboard","nifty-backups"); ?></a></li>
	   <li class='active'><a class='nifty-backup-button-menu' href='#'><i class="fa fa-database fa-lg"></i> &nbsp; <?php _e("Backup","nifty-backups"); ?></a></li>
	   <li class='active'><a class='nifty_menu_item' menuitem="restoremenu" href='#'><i class="fa fa-cloud-upload fa-lg"></i> &nbsp; <?php _e("Restore","nifty-backups"); ?></a></li>
	<?php
	}
	function nifty_backup_menu_control_settings($text) {
	?>
	   <li class='has-sub'><a href='#'><i class='fa fa-wrench fa-lg'></i> &nbsp; <?php _e("Settings","nifty-backups"); ?></a>
	      <ul>
	        <li class=''><a class='nifty_menu_item' menuitem="general_settings" href='#'> &nbsp; <i class='fa fa-wrench'></i> &nbsp; <?php _e("General","nifty-backups"); ?></a></li>
	        <li class=''><a class='nifty_menu_item' menuitem="db_settings" href='#'> &nbsp; <i class='fa fa-database'></i> &nbsp; <?php _e("Database","nifty-backups"); ?></a></li>
	        <li class=''><a class='nifty_menu_item' menuitem="file_settings" href='#'> &nbsp; <i class='fa fa-file-o'></i> &nbsp; <?php _e("Files","nifty-backups"); ?></a></li>
			<?php echo apply_filters("nifty_backup_filter_main_menu_settings",""); ?>
	      </ul>
	   </li>
	<?php
	}
	function nifty_backup_menu_control_schedules($text) {
	?>
	   <li><a href='#' class='nifty_menu_item' menuitem="schedules"><i class='fa fa-clock-o fa-lg'></i> &nbsp; <?php _e("Schedules","nifty-backups"); ?></a></li>
	<?php
	}
	function nifty_backup_menu_control_support($text) {
	?>
	   <li><a href='#' class='nifty_menu_item' menuitem="support"><i class='fa fa-support fa-lg'></i> &nbsp; <?php _e("Support","nifty-backups"); ?></a></li>
	<?php
	}

	function get_available_backups($simple = false) {
		if (!$this->backup_directory) {
			return _e("There was an issue when trying to create the default backup directory in your wp-content/uploads folder. Please contact support to get this fixed.","nifty-backups");
		}


		//$files = get_option("nifty_backup_files");
		$upload_dir = wp_upload_dir();
		$upload_url = $upload_dir['baseurl']."/nifty-backups/";
    	$dir = $upload_dir['basedir'].$this->DS()."nifty-backups".$this->DS();
		$files = scandir($dir);
		

		global $ttdir;
		$ttdir = $dir;
		usort($files, function($a, $b){
			global $ttdir;
	        return filectime($ttdir.$a) < filectime($ttdir.$b);
	    });
		


		?>


		<section class="nifty-container">

			<table class="order-table">
				<thead>
					<tr>
						<th><?php _e("Filename","nifty-backups"); ?></th>
						<th><?php _e("Date","nifty-backups"); ?></th>
						<th><?php _e("Size","nifty-backups"); ?></th>
						<th><?php _e("Integrity","nifty-backups"); ?></th>
						<th><?php _e("Actions","nifty-backups"); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ($files) {
						$cnt = count($files);
						$cnt--;
						foreach ($files as $file) { 
						
						if ($file == "." || $file == ".." || pathinfo($dir.$file, PATHINFO_EXTENSION) != "zip") { } else {

									if (strpos($file,"-db-") !== false) { $restore_type = "restore_db"; }
									else if (strpos($file,"-files-") !== false) { $restore_type = "restore_file"; }


									$bulog = false;
									$bu_summary = false;
									$int_text = false;
									$int = "unknown";
									$bfile = "";
									$tfile = "";

									if ($restore_type == "restore_file") {
										$site_url = get_option("siteurl");
										$parsed_url = parse_url($site_url);
										$tmp_file = str_replace(".zip","",$file);
										$tmp_file = str_replace($parsed_url['host']."-","",$tmp_file);
										$pos = strpos($tmp_file,"-files-");
										$link = substr($tmp_file,-$pos,strlen($tmp_file));

										if ($link) {
											$bu_summary_file = $this->upload_dir . $this->DS() . 'nifty-backups'.$this->DS().'nifty-bu-option-nifty_Fin_progress-report-'.$link.'.json';
											$bu_file = $this->upload_dir . $this->DS() . 'nifty-backups'.$this->DS().'backup-log-'.$link.'.txt';
											if (file_exists($bu_file)) {
												
												$bulog = true;
												$bfile = nifty_bu_upload_url.basename($bu_file);
												
											}

											if (file_exists($bu_summary_file)) {
												$busummary = true;
												$cont = file_get_contents($bu_summary_file);
												$arr = json_decode($cont);
												$files_total = $arr->file_cnt;
												$file_skipped = $arr->files_skipped_qty;


												if ($file_skipped > 0) { 
													$tfile = nifty_bu_upload_url.basename($bu_summary_file);
													$integrity = round(((($files_total - $file_skipped) / $files_total)*100),1);
													$int_text = "<br /><a href='".$tfile."' target='_BLANK' style='color:red; font-size:0.9em;'><i>view summary</i></a>";
												} else {
													$integrity = 100;
												}
												
											}
										}
									} else {
										$site_url = get_option("siteurl");
										$parsed_url = parse_url($site_url);
										$tmp_file = str_replace(".zip","",$file);
										$tmp_file = str_replace($parsed_url['host']."-","",$tmp_file);
										$pos = strpos($tmp_file,"-db-");
										$link = substr($tmp_file,-$pos,strlen($tmp_file));
										if ($link) {
											$bu_summary_file = $this->upload_dir . $this->DS() . 'nifty-backups'.$this->DS().'nifty-bu-option-nifty_backups_in_progress-report-'.$link.'.json';
											
											$bu_file = $this->upload_dir . $this->DS() . 'nifty-backups'.$this->DS().'backup-log-'.$link.'.txt';
											if (file_exists($bu_file)) {
												
												$bulog = true;
												$bfile = nifty_bu_upload_url.basename($bu_file);
												
											}

											if (file_exists($bu_summary_file)) {
											
												$busummary = true;
												$cont = file_get_contents($bu_summary_file);
												$arr = json_decode($cont);
												if (isset($arr->db_integrity)) {
													$integrity = $arr->db_integrity;
												} else {
													$integrity = "-";
												}
											}

										}
									}

									
								

						 ?>


						
						<tr id='tr_<?php echo $file; ?>' <?php if ($cnt == count($files)) echo 'style="background-color:rgba(91, 157, 217, 0.66);"'; ?>>
							<td><?php echo $file; ?></td>
							<td><?php echo $this->timeAgo(date("Y-m-d H:i:s",filectime($dir.$file))); ?></td>
							<td><?php echo $this->format_size(filesize($dir.$file)); ?></td>
							<?php if (isset($busummary) && $busummary) { ?>
							<td>
								<?php echo $integrity."%"; ?>
								<?php if ($int_text !== false) { ?>
									<?php echo $int_text; ?>
								<?php } ?>

							</td>
							<?php } else { ?>
							<td><?php echo "unknown"; ?></td>
							<?php } ?>
							<td>
								<?php if ($simple) { ?>
									<button id="restore" class='nifty-button nifty_menu_item nifty-button-restore' menuitem='<?php echo $restore_type; ?>' extraitem='<?php echo $file ?>' title="<?php _e("Restore this backup","nifty-backups"); ?>"><i class='fa fa-upload'></i> <?php _e("Restore","nifty-backups"); ?></button>
								<?php } else { ?>
									<?php if ($bulog) { ?>
									<a href="<?php echo $bfile; ?>" target='_BLANK' class="nifty-button"  title="<?php _e("View backup log","nifty-backups"); ?>"><i class='fa fa-exclamation-triangle'></i></a>
									<?php } ?>
									<a href="<?php echo $upload_url.$file; ?>" target="_BLANK" class='nifty-button' id="nifty-button-download" title="<?php _e("Download","nifty-backups"); ?>"><i class='fa fa-cloud-download'></i></a>
									<?php do_action("nifty_backups_hook_dash_list_buttons",$file); ?>								
									<button id="restore" class='nifty-button nifty_menu_item nifty-button-restore' menuitem='<?php echo $restore_type; ?>' extraitem='<?php echo $file ?>' title="<?php _e("Restore this backup","nifty-backups"); ?>"><i class='fa fa-upload'></i></button>
									<button id="delete" class='nifty-button nifty-button-delete' bid='<?php echo $file ?>' title="<?php _e("Delete this backup","nifty-backups"); ?>"><i class='fa fa-remove'></i></button>
								<?php } ?>

							</td>
						</tr>

					<?php $cnt--; } } } else { ?>
					<tr>
						<td colspan='4'><?php _e("No backups found","nifty-backups"); ?></td>
					</tr>

					<?php }?>
				</tbody>
			</table>

		</section>
		<?php




	}
	function format_size($size) {
      $sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
      if ($size == 0) { return('n/a'); } else {
      return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . $sizes[$i]); }
	}


	function timeAgo($timestamp){
	    $datetime1=new DateTime("now");
	    $datetime2=date_create($timestamp);
	    $diff=date_diff($datetime1, $datetime2);
	    $timemsg='';
	    if($diff->y > 0){
	        $timemsg = $diff->y .' year'. ($diff->y > 1?"s":'');

	    }
	    else if($diff->m > 0){
	     $timemsg = $diff->m . ' month'. ($diff->m > 1?"s":'');
	    }
	    else if($diff->d > 0){
	     $timemsg = $diff->d .' day'. ($diff->d > 1?"s":'');
	    }
	    else if($diff->h > 0){
	     $timemsg = $diff->h .' hour'.($diff->h > 1 ? "s":'');
	    }
	    else if($diff->i > 0){
	     $timemsg = $diff->i .' minute'. ($diff->i > 1?"s":'');
	    }
	    else if($diff->s > 0){
	     $timemsg = $diff->s .' second'. ($diff->s > 1?"s":'');
	    } else {
	    	return "Just now";
	    }

		$timemsg = $timemsg.' ago';
		return $timemsg;
	}

	private function backup_info($return = false) {
		/* first time running a backup */



		$file_data = $this->get_file_list();

		$skip_db = apply_filters("nifty_backup_filter_skip_db",0);
		if ($skip_db) {
			$table_data = false;
			$table_sizes = false;
		} else {
			$table_data = $this->get_table_list(true);
			$table_sizes = $this->get_table_sizes();			
		}




		$backups_info = array(
			'tables' => $table_data,
			'table_sizes' => $table_sizes,
			'files' => $file_data['files'],
			'file_cnt' => $file_data['cnt']

		);
		$table_cnt = 0;
		$row_cnt = 0;
		if ($table_data) { 
			foreach ($table_sizes['tables'] as $key => $val) {
				$table_cnt++;
				$row_cnt = $row_cnt + $val;
			}
		}
		if (!isset($file_data['total_fsize'])) { $file_data['total_fsize'] = 0; }
		$html = "<div style='display:block; overflow:auto;'>";
		$html .= "<h1>Pre-backup information</h1>";
		$html .= "<p>&nbsp;</p>";
		$html .= "<p>The following will be backed up:</p>";
		$html .= "<ul style='padding:inherit !important; list-style:inherit !important; margin-left:20px;'>";
		$html .= "<li>".number_format($table_cnt)." tables</li>";
		$html .= "<li>".number_format($row_cnt)." table rows</li>";
		$html .= "<li>".number_format($file_data['cnt'])." files (".$this->format_size($file_data['total_fsize']).")</li>";
		$html .= "</ul>";
		$html .= "<hr />";

		if (!$table_data && $file_data['cnt'] == 0) {
			$html .= "<strong>You have disabled both file and database backups - there is nothing to backup.</strong>";
			$html .= "<hr />";
			$continue = false;
		} else {

			if (isset($file_data['large_files'])) {
				$html .= "<p>The following large files have been detected, these could result in excessive delays between backup iterations:</p>";
				$html .= "<ul style='padding:inherit !important; list-style:inherit !important; margin-left:20px;'>";
				
				foreach ($file_data['large_files'] as $file => $size) {
					$html .= "<li>(".$this->format_size($size).") $file</li>";
				}
				$html .= "</ul>";
			}

			$html .= "<p><strong>Estimated completion time:</strong> ".$this->estimated_completion_time($row_cnt,$file_data['cnt'])."</p>";
			$html .= "";
			$html .= "";
			$continue = true;
			$html .= '<button id="nifty-button" class="nifty-backup-button nifty-bg-blue nifty-white" confirm-backup="'.$continue.'" style="display: block;"><i class="fa fa-database"></i> <span class="nifty-backup-button-span">Backup Now</span></button>';
		}
		$array = array(
			"data" => $html,
			"files" => $file_data['cnt'],
			"action" => "",
			"cont" => $continue
			);
		$html .= "</div>";

		if ($return) { return json_encode($array); } else { echo json_encode($array); }
		

	}
	public function estimated_completion_time($rows,$files) {

		/*
		average times (will vary depending on server type)

			these averages include the average delays between cron runs

			150 000 rows per minute or 9 000 000 rows per hour
			3000 files per minute or 180 000 files per hour

			The more files the slower the process...

			Generally file counts under 50 000 go very quickly



		 */
		$file_readrate_high_end = 2000;
		$file_readrate_low_end = 3500;

		if ($files < 50000) {
			$file_duration = round($files / $file_readrate_low_end);
		} else {
			$file_duration = round($files / $file_readrate_high_end);
		}
		$row_duration = round(($rows / 150000),1); /* calculates how many minutes it will take */
		$total_duration = $file_duration + $row_duration;
		if ($total_duration > 60) {
			return "".round(($total_duration / 60),1). "hours";
		} else {
			if (round($total_duration) == 1) { return "1 minute"; }
			else if ($total_duration < 1) {
				return "Less than a minute";
			} else {
				return "".round($total_duration) . " minutes";
			}
		}
			
		
		

	}

	public function start_backup_check() {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		if (!$backups_in_progress || $backups_in_progress == '') {
			$check = $this->start_backup();	
		} else {
			var_dump("backup already in progress");
		}

	}

	public function start_backup() {

		/* first time running a backup */
		$this->logthis(__LINE__." ### NEW BACKUP ###","simple",false);
		$this->logthis(__LINE__." SETTING UP BACKUP VARIABLES","verbose",false);

		$subject = __("Nifty Backups - Backup Started","nifty-backups");
		$body_header = __("Backup Started","nifty-backups");
		$body = sprintf(__("A backup has been started on %s"),get_option("siteurl"));
		$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
		$message_array = array(
			"body" => $body,
			"header" => $body_header,
			"footer" => $body_footer
		);
		$this->notify($subject,$message_array,'',false);



		$hash = time();
		$date = date("Y-m-d");

		$site_url = get_option("siteurl");
		$parsed_url = parse_url($site_url);

		$site_name = str_replace(".","",$parsed_url['host']);
		$this->zip_filename_db = $this->backup_directory . $this->DS() . $site_name. "-".$date . '-db-' . $hash . '.zip';
		$this->nice_zip_filename_db = $site_name. "-".$date . '-db-' . $hash . '.zip';
		$this->zip_filename_files = $this->backup_directory . $this->DS() . $site_name. "-".$date . '-files-' . $hash . '.zip';
		$this->nice_zip_filename_files = $site_name. "-".$date . '-files-' . $hash . '.zip';

		$file_data = $this->get_file_list();
		$table_size_data = $this->get_table_sizes();

		$row_cnt = 0;
		foreach ($table_size_data['tables'] as $key => $val) {
			
			$row_cnt = $row_cnt + $val;
		}


		/* generate a link for all 3 JSON files so that we can compare */
		$time_link = $hash;

		$skip_db = apply_filters("nifty_backup_filter_skip_db",0);
		$skip_files = apply_filters("nifty_backup_filter_skip_files",0);
		if ($skip_files == 1) { $skip_files = true; }

		$backups_in_progress = array(
			'in_progress' => 0,
			'link' => $time_link,
			'generated' => date("Y-m-d H:i:s"),
			'file_db' => $this->zip_filename_db,
			'file_db_nice' => $this->nice_zip_filename_db,
			'file_files' => $this->zip_filename_files,
			'file_files_nice' => $this->nice_zip_filename_files,
			'current_step' => 'to_start',
			'rows_read' => 0,
			'files_read' => 0,
			'started' => '',
			'skip_files' => $skip_files,
			'skip_db' => $skip_db,
			'status' => array(
				'db' => intval($skip_db),
				'files' => intval($skip_files)
			),
			'file_cnt' => $file_data['cnt'],
			'row_cnt' => $row_cnt,
			'table_count' => count($table_size_data['tables'])


		);

		$files_in_progress = array(
			'generated' => date("Y-m-d H:i:s"),
			'link' => $time_link,
			'files' => $file_data['files']

		);
		$sql_in_progress = array(
			'generated' => date("Y-m-d H:i:s"),
			'link' => $time_link,
			'tables' => $this->get_table_list(true),
			'table_sizes' => $table_size_data

		);

		/* we have to separate the files, sql and normal backup option as in some cases
		when there are many files or tables, the option.json file gets so large
		that we cannot read it and update it quick enough before the next backup iteration begins.

		splitting it allows us to have a small "backups_in_progress" main json file that we can quickly
		read and write to and identify if we are still updating the file or sql JSON file.
		 */
		$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);
		$this->nifty_bu_update_option("nifty_backups_in_progress_sql",$sql_in_progress);
		$this->nifty_bu_update_option("nifty_backups_in_progress_files",$files_in_progress);

		echo json_encode(array('db'=>'0'));	
	

	}

	public function integrity_check_db($backups_in_progress) {
		if (!$backups_in_progress) {
			/* no backup options? return true to stop the continuous loop */
			return true;
		}
		$sql_file = $this->filename;
		if (file_exists($sql_file) && is_readable($sql_file)) {
			$handle = fopen($sql_file, "r");
			$insert_check = 0;
			$drop_check = 0;
			$create_check = 0;
			$db_int_check = array();
			$current_table_name = "";
			if ($handle) {
			    while (($line = fgets($handle)) !== false) {
			        if ($line[0] == "(") {
			        	/* we are working with an insert */
			        	$insert_check++;
			        	if ($current_table_name != "") {
			        		if (isset($db_int_check[$current_table_name])) {
				        		$db_int_check[$current_table_name] = $db_int_check[$current_table_name] + 1;
			        		} else {
				        		$db_int_check[$current_table_name] = 1;

			        		}
			        	}
			        }
			        if (substr($line,0,4) == "DROP") {
			        	/* we are working with a drop table query */
			        	$drop_check++;
			        }
			        if (substr($line,0,6) == "CREATE") {
			        	/* we are working with a create table query */
			        	$create_check++;
			        	$pregcheck = preg_match("/`(.*)\`/",$line,$matches);
			        	if ($matches && isset($matches[1])) {
			        		$current_table_name = $matches[1];
			        	}	
			        }
			    }
			    fclose($handle);

			    if ($drop_check != $create_check) {
			    	/* 	mismatch in drop and create, something is missing */

			    }
				$backups_in_progress['db_integrity_rows_created'] = $insert_check;
				$backups_in_progress['db_integrity_tables_created'] = $create_check;
				$backups_in_progress['db_integrity_drops_created'] = $drop_check;

		    	/* 	we have a mismatch in the rows captured in the SQL file and the rows that were expected
		    	 	Important note: there could very well be a difference in the amount of rows catpured as plugins, 
		    	 	themes and wordpress itself may alter options and rows while the backup is in process.
		    		Therefore, we should strive for a 95% integrity score
	    		*/
			    if ($insert_check != $backups_in_progress['row_cnt']) {
			    	$ratio = round((($backups_in_progress['row_cnt'] - $insert_check) / $backups_in_progress['row_cnt']),2);
			    	$backups_in_progress['db_integrity'] = 100;
			    } else {
			    	/* perfect match..! */
			    	$backups_in_progress['db_integrity'] = 100;
			    }
					$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);
			    


			} else {
			    // error opening the file.
			    $this->logthis(__LINE__." Could not open SQL file for integrity check, either it doesnt exist or is not readable","verbose",true);
				/* return true to avoid a continuous loop of integrity checks */
				return true;
			} 
		} else {
			$this->logthis(__LINE__." Could not open SQL file for integrity check, either it doesnt exist or is not readable","verbose",true);
			/* return true to avoid a continuous loop of integrity checks */
			return true;
		}
	}


	public function integrity_check($file_list,$file) {
		if (!class_exists("ZipArchive")) { return; }

		$source = ABSPATH;
		$source = str_replace('\\', $this->DS(), realpath($source));
		$source = str_replace('/', $this->DS(), $source);

		$zip_arr_list = array();
		$file_arr_list = array();

		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");

		foreach ($backups_in_progress['zip_chunks'] as $key => $val) {
			if (@file_exists($key)) {
				$this->logthis(__LINE__. " Comparing files in ".$key."","simple",true);
				$za = new ZipArchive(); 
				$za->open($key); 

				for( $i = 0; $i < $za->numFiles; $i++ ){ 
				    $stat = $za->statIndex( $i ); 

				    /* BUILD A LIST OF FILES WITHIN THE CURRENT ZIP FILE */
				    $zip_arr_list[rtrim($stat['name'],$this->DS())] = 1;
				}
				$za->close();
			}
		}

		if (@file_exists($file_list)) { 
			$data = file_get_contents($file_list);
			if ($data) { 
    			$datanew = json_decode($data,true);
    		}
        }


        foreach ($datanew as $key => $val) {
        	/* BUILD A LIST OF FILES IN THE INTEGRITY LIST FILE */
        	$file_arr_list[rtrim($val,$this->DS())] = 1;
        }




        $actual_files_vs_zip = array_diff_key($file_arr_list,$zip_arr_list);

		$backups_in_progress_files = $this->nifty_bu_get_option("nifty_backups_in_progress_files");
		
		/* get amount of files read so that we can subtract how many have not actually been added */
		$files_read = $backups_in_progress['files_read'];
		$this->logthis(__LINE__. " Initial files read: ".$files_read,"simple",true);

        /* update the JSON list of files and set the ones we are missing to -1 so that they can be attempted again. */
        $files_missed = 0;
        $files_skipped = 0;
		foreach( $actual_files_vs_zip as $key => $val) {
			if (isset($backups_in_progress_files['files'][$source.$this->DS().$key])) {
				$current_attempt = $backups_in_progress_files['files'][$source.$this->DS().$key];
				$new_attempt = -4;
				$this->logthis(__LINE__. " Integrity check [attempt: $current_attempt] failed on ".$key,"verbose",false);


				if ($current_attempt == 0) {
					$this->logthis(__LINE__. " [1 failed attempts - retrying] ".$key,"verbose",false);
					$new_attempt = -4;
				}
				if ($current_attempt == -4) {
					$this->logthis(__LINE__. " [2 failed attempts - retrying] ".$key,"verbose",false);
					$new_attempt = -3;
				}
				if ($current_attempt == -3) {
					$this->logthis(__LINE__. " [3 failed attempts - retrying] ".$key,"verbose",false);
					$new_attempt = -2;
				}
				if ($current_attempt == -2) { 
					$this->logthis(__LINE__. " [4 failed attempts - skipping]".$key,"verbose",true);
					$new_attempt = "failed";
				}

				if ($new_attempt < 0) {
					$backups_in_progress_files['files'][$source.$this->DS().$key] = $new_attempt;
					$files_missed++;
				} else {
					$this->logthis(__LINE__. " Skipped ".$key."","verbose",false);
					$files_skipped++;
					$files_missed++;
					$backups_in_progress['files_skipped'][] = $key;

				}
			} else {
				/* key doesnt exist, do nothing */
			}
			
		}
		$this->logthis(__LINE__. " Files missed: ".$files_missed,"simple",false);
		
		$backups_in_progress['files_read'] = $files_read - $files_missed;
		$backups_in_progress['files_skipped_qty'] = $files_skipped;

		$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);

		$this->logthis(__LINE__. " Reassessed files read: ".$backups_in_progress['files_read'],"simple",false);
		$this->nifty_bu_update_option("nifty_backups_in_progress_files",$backups_in_progress_files);


		if ($files_missed > 0) {
			return true;
		} else {
			return false;
		}


		
		



	}



	public function test_function($second_run = false) {
		/* start the process */
		//$this->nifty_bu_delete_option("nifty_backups_in_progress");
		//$this->set_in_progress(0);
		
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		if (!$backups_in_progress) {
			if ($second_run) {
				sleep(2);
				test_function(true);
			}
		}
		if ($backups_in_progress) {

			if (isset($backups_in_progress['in_progress'])) {

				/* FIRST CHECK PROGRESS ON THE DATABASE */
				if ($backups_in_progress['in_progress'] == 1) {
					$this->logthis(__LINE__." Tried to run the backup process but we are already processing a backup. Stopped.","verbose",false);

					/* we are busy with a backup, do not start */
					return $this->progress_update(true);

					
				} else {
					/* no backup in progress, we can continue */

					$db_progress = $backups_in_progress['status']['db'];
					$files_progress = $backups_in_progress['status']['files'];

					

					if ($db_progress == 0) {
						/* we need to continue with the DB backup" */
						/* have we gone through all the tables though? */
						$backups_in_progress_sql = $this->nifty_bu_get_option("nifty_backups_in_progress_sql");
						$checker = true;
						foreach ($backups_in_progress_sql['tables'] as $key => $val) {
							if ($val === 0) {
								$checker = true;
							} else {
								$checker = false;
								break;
							}
						}
						if ($checker) {
							/* first time, let's overwrite the file.. */
							
							$this->logthis(__LINE__." ### Opening the file with w+ ###","verbose",false);
							$this->destination = $this->open($this->filename,"w+");
						} else {
							$this->logthis(__LINE__." ### Opening the file with a+ ###","verbose",false);
							$this->destination = $this->open($this->filename,"a+");
							
						}				
						$this->sql_file_header();
						
						
						
						echo json_encode($this->backup_mysql(null));
					
						$this->close($this->destination);
					}
					else if ($db_progress == 1 && $files_progress == 0) {
						/*we need to continue with the FILE backup") */
						echo json_encode($this->backup_files(null));

					}


				}
			} else {
				/* in_progress not set */
				/* send back 100% as this will stop the recurring JS loop */
				echo json_encode(array("db"=>100,"files"=>100,"quick"=>"already_done_1"));
				die();

			}
		} else {
			/* there is nothing going on */
			/* send back 100% as this will stop the recurring JS loop */
			echo json_encode(array("db"=>100,"files"=>100,"quick"=>"already_done2"));
			die();
		}
		die();


	}


	public static function get_table_list($filter = false) {
		global $wpdb;
		$nifty_backup_options = get_option("nifty_backup_options");
		$table_names = $wpdb->get_results('SHOW TABLES',ARRAY_A);
		

		$table_data = array();
		foreach( $table_names as $tn){
			foreach ($tn as $key => $val) {
	            $include_tbl = true;
	            if ($filter) { $include_tbl = apply_filters("nifty_bu_filter_include_tbl",$include_tbl,$val,$nifty_backup_options); }
	            if ($include_tbl) {
	            	$length = $wpdb->get_var("SELECT COUNT(*) AS `total_count` FROM $val");
					$table_data[$val] = 0;	

				}
			}
			
		}
		return $table_data;
	}
	public static function get_table_sizes($force = false) {
		global $wpdb;
		$nifty_backup_options = get_option("nifty_backup_options");
		$table_names = $wpdb->get_results('SHOW TABLES',ARRAY_A);
		$table_data = array();
		$total_count = 0;
		foreach( $table_names as $tn){
			foreach ($tn as $key => $val) {
	            $include_tbl = true;
	            $include_tbl = apply_filters("nifty_bu_filter_include_tbl",$include_tbl,$val,$nifty_backup_options);
	            if ($include_tbl) {
					$records = $wpdb->get_results( "SELECT count(*) as `total_items` FROM `$val`", ARRAY_A );
					$total_count = $total_count + intval($records[0]['total_items']);
					$table_data['tables'][$val] = intval($records[0]['total_items']);
				} else {
					if ($force) {
						/* make sure we get all tables and their sizes for the settings page */
						$records = $wpdb->get_results( "SELECT count(*) as `total_items` FROM `$val`", ARRAY_A );
						$total_count = $total_count + intval($records[0]['total_items']);	
						$table_data['tables'][$val] = intval($records[0]['total_items']);	
					}
				}
			}
			
		}
		$table_data['total_count'] = $total_count;
		return $table_data;

		$perc_array = array();
		foreach ($table_data as $key => $val) {
			$table_name = $key;
			$table_perc = round(floatval($val / $total_count),5);
			$perc_array[$key] = $table_perc;


		}

			
		return $table_data;
	}

	private function close($destination) {
		fclose($destination);
	}
	private function open($file = '',$mode = 'a+') {
		$destination = fopen($file, $mode);
		return $destination;
	}
	/* creates a compressed zip file */
	private function zip($files = array(),$destination = '',$overwrite = false,$path_to_ignore = '') {
		if (!class_exists("ZipArchive")) { return false; }

		
		//if the zip file already exists and overwrite is false, return false
		//vars
		$valid_files = array();
		//if files were passed in...
		if(is_array($files)) {
			//cycle through each file
			foreach($files as $key => $val) {

				$this->logthis(__LINE__." Trying to zip ".$key,"verbose",false);

				//var_dump("here");
				//var_dump($key);
				//make sure the file exists AND IS READABLE
				if(file_exists($key) && is_readable($key)) {
					if ($val < -1) { 
						$valid_files[$key] = $val;
					} else {
						$valid_files[$key]['path'] = $val['path'];
						$valid_files[$key]['fname'] = $val['fname'];
						$valid_files[$key]['nname'] = $val['nname'];
						$valid_files[$key]['type'] = $val['type'];
					}

				} else {
					$this->logthis(__LINE__." File either doesnt exist or is not readable - ".$key, "simple", false);
				}
			}
		}
		//var_dump("==========x==============");
		//var_dump($valid_files);
		//var_dump("=========================");

		$source = ABSPATH;
		$source = str_replace('\\', $this->DS(), realpath($source));
		$source = str_replace('/', $this->DS(), $source);

		if ($path_to_ignore != '') {
			$source = $path_to_ignore;
		}

		$this->logthis(__LINE__." source: ".$source,"verbose",true);


		if(count($valid_files)) {
			$zip = new ZipArchive();
			$zip_check = $zip->open($destination,ZipArchive::CREATE);

			if($zip_check) {
				
				$this->logthis(__LINE__." Number of files in ZIP file at start: ".$zip->numFiles,"verbose",false);
				foreach($valid_files as $key => $val) {



		            if (is_dir($key) === true) {
		            	$fname = str_replace($source . $this->DS(), '', $key . $this->DS());
		                $zip->addEmptyDir(str_replace($source . $this->DS(), '', $key . $this->DS()));

		            }
		            else if (is_file($key) === true) {
		            	$fname = str_replace($source . $this->DS(), '', $key . $this->DS());
		            	if ($val['nname'] == 'dump.sql') {
		            		 if ($zip->addFile($key, $val['nname'])) { } else {
		            		 	/* error adding file */
		            			$this->logthis(__LINE__. " Error adding ".$val['nname'],"simple",true);
		            		 }
		            		 	
		            	} else {
		            		
		            		if ($val < -1) {
		            			/* we use this method when the file has failed the integrity check */
		            			$this->logthis(__LINE__. " [Attempt $val] Adding from STRING for ".$key,"verbose",false);
		            			$content = file_get_contents($key);

								if ($zip->addFromString(str_replace($source . $this->DS(), '', $key), $content)) { } else {
			            			/* error adding file */
			            			$this->logthis(__LINE__. " Error adding ".$source." - " .$key,"simple",true);
			            		}
			            	} else {
			            		if ($zip->addFile($key, str_replace($source . $this->DS(), '', $key))) { } else {
			            			/* error adding file */
			            			$this->logthis(__LINE__. " Error adding ".$source." - " .$key,"simple",true);
			            		}
			            	} 
		            	}

		            }




			
				}
			} else {
				$this->logthis(__LINE__." Could not open or create the ZIP file ($destination) for writing","simple",false);
				return false;
			
			}
			//debug
			//echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
			
			//close the zip -- done!
			$this->logthis(__LINE__." Number of files in ZIP file at end: ".$zip->numFiles,"simple",false);

			$checkme = $zip->close();
			$this->logthis(__LINE__." ZIP CLOSE - ".($checkme ? "true" : "false"),"simple",false);
			
			//check to make sure the file exists
			if (file_exists($destination)) { return true; } else { return false; }
		}
		else
		{
			return false;
		}
	}

	private function nifty_bu_update_option($option_name,$data,$retries = 1) {
		if ($retries > 3) {
			$this->logthis(__LINE__. " COULD NOT Update option ($option) after 3 tries. Took ". sprintf("%.4f", ($end-$debug_start))." seconds","verbose",false);
			return false;
		}
		$debug_start = (float) array_sum(explode(' ',microtime()));
		$file = $this->upload_dir . $this->DS() . 'nifty-bu-option-'.$option_name.'.json';
		
		if ($dest = @fopen($file, "w+")) {

			if (fwrite($dest, json_encode($data, JSON_PRETTY_PRINT))) {
	        	$end = (float) array_sum(explode(' ',microtime()));
	    		$this->logthis(__LINE__. " Finished updating option. Took ". sprintf("%.4f", ($end-$debug_start))." seconds","verbose",false);
				return true;

			} else {
		        $end = (float) array_sum(explode(' ',microtime()));
		    	$this->logthis(__LINE__. " Finished updating option WITH ERROR. Took ". sprintf("%.4f", ($end-$debug_start))." seconds","verbose",false);
				return false;
			}
		} else {
			sleep(1);
			$new_retries = $retries + 1;
			nifty_bu_update_option($option_name,$data,$new_retries);
		}
	}
	private function nifty_bu_delete_option($option_name,$line = "") {
		$file = $this->upload_dir . $this->DS() . 'nifty-bu-option-'.$option_name.'.json';
		$dest = fopen($file, "w+");
		if (fwrite($dest, "")) {
			return true;
		} else {
			return false;
		}
	}
	private function nifty_bu_get_option($option_name) {
		$debug_start = (float) array_sum(explode(' ',microtime()));

		$file = $this->upload_dir . $this->DS() . 'nifty-bu-option-'.$option_name.'.json';
		if (@file_exists($file)) { 
			try {
				$data = file_get_contents($file);
				if ($data) { 
        			$datanew = json_decode($data,true);
			        $end = (float) array_sum(explode(' ',microtime()));
        			$this->logthis(__LINE__. " Finished GETTING option. Took ". sprintf("%.4f", ($end-$debug_start))." seconds","verbose",false);
					return $datanew;
				} else { 
					return ""; 
				}
			}
			catch (Exception $e) {
				/* it failed.. sleep for a second and try again */
				$this->logthis(__LINE__. " Get OPTION FAILED","verbose",true);
				sleep(1);
			    nifty_bu_get_option($option_name);
			}
			
		} else {
			//$this->logthis(__LINE__. " FILE DOESNT EXIST ".$file);
			return false; 
		}
	}


	private function logthis($message,$type = "simple",$force) {
		$continue = false;
		if ($type == "verbose" && NIFTY_BU_VERBOSE_DEBUG) { $continue = true; }
		if ($type != "verbose" && NIFTY_BU_DEBUG) { $continue = true;}

		if ($continue || $force) {
			$file = $this->upload_dir . $this->DS() . 'backup-log.txt';
			$dest = fopen($file, "a+");
			fwrite($dest, date("Y-m-d H:i:s").": ".$message."\r\n");
			return true;
		}

	}

	function get_file_list() {


		/* clear integrity checker file list */
		$tmp_darr = array();
		$integrity_check_dest = fopen($this->file_integrity, "w+");

		$cnt = 0;
		$total_fsize = 0;
		$file_data = array();
		$source = ABSPATH;
		$source = str_replace('\\', $this->DS(), realpath($source));
		$source = str_replace('/', $this->DS(), $source);
		$nifty_backup_options = get_option("nifty_backup_options");
		$skip_files = apply_filters("nifty_backup_filter_skip_files",0);
		if ($skip_files) {
			return array(
				"files" => null,
				"cnt" => null,
				"source" => null
			);
		}
		if (is_dir($source) === true) {
	        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

	        foreach ($files as $file) {
	        	if (is_readable($file)) {
		        	$nname = "";	
		        	$fname = "";
		            $file = str_replace('\\', $this->DS(), $file);
		            $file = str_replace('/', $this->DS(), $file);
		            $orig_file = $file;
		            // Ignore "." and ".." folders
		            if( in_array(substr($file, strrpos($file, $this->DS())+1), array('.', '..')) )
		                continue;

		            //$file = realpath($file);

		            if (is_dir($file) === true) {
		            	$type = 1;
		            	$fname = str_replace($source . $this->DS(), '', $file . $this->DS());
		            	//echo "DIRECTORY: ".str_replace($source . '/', '', $file . '/')."<br />";
		                //$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
		            }
		            else if (is_file($file) === true) {
		            	$type = 2;
		            	$nname = basename($file);
		            	$fname = str_replace($source . $this->DS(), '', $file . $this->DS());
		            	//echo "FILE: ".str_replace($source . '/', '', $file)."<br />";
		                //$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));


		            }

		            $include_file = true;

		            $include_file = apply_filters("nifty_bu_filter_include_file",$include_file,$orig_file,$nifty_backup_options);
		            if ($include_file) {
						$cnt++;
						$file_data['files'][$file] = -1;
						$fsize = @filesize($file);
						$total_fsize = $total_fsize + $fsize;
						if ($fsize > (1024 * 1 * 1000)) {
							$file_data['large_files'][$file] = $fsize;	
						}
						array_push($tmp_darr,$fname);
						
						

					}
				} else {
					
				}

	        }
	    }
		@fwrite($integrity_check_dest, json_encode($tmp_darr, JSON_PRETTY_PRINT));
	    fclose($integrity_check_dest);
	    if (isset($file_data['large_files'])) { arsort($file_data['large_files']); }
	    $file_data['total_fsize'] = $total_fsize;

		$file_data['cnt'] = $cnt;
		$file_data['source'] = $source;
		return $file_data;

	}

	function backup_files() {
		$this->logthis(__LINE__." Starting backup of FILES","simple",false);
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");

		if (!$backups_in_progress) {
			$this->logthis(__LINE__." Error #2 - Please contact support","simple",true);
			return false;
		}
		if ($backups_in_progress['skip_files'] == 1) {
			/**
			 *PUT HERE
			 * 
			 */
		}


		$this->set_in_progress(1);

		$nifty_backup_options = get_option("nifty_backup_options");
		if (isset($nifty_backup_options['nifty_files'])) { } else { $nifty_backup_options['nifty_files'] = 500; }
		$this->max_allowed_files_per_session = $nifty_backup_options['nifty_files'];
		if ($this->max_allowed_files_per_session < 1) { $this->max_allowed_files_per_session = 500; }


		$max_loops = $this->max_allowed_files_per_session;

		$tmp_cnt = 0;
		$file_names = array();
		$current_position = intval($backups_in_progress['files_read']);


		/* set defaults for each table, until altered later */
		$first_run = false;
		$run = true;

		if ($backups_in_progress['files_read'] == 0) {
			$first_run = true;
			$run = true;
		}

		$backups_in_progress_files = $this->nifty_bu_get_option("nifty_backups_in_progress_files");

		foreach( $backups_in_progress_files['files'] as $key => $val) {

			$file_name = $key;
			$checked = $val;

			// removed
			//chhecked = $val['check'];
			//$fname = $val['fname'];
			//$nname = $val['nname'];
			//$type = $val['type'];
				



			/* have we done the max amount of files yet? */
			if ($tmp_cnt >= $max_loops) {
				$run = false;
			}
			

			if ($run) {

				if ($checked === -1) {
					//var_dump("we are backing this file up");
					/* we need to back this up */
					//var_dump("adding ".$fname." to list");
					

					//$file_names[$file_name]['path'] = $file_name;
					//$file_names[$file_name]['fname'] = $fname;
					//$file_names[$file_name]['nname'] = $nname;
					//$file_names[$file_name]['type'] = $type;

					$file_names[$file_name] = 1;

					/* increase the counter */
					$tmp_cnt++;


				} else if ($checked < -1) {
					/* will only be less than -1 when integrity checks fail on the file */
					/* send the negative number to the zip function so that it knows to use addFromString */
					$file_names[$file_name] = $checked;		

					/* increase the counter */
					$tmp_cnt++;
				}
				else {
					//var_dump("we've already backed this up");
					/* this has already been done */

				}

			} else {
				//var_dump("breaking, reached ".$tmp_cnt." files");
				$this->logthis(__LINE__. " Stopping file backup. Ran through $tmp_cnt files.","verbose",false);
				break;
			}





		}

		/* which ZIP chunk does this go to */
		$next_zip_chunk = $this->get_next_zip_chunk();

		/* send the file array to be zipped */
		$this->zip($file_names,$next_zip_chunk,false);
		$this->update_current_file_list($file_names);
		$this->update_current_position_files($tmp_cnt);
		$this->set_in_progress(0);
		$this->logthis(__LINE__." Zipping files complete. Pausing backup","verbose",false);

		return $this->progress_update();



		
	}

	function get_next_zip_chunk() {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		if (isset($backups_in_progress['next_zip_chunk'])) {
			$number = $backups_in_progress['next_zip_chunk'] + 1;
			$backups_in_progress['next_zip_chunk'] = $number;
			$new_chunk_name = $backups_in_progress['file_files']."-".$number;
			$backups_in_progress['zip_chunks'][$new_chunk_name] = -1;
			$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);
			return $new_chunk_name;
		} else {
			$backups_in_progress['next_zip_chunk'] = 1;
			$new_chunk_name = $backups_in_progress['file_files']."-1";
			$backups_in_progress['zip_chunks'][$new_chunk_name] = -1;
			$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);
			return $new_chunk_name;
		}


	}
	function update_current_position_files($count) {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		$backups_in_progress['files_read'] = $backups_in_progress['files_read'] + intval($count);
		$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);
	}
	function update_current_file_list($array) {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress_files");
		foreach ($array as $file => $val) {
			if (isset($backups_in_progress['files'][$file])) {
				if ($val < -1) {
					$backups_in_progress['files'][$file] = $val;
				} else {
					$backups_in_progress['files'][$file] = 0;
				}
			}
		}
		$this->nifty_bu_update_option("nifty_backups_in_progress_files",$backups_in_progress);

	}

	public function backup_mysql(){
		global $wpdb;	



		$this->logthis(__LINE__. " Starting backup of MySQL database","simple",false);
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		if (!$backups_in_progress) {
			$this->logthis(__LINE__ . " Error #1 - Please contact support","simple",true);
			return false;
		}

		$this->set_in_progress(1);

		$nifty_backup_options = get_option("nifty_backup_options");

		if (isset($nifty_backup_options['nifty_db_rows'])) { } else { $nifty_backup_options['nifty_db_rows'] = 500; }

		$this->max_allowed_rows_per_session = $nifty_backup_options['nifty_db_rows'];
		if ($this->max_allowed_rows_per_session < 1) { $this->max_allowed_rows_per_session = 500; }
		$this->logthis(__LINE__." Maximum rows to scan per session: ".$this->max_allowed_rows_per_session,"verbose",false);





		$table_data = array();
		$insert = "";
		$rows_read = 0;


		$backups_in_progress_sql = $this->nifty_bu_get_option("nifty_backups_in_progress_sql");

		foreach( $backups_in_progress_sql['tables'] as $key => $val) {

			$table_name = $key;

			/* set defaults for each table, until altered later */
			$first_run = false;
			$run = true;

			/* how far are we with this table? */
			$current_position = $backups_in_progress_sql['tables'][$table_name];



			if ($current_position === 0) {
				/* first time starting with this table */
				$this->logthis(__LINE__. " First run on table [$key]","verbose",false);
				$first_run = true;
			} else if ($current_position === -1) {
				/* we have already done this table */
				$run = false;
			} else {
				/* continuing with this table */
				$this->logthis(__LINE__. " Continuation on table [$key]","verbose",false);
			}



			if ($run) {



				$this->logthis(__LINE__." first run: ".$first_run,"verbose",false);
				if ($first_run) {


					$this->store("\n"."DROP TABLE IF EXISTS " . $this->backquote($table_name) . ";");

					$create_table_string = $wpdb->get_results('SHOW CREATE TABLE '.$table_name, ARRAY_N);
					if( isset( $create_table_string[0] ) && isset( $create_table_string[0][1] ) ){
						$this->store("\n".$create_table_string[0][1].";\n\n");
					} else {
						/* need failover for this */
					}
				}
				/**
				 * Gets column names for each table
				 */
				
				$column_data = $wpdb->get_col( "SHOW COLUMNS FROM `$table_name`");

				/* first check if table is empty */
				if (!$this->is_tbl_empty($table_name)) {
					
					$first_run = true;

					if ($first_run) {
						/*
						$insert_header = "INSERT INTO ".$this->backquote($table_name)." (\n";
						$column_cnt = count($column_data);
						$column_tmp_cnt = 0;
						foreach ($column_data as $column) {
							$column_tmp_cnt++;
							if ($column_tmp_cnt < $column_cnt) { 
								$insert_header .= $this->backquote($column).",\n";
							} else {
								$insert_header .= $this->backquote($column)."\n";
							}

						}
						$insert_header .= ") VALUES ";
						$this->store($insert_header); */
					} else {
						/* we are continuing, the values need to be added */
						
						
					}
					$value_data = array();
					$are_we_done = false;
					while ($are_we_done === false) {
						$value_data = $this->get_tbl_values($table_name,$current_position,2000);

						if (($value_data['rows_returned'] + $current_position) >= $backups_in_progress_sql['table_sizes']['tables'][$table_name]) {
							/* we have read all the rows for this table, lets mark it as complete */
							$this->update_current_position($table_name,-1);
							$are_we_done = true;
						} else {
							$this->update_current_position($table_name,($value_data['rows_returned'] + $current_position));
						}

						$this->store($value_data);
						/* are we done ? */
						//if ($value_data['rows_returned'] < $this->max_allowed_rows_per_session) {
							/* less than what was requested was returned, we are done. */
						//	$this->update_current_position($table_name,-1);
						//	$are_we_done = true;
						//} else {
						//	$this->update_current_position($table_name,($value_data['rows_returned'] + $current_position));
						//}


					
						/* set new current position */
						$current_position = $value_data['rows_returned'] + $current_position;
						$this->update_rows_read($value_data['rows_returned']);
						
						$rows_read = $rows_read + intval($value_data['rows_returned']);
						if ($rows_read >= $this->max_allowed_rows_per_session) {
							/* we have done enough this session, now pause and wait for the next session to start */
							$this->logthis(__LINE__. " Ran through $rows_read rows, pausing for now","verbose",false);
							$this->set_in_progress(0);
							$are_we_done = true;
							return $this->progress_update();
						}


					}
					if ($rows_read >= $this->max_allowed_rows_per_session) {
						$this->logthis(__LINE__. " We've done $rows_read rows (max allowed: ".$this->max_allowed_rows_per_session,"verbose",false);
						$this->logthis(__LINE__. " In progress set to: 0","verbose",false);

						$this->set_in_progress(0);
						return $this->progress_update();						
					}

				} else {
					$this->logthis(__LINE__." Table [$key] is empty, nothing to record","verbose",false);

					$this->update_current_position($table_name,-1);
				}
			}

		}
		$this->logthis(__LINE__." In progress set to: 0. Ran through $rows_read rows","verbose",false);
		$this->set_in_progress(0);
		return $this->progress_update();
		
		

		
	}

	public function progress_update($quick = false) {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");

		if ($backups_in_progress['status']['db'] == 0) {

			$total_perc_db = 0;
			$rows_read = $backups_in_progress['rows_read'];

			$backups_in_progress_sql = $this->nifty_bu_get_option("nifty_backups_in_progress_sql");

			$total_rows = $backups_in_progress_sql['table_sizes']['total_count'];

			$total_perc_db = $rows_read / $total_rows;
			$total_perc_db = intval($total_perc_db * 100);


			/* check if all done with the DB, then show 100% anyway */
			$in_progress_check_db = true;
			foreach ($backups_in_progress_sql['tables'] as $key => $val) {
				if ($val !== -1) { $in_progress_check_db = true; } else { $in_progress_check_db = false; }
			}
			if (!$in_progress_check_db) { 

				/* check integrity of SQL file */
				$table_sizes = $this->get_table_sizes();
				$row_cnt = 0;
				foreach ($table_sizes['tables'] as $key => $val) {
					$row_cnt = $row_cnt + $val;
				}
				$this->integrity_check_db($backups_in_progress);

				$total_perc_db = 100;
				$this->backup_complete('db',__LINE__);
				$this->logthis(__LINE__. " DB backup complete!","simple",false);
			}
		} else {

			$total_perc_db = 100;
		}


		if ($backups_in_progress['status']['files'] == 0) {
			/* check file status */
			$total_perc_files = 0;
			$files_read = $backups_in_progress['files_read'];
			$total_files = $backups_in_progress['file_cnt'];
			$total_perc_files = $files_read / $total_files;
			$total_perc_files = intval($total_perc_files * 100);

			if ($backups_in_progress['files_read'] >= $backups_in_progress['file_cnt']) {


				/* check integrity! */
				if ($this->integrity_check($this->file_integrity,$backups_in_progress['file_files'])) {
					return array(
						'db' => $total_perc_db,
						'files' => 99,
						'quick' => 'integrity_failed',
						'files_read' => $backups_in_progress['files_read'],
						'files_total' => $backups_in_progress['file_cnt']

					);
				}



				$this->backup_complete('files',__LINE__);
				$this->logthis(__LINE__. " File backup complete!","simple",false);
			}


		} else {
			$total_perc_files = 100;
			$this->backup_complete('files',__LINE__);

		}




		return array(
			'db' => $total_perc_db,
			'files' => $total_perc_files,
			'quick' => $quick
		);

	}
	private function backup_complete($type,$line) {
		$still_busy = false;

		if ($type == 'db') {
			$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
			$file_names = array();
			$file_names[$this->filename]['path'] = $this->filename;
			$file_names[$this->filename]['fname'] = $this->filename;
			$file_names[$this->filename]['nname'] = "dump.sql";
			$file_names[$this->filename]['type'] = 2;

			if ($this->zip($file_names,$backups_in_progress['file_db'],true)) {

				$new_array = array(
					"filename" => $backups_in_progress['file_db'],
					"url" => $this->backup_url.$this->DS().$backups_in_progress['file_db_nice'],
					"nicefilename" => $backups_in_progress['file_db_nice'],
					"date" => date("Y-m-d H:i:s"),
					"timestamp" => time(),
					"size" => $this->format_size(filesize($backups_in_progress['file_db'])),
					"user" => get_current_user_id(),
				);

				$files = get_option("nifty_backup_files");
				if ($files) {
					$files[] = $new_array;

				} else {
					$files = array();
					$files[] = $new_array;
				}

				$backups_in_progress['status']['db'] = 1;
				$backups_in_progress['in_progress'] = 0;
				$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);

				update_option("nifty_backup_files",$files);


				$data_array = array(
					"type" => 'file_backup_db',
					"file_location" => $backups_in_progress['file_db'],
					'file_name' => $backups_in_progress['file_db_nice']
				);
				$data_array = apply_filters("nifty_backups_file_backup_complete",$data_array);

				$subject = __("Nifty Backups - Database Backup Complete","nifty-backups");
				$body_header = __("Database Backup Complete","nifty-backups");
				$body = sprintf(__("A database backup has ended successfully on %s"),get_option("siteurl"));
				$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
				$message_array = array(
					"body" => $body,
					"header" => $body_header,
					"footer" => $body_footer
				);
				
				$attachments = false;

				$nifty_backup_options = get_option("nifty_backup_options");
				if (isset($nifty_backup_options['offsite_selection']) && isset($nifty_backup_options['offsite_selection']['email']) && $nifty_backup_options['offsite_selection']['email'] == 1) {
					if (@filesize($backups_in_progress['file_db']) < (20 * 1024 * 1024)) {
						/* its small enough to email */
						$attachments = array( $backups_in_progress['file_db'] );
					}
				}

				if ($backups_in_progress['skip_files']) {
					/* we have opted to skip files, lets close this backup session now */			
					$this->nifty_bu_store_log("nifty_backups_in_progress",$backups_in_progress['link']);
					$this->nifty_bu_delete_option("nifty_backups_in_progress",__LINE__);	
					$this->nifty_bu_delete_option("nifty_backups_in_progress_sql",__LINE__);	
					$this->nifty_bu_delete_option("nifty_backups_in_progress_files",__LINE__);	

					$this->notify($subject,$message_array,'',$attachments);
					exit();
				}


				$this->notify($subject,$message_array,'',$attachments);
			} else {

				$subject = __("Nifty Backups - Error","nifty-backups");
				$body_header = __("Zip Error","nifty-backups");
				$body = sprintf(__("There was a problem ziping your backup file. Please contact <a href='%s'>Support</a>"),"http://niftybackups.com/contact-us/?utm_source=plugin&utm_medium=link&utm_campaign=ziperror");
				$body .= "<br /><br />".$backups_in_progress['file_db']."<br/><br/>";
				$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
				$message_array = array(
					"body" => $body,
					"header" => $body_header,
					"footer" => $body_footer
				);
				$this->notify($subject,$message_array,'',false);
				$this->nifty_bu_delete_option("nifty_backups_in_progress",__LINE__);	
				$this->nifty_bu_delete_option("nifty_backups_in_progress_sql",__LINE__);	
				$this->nifty_bu_delete_option("nifty_backups_in_progress_files",__LINE__);	

				exit();

			}


		} else if ($type == 'files') {
			//var_dump("It's done..!");
			



			$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");

			if ($backups_in_progress['skip_files'] && $backups_in_progress['status']['db'] == 0) {
				/** we are not done with the DB so dont do anything with this yet. Only when DB status = 1 do we continue */
				return;
			}
			if ($backups_in_progress['skip_files']) {
				/* DB is 1, and skip_files is true, so therefore lets end the whole show now */
				$this->nifty_bu_store_log("nifty_backups_in_progress",$backups_in_progress['link']);
				$this->nifty_bu_delete_option("nifty_backups_in_progress",__LINE__);	
				$this->nifty_bu_delete_option("nifty_backups_in_progress_sql",__LINE__);	
				$this->nifty_bu_delete_option("nifty_backups_in_progress_files",__LINE__);	
				return;
			}



			$backups_in_progress['status']['files'] = 1;

			/* we are now done with both db and files, we can end this */

			/* zip all the zip chunks together */
			$chunk_arr = array();
			if (isset($backups_in_progress['zip_chunks'])) {
				foreach ($backups_in_progress['zip_chunks'] as $key => $val) {
					$chunk_arr[$key] = 1;
					$this->logthis(__LINE__. " Zipping ZIP CHUNK ".$key."","verbose",true);
									
				}
				$this->logthis(__LINE__. " MAIN ZIP: ".$backups_in_progress['file_files']."","verbose",true);

				/* we define the source dir so that it can be removed from the filename when added to the zip. we do not need
				the directory structure for these files in the main zip file */
				$source = nifty_bu_upload_dir;
				$source = str_replace("\\",$this->DS(),$source);
				$source = str_replace("/",$this->DS(),$source);
				$source = rtrim($source,$this->DS());

				$this->zip($chunk_arr,$backups_in_progress['file_files'],true,$source);

				/* remove zip chunks */
				foreach ($backups_in_progress['zip_chunks'] as $key => $val) {
					if (@unlink($key)) {} else {
						sleep(1);
						@unlink($key);
					}
				}

			}
			




			$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);

			$this->nifty_bu_store_log("nifty_backups_in_progress",$backups_in_progress['link']);

			$this->nifty_bu_delete_option("nifty_backups_in_progress",__LINE__);	
			$this->nifty_bu_delete_option("nifty_backups_in_progress_sql",__LINE__);	
			$this->nifty_bu_delete_option("nifty_backups_in_progress_files",__LINE__);	
			$data_array = array(
				"type" => 'file_backup_zip',
				"file_location" => $backups_in_progress['file_files'],
				'file_name' => $backups_in_progress['file_files_nice']
			);
			$data_array = apply_filters("nifty_backups_file_backup_complete",$data_array);





			$subject = __("Nifty Backups - File Backup Complete","nifty-backups");
			$body_header = __("File Backup Complete","nifty-backups");
			$body = sprintf(__("A file backup has ended successfully on %s"),get_option("siteurl"));
			$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
			$message_array = array(
				"body" => $body,
				"header" => $body_header,
				"footer" => $body_footer
			);
			$attachments = false;

			$nifty_backup_options = get_option("nifty_backup_options");
			if (isset($nifty_backup_options['offsite_selection']) && isset($nifty_backup_options['offsite_selection']['email']) && $nifty_backup_options['offsite_selection']['email'] == 1) {
				if (@filesize($backups_in_progress['file_db']) < (20 * 1024 * 1024)) {
					/* its small enough to email */
					$attachments = array( $backups_in_progress['file_files'] );
				}
			}


			$this->notify($subject,$message_array,'',$attachments);


		}

		
	}

	function nifty_bu_store_log($option_name,$link) {
		$file = $this->upload_dir . $this->DS() . 'nifty-bu-option-'.$option_name.'.json';
		$new = $this->upload_dir . $this->DS() . 'nifty-backups'.$this->DS().'nifty-bu-option-'.$option_name.'-report-'.$link.'.json';
		rename($file, $new);



		$file = $this->upload_dir . $this->DS() . 'backup-log.txt';
		if (@file_exists($file)) {
			$new = $this->upload_dir . $this->DS() . 'nifty-backups'.$this->DS().'backup-log-'.$link.'.txt';
			rename($file, $new);
		}

		$file_int = $this->upload_dir . $this->DS() . 'file_list_integrity_check.txt';
		$this->delete($file_int);



	}

	private function update_rows_read($count) {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		//var_dump($backups_in_progress);
		$backups_in_progress['rows_read'] = $backups_in_progress['rows_read'] + intval($count);
		$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);
	}

	private function update_current_position($table_name,$current_position) {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress_sql");
		//var_dump($backups_in_progress);
		$backups_in_progress['tables'][$table_name] = intval($current_position);
		$this->nifty_bu_update_option("nifty_backups_in_progress_sql",$backups_in_progress);
	}


	private function set_in_progress($value) {
		$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
		//var_dump($backups_in_progress);
		$backups_in_progress['in_progress'] = intval($value);
		$this->nifty_bu_update_option("nifty_backups_in_progress",$backups_in_progress);

	}

	private function is_tbl_empty($table_name) {
		global $wpdb;
		$records = $wpdb->get_results( "SELECT * FROM `$table_name` LIMIT 1", ARRAY_A );
		if (!$records) {
			return true;
		} else {
			return false;
		}
	}
	private function get_tbl_values($table_name,$offset,$limit) {
		$this->logthis(__LINE__."GETTING VALUES ########################################","verbose",false);
		$this->logthis(__LINE__."Table name: $table_name","verbose",false);
		$this->logthis(__LINE__."Table offset: $offset","verbose",false);
		$this->logthis(__LINE__."Table limit: $limit","verbose",false);
		global $wpdb;

		$maxp = $wpdb->get_var( 'SELECT @@global.max_allowed_packet' );
		if ($maxp) {
			if ($maxp > (0.1 * 1024 * 1024)) {
				// if its greater than 3mb, set it to 3mb incase we run into PHP memory issues
				$maxp = (0.1 * 1024 * 1024);
			} else {
				$this->maxpackets = $maxp;
			}
		} else {
			$this->maxpackets = 0.1 * 1024 * 1024;
		}

		/* TO DO - remove the hard set here which was used for debugging */
		$this->maxpackets = 0.05 * 1024 * 1024;





		$records = $wpdb->get_results( "SELECT count(*) as `total_items` FROM `$table_name`", ARRAY_A );
		$total_rows_in_table = $records[0]['total_items'];
		
		$sql = "SELECT * FROM `$table_name` LIMIT $limit OFFSET $offset";

		$records = $wpdb->get_results( "SELECT * FROM `$table_name` LIMIT $limit OFFSET $offset", ARRAY_A );
		if( $records ){
			$this->logthis(__LINE__."THERE ARE RECORDS","verbose",false);
			$record_size = sizeof($records);

			$recorded = 0;

			$string_value = "";
			$search = array("\x00", "\x0a", "\x0d", "\x1a");
			$replace = array('\0', '\n', '\r', '\Z');

			$ignore_search = array(
				"nifty_backups_in_progress"
			);

			$max_records_count = count($records);
			$tmp_records_count = 0;
			$chunk_size = 0;

			foreach( $records as $record ){


				//if ($tmp_records_count === 0 || $tmp_records_count % 500 == 0) {
				if ($tmp_records_count === 0) {
					$column_data = $wpdb->get_col( "SHOW COLUMNS FROM `$table_name`");
					$insert_header = "INSERT INTO ".$this->backquote($table_name)." (\n";
					$column_cnt = count($column_data);
					$column_tmp_cnt = 0;
					foreach ($column_data as $column) {
						$column_tmp_cnt++;
						if ($column_tmp_cnt < $column_cnt) { 
							$insert_header .= $this->backquote($column).",\n";
						} else {
							$insert_header .= $this->backquote($column)."\n";
						}

					}
					$insert_header .= ") VALUES ";
					
					$string_value .= $insert_header;

					

					//$this->store($insert_header);
				} 

				// calc size of header information as well.. */
				$current_record_size = mb_strlen($insert_header, '8bit');
				$chunk_size = $chunk_size + $current_record_size;

				$tmp_records_count++;
				$offset++;
				$max_record_count = count($record);

				$store_it = true;
				foreach($record as $key => $val) {
					foreach ($ignore_search as $ignore_string) {
						if (strpos($val, $ignore_string) !== false) {	
							/* we must ignore this string */
							$store_it = false;
							
							break;
						}
					}
				}





				$tmp_count = 0;
				if ($store_it) {




					if ($tmp_records_count == 1) {
						$string_value .= "\n(";
					} else {

						$my_cnt = $tmp_records_count - 1;
						//if ($my_cnt % 500 == 0) {
						//	$string_value .= "#eh?";
						//	$string_value .= "\n(";
						//} else {
							$string_value .= ",\n(";
						//}
					}
					
					foreach ($record as $key => $val) {
						
						$current_record_size = mb_strlen($val, '8bit');
						$chunk_size = $chunk_size + $current_record_size;


						$tmp_count++;

					
						if ($tmp_count === 1) {

							$val = str_replace($search, $replace, $this->sql_addslashes($val));
							$string_value .= "'$val'";

						} else {
							$val = str_replace($search, $replace, $this->sql_addslashes($val));
							$string_value .= ",'$val'";

						}
					}
							
					
					if ($tmp_records_count < $max_records_count) {
						$string_value .= ")";
					}



					if ($chunk_size >= $this->maxpackets) {
						$string_value .= ";\n";
						$string_value .= "#BREAKING HERE. CHUNK SIZE: ".$chunk_size."\n";
						$this->logthis(__LINE__."STRING FOR CHECKING ".$string_value,"verbose",false);

						return array(
							"rows_returned" => $tmp_records_count,
							"data" => $string_value
							);
					}
					//if ($tmp_records_count >= $max_records_count) {
					//	$string_value .= ");\n";
					//}
					if ($tmp_records_count >= $record_size) {
						/* this is the last record.. */
						$string_value .= ");\n";
					}
				}
				else {
					if ($tmp_records_count >= $max_records_count) {
						$string_value .= ");\n";
					}
				}
/*
				if ($tmp_records_count === 0 || $tmp_records_count % 500 == 0) {
					$string_value .= ";\n";
				} 
*/

			} 
			$this->logthis(__LINE__."ROWS REtuRNED ".$tmp_records_count,"verbose",false);
			return array(
				"rows_returned" => $tmp_records_count,
				"data" => $string_value
				);
				
		}			



	}


	function nifty_handle_directory() {
  
		$upload_dir = wp_upload_dir();
		$dir = $upload_dir['basedir'].$this->DS()."nifty-backups";

		$dir = str_replace("\\",$this->DS(),$dir);
		$dir = str_replace("/",$this->DS(),$dir);

		$url = $upload_dir['baseurl'].$this->DS()."nifty-backups";	  	
		if (!file_exists($dir)) {
	        if (@mkdir($dir)) {
	        	/* create blank index.php file to stop people from browsing the directory */
				$dest = @fopen($dir."/index.php", "a+");
				@fwrite($dest, " ");
				@fclose($dest);
	        	

	            $this->backup_directory = $dir;
	            $this->backup_url = $url;
	            

	        } else {
		    	$this->backup_directory = FALSE;
	            $this->backup_url = FALSE;
	            
	        }
	        
	    } else {
	    	$this->backup_directory = $dir;
            $this->backup_url = $url;
            
	    }

		return true;
	    
	    
	}

	private function sql_addslashes($a_string = '', $is_like = false) {
		if($is_like)
			$a_string = str_replace('\\', '\\\\\\\\', $a_string);
		else
			$a_string = str_replace('\\', '\\\\', $a_string);
		
		$a_string = str_replace('’', '&#8217;', $a_string);
		$a_string = utf8_decode($a_string);
		return str_replace('\'', '\\\'', $a_string);
	}


	function sql_file_header() {
		$this->store("# " . __('Database Backup', 'nifty-backups') . "\n");
		$this->store("# " . sprintf(__('Hostname: %s', 'nifty-backups'), get_option('siteurl')) . "\n");
		$this->store("#\n");
		$this->store("# " . sprintf(__('Generated: %s', 'nifty-backups'), date("Y-m-d H:i:s")) . "\n");
		$this->store("# " . sprintf(__('Hostname: %s', 'nifty-backups'), DB_HOST) . "\n");
		$this->store("# " . sprintf(__('Database: %s', 'nifty-backups'), $this->backquote(DB_NAME)) . "\n");
		$this->store($this->hr_sep());
	}
	function store($data) {
		$this->logthis(__LINE__." ### Storing data ###","verbose",false);
		if (is_array($data)) {

			if(false === fwrite($this->destination, $data['data'])) {
				$this->logthis(__LINE__.' There was an error writing a line to the backup script: '.$data,"simple",true);
			}
		} else {
			if(false === fwrite($this->destination, $data)) {
				$this->logthis(__LINE__.' There was an error writing a line to the backup script: '.$data,"simple",true);
			}

		}
	}
	function hr_sep() {
		return "# ========================================================\n\n";
	}

	/* Add backquotes to tables and db-names */
	function backquote($table_name) {
		if(! empty($table_name) && $table_name != '*') {
			if(is_array($table_name)) {
				$result = array();
				reset($table_name);
				while(list($key, $val) = each($table_name))
					$result[$key] = '`' . $val . '`';
				return $result;
			} else {
				return '`' . $table_name . '`';
			}
		} else {
			return $table_name;
		}
	}
	function nifty_backup_filter_control_button_handling($button_data) {
		
		if ($button_data['name'] == 'save-db') { 
			$button_data['string'] = __("Save database settings","nifty-backups");
			$button_data['icon'] = "fa-check-circle-o";
		}
		if ($button_data['name'] == 'save-files') { 
			$button_data['string'] = __("Save file settings","nifty-backups");
			$button_data['icon'] = "fa-check-circle-o";
		}
		if ($button_data['name'] == 'save-general-settings') {
			$button_data['string'] = __("Save settings","nifty-backups");
			$button_data['icon'] = "fa-check-circle-o";
		}
		if ($button_data['name'] == 'save-schedule') {
			$button_data['string'] = __("Save schedule","nifty-backups");
			$button_data['icon'] = "fa-check-circle-o";
		}
		
		return $button_data;
	}

	public static function nifty_backups_return_button($name) {
		$button_data = array();
		$button_data['name'] = $name;
		$button_data = apply_filters("nifty_backup_filter_button_handling",$button_data);
		
		
		echo '<p align="right" id="save-settings-response" style="display:block; clear:both;">&nbsp;</p>';
		echo '<p><button style="clear:both;" id="nifty-save-settings-button" type="'.$button_data['name'].'" class="nifty-settings-button '.$button_data['name'].' nifty-bg-blue nifty-white "><i class="fa '.$button_data['icon'].' fa-lg"></i> <span class="nifty-button-span">'.$button_data['string'].'</span></button></p>';
	}

	/**
	 * [nifty_backups_filter_control_exclude_system_files description]
	 *
	 * Ignores the system file that the plugin uses to keep track of tables and files.
	 * 
	 * @param  string 	$include_file         
	 * @param  string 	$file                 
	 * @param  array 	$nifty_backup_options 
	 * @return boolean
	 */
	function nifty_backups_filter_control_exclude_system_files($include_file,$file,$nifty_backup_options) {

		$ignore_list = array(
			'Thumbs.db',
			'nifty-bu-option-nifty_backups_in_progress.json',
			"uploads".$this->DS()."nifty-backups",
			"uploads".$this->DS()."dump.sql",
			"wp-content".$this->DS()."cache",
			"uploads".$this->DS()."backup-log.txt",
			"uploads".$this->DS()."file_list_integrity_check.txt",
			"nifty-bu-option-nifty_backups_in_progress_sql.json",
			"nifty-bu-option-nifty_backups_in_progress_files.json",
			"error_log"

		);
		foreach ($ignore_list as $ignore_this) {
			
			if (strpos($file,$ignore_this) === FALSE) { } else { return false; }
		}
		return $include_file;
		/*
		if (strpos($file,"nifty-bu-option-nifty_backups_in_progress.json") === false && strpos($file,"uploads".$this->DS()."nifty-backups") === false) { return true; }
		else if (strpos($file,"Thumbs.db") === false && strpos($file,"uploads".$this->DS()."nifty-backups") === false) { return true; }
		else { return false; }
		*/
	}


	function maintenance_mode_start() {
		$file = ABSPATH . '.maintenance';
		$dest = @fopen($file, "w+");
		$data = "<?php $".''."upgrading = ".time()."; /* ".date("Y-m-d H:i:s",time())." */ ?>";
		@fwrite($dest, $data);
	}

	function maintenance_mode_end() {
		@unlink(ABSPATH.'.maintenance');
	}

	function nifty_build_button() {
		if (!class_exists("ZipArchive")) {
			echo  "<span class='update-nag'>Please ensure that <strong>ZipArchive</strong> is enabled on your server. <br/><br/>Nifty Backups cannot run without enabling this first. Please <a href='http://niftybackups.com/documentation/how-to-set-enable-ziparchive-on-your-server-whm/' target='_BLANK'>view this tutorial if you are using WHM</a> or contact your host to enable this PECL package, alternatively <a href='http://niftybackups.com/contact-us' target='_BLANK'>contact a backup specialist at Nifty Backups</a></span>";
		} else {
			$backups_in_progress = $this->nifty_bu_get_option("nifty_backups_in_progress");
			if ($backups_in_progress) {
				echo "<span class='nifty-bu-information'><h1>".__("Backup in progress","nifty-backups")."</h1><p>".__("There is a backup in progress. Information about the backup will appear here periodically.","nifty-backups")."</p></span>";
			} else {
				echo "<button id='nifty-button' class='nifty-backup-button nifty-bg-blue nifty-white '><i class='fa fa-database'></i> <span class='nifty-backup-button-span'>Backup Now</span></button>";
			}
		}
	}

	/**
	 * Check if we need to skip backing up the db
	 *
	 * 0 = do not skip
	 * 1 = skip
	 *  
	 * 
	 * @param  [bool] $check
	 * @return [bool]       
	 */
	function check_skip_db($check) {
		
		$nifty_backup_options = get_option("nifty_backup_options");
		
		if (isset($nifty_backup_options['nifty_exclude_db_backup']) && $nifty_backup_options['nifty_exclude_db_backup'] == '1') {
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * Check if we need to skip backing up all files
	 *
	 * 0 = do not skip
	 * 1 = skip
	 *  
	 * 
	 * @param  [bool] $check
	 * @return [bool]       
	 */
	function check_skip_files($check) {
		$nifty_backup_options = get_option("nifty_backup_options");
		if (isset($nifty_backup_options['nifty_exclude_files_backup']) && $nifty_backup_options['nifty_exclude_files_backup'] == '1') {
			return true;
		} else {
			return false;
		}
	}






}

$nifty_backup_class = new CodeCabinBackups();







