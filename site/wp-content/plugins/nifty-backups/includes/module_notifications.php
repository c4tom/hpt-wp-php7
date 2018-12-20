<?php

add_filter("nifty_backups_filter_settings_paramter_intercept", "nifty_backups_filter_settings_paramter_intercept_notifications",9,2);
function nifty_backups_filter_settings_paramter_intercept_notifications($nifty_backup_options,$params) {
		if (isset($params['nifty_notification_email_array'])) {
			$email_str = trim($params['nifty_notification_email_array']);
			$emails = explode(",",$email_str);

			$nifty_backup_options['nifty_notification_email_array'] = $emails;
		}
		return $nifty_backup_options;
}

add_action("nifty_backups_general_settings_output_hook","nifty_backups_hook_control_general_settings_output_hook_notifications",9);
function nifty_backups_hook_control_general_settings_output_hook_notifications($nifty_backup_options) {

	echo "<h3>".__("Notification Control","nifty-backups")."</h3>";
	echo "<table class='wp-list-table widefat fixed'>";
	echo "<tr>";
	echo "<td>";
	echo __("Email to send notifications","nifty-backups");
	echo "</td>";
	echo "<td>";
	echo "<input type='text' style='width:100%;' id='nifty_notification_email_array' name='nifty_notification_email_array' value='".((isset($nifty_backup_options['nifty_notification_email_array']) && $nifty_backup_options['nifty_notification_email_array'] != '') ? esc_attr( implode( "," , $nifty_backup_options['nifty_notification_email_array'] ) ) : get_option('admin_email') )."' />";
	echo "<p class='description'>".__("Multiple emails seperated by comma","nifty-backups")."</p>";
	echo "</td>";
	echo "</tr>";
	echo "</table>";
}