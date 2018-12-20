<?php

include ("module_offsite_email.php");

add_filter("nifty_backup_filter_button_handling","nifty_backup_pro_filter_control_button_handling",11,1);
function nifty_backup_pro_filter_control_button_handling($button_data) {
		
	if ($button_data['name'] == 'save-offsite') {
		$button_data['string'] = __("Save offsite storage settings","nifty-backups");
		$button_data['icon'] = "fa-check-circle-o";	
	}

	return $button_data;
}


add_filter( 'nifty_backup_filter_main_menu_settings', 'nifty_backup_menu_control_settings_offsite' ,9,1 );
function nifty_backup_menu_control_settings_offsite($text) {
?>
   <li><a href='#' class='nifty_menu_item' menuitem="offsite"> &nbsp; <i class='fa fa-hdd-o'></i> &nbsp; <?php _e("Offsite Storage","nifty-backups"); ?></a></li>
<?php
}


add_action("nifty_backups_hook_dash_list_buttons","nifty_backups_pro_hook_control_dash_list_buttons",10,1);
function nifty_backups_pro_hook_control_dash_list_buttons($file) {
	?>
	<button id="cloud-upload" class='nifty-button nifty-cloud-upload' bid='<?php echo $file ?>' title="<?php _e("Send to cloud","nifty-backups"); ?>"><i class='fa fa-cloud-upload'></i></button>
	<?php
}


add_action( 'nifty_backup_action_view_change' , 'nifty_backup_pro_action_view_change' , 10);
function nifty_backup_pro_action_view_change($post_data) {
	if (isset($post_data['menu_item'])) {
		if ($post_data['menu_item'] == "offsite") { 
			nifty_backup_pro_offsite_menu();
		}


		wp_die();
	}
}



function nifty_backup_pro_offsite_menu() {
?>
<div id='nifty_dashboard_inner_top'>

</div>
<div id='nifty_dashboard_content'>
	<form id='nifty-save-settings'>
		<h2><?php _e("Offsite Storage Settings","easy-backups"); ?></h2>
		<?php nifty_backup_pro_offsite_settings(); ?>
	</form>

</div>

<?php
}


function nifty_backup_pro_offsite_settings() {
		$nifty_backup_options = get_option("nifty_backup_options");
		echo "<input type='hidden' val='1' name='db_offsite' />";

		
			echo '<table  class="wp-list-table widefat fixed">';

		echo '<tbody>';
		echo '<tr>';
		echo '<td width="20%" align="left" valign="top">'.__("Send my backups to","nifty-backups").'</td>';
		echo '<td>';
		echo '<input type="hidden" name="nb_offsite_page" id="nb_offsite_page" value="1"  />';
		$buarray = array();
		$checked_buoption_display = array();

		$buarray = apply_filters("nifty_backups_filter_offsite_selection",$buarray);
		foreach ($buarray as $buoption) {
			$checked = '';
			if (isset($nifty_backup_options['offsite_selection']) && isset($nifty_backup_options['offsite_selection'][$buoption['name']]) && $nifty_backup_options['offsite_selection'][$buoption['name']] == 1) {
					$checked = "checked='checked'";
					$checked_buoption_display[$buoption['name']] = 'display:block';
			} else { 
				$checked = "";
				$checked_buoption_display[$buoption['name']] = 'display:none';
			}

			echo '<input type="checkbox" class="offsite_selector" bid="'.$buoption['name'].'" name="nifty_offsite_list[]" '.$checked.' '.$buoption['disabled'].' value="'.$buoption['name'].'" />'.$buoption['nice_name'].' <br />';
		}
		echo '</tr>';
		echo '</tbody>';
		echo '</table>';

		foreach ($buarray as $buoption) {
			echo '<div class="nb_conditional_div buoption_'.$buoption['name'].'" style="'.$checked_buoption_display[$buoption['name']].'">';
			call_user_func($buoption['formbuild'],$buoption,$nifty_backup_options);
			echo '</div>';
		}
		CodeCabinBackups::nifty_backups_return_button("save-offsite");

}