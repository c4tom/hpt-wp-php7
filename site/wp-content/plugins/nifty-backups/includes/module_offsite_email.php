<?php

/**
 * Upselling
 */
add_filter("nifty_backups_filter_offsite_selection","nifty_backups_filter_control_offsite_selection_upselling",3,1);
function nifty_backups_filter_control_offsite_selection_upselling($buarray) {
	$buarray['ftp'] = array(
		'name' => 'ftp',
		'nice_name' => "FTP Server ".sprintf(__("(<a target='_BLANK' href='%s'>Pro version</a>)","nifty-backups"),'http://niftybackups.com/?utm_source=plugin&utm_medium=link&utm_campaign=ftp'),
		'formbuild' => "nifty_backup_pro_email_us1",
		'disabled' => 'disabled readonly'
	);
	$buarray['google-drive'] = array(
		'name' => 'google-drive',
		'nice_name' => "Google Drive ".sprintf(__("(<a target='_BLANK' href='%s'>Pro version</a>)","nifty-backups"),'http://niftybackups.com/?utm_source=plugin&utm_medium=link&utm_campaign=gdrive'),
		'formbuild' => "nifty_backup_pro_email_us1",
		'disabled' => 'disabled readonly'
	);
	$buarray['dropbox'] = array(
		'name' => 'dropbox',
		'nice_name' => "Dropbox ".sprintf(__("(<a target='_BLANK' href='%s'>Pro version</a>)","nifty-backups"),'http://niftybackups.com/?utm_source=plugin&utm_medium=link&utm_campaign=dropbox'),
		'formbuild' => "nifty_backup_pro_email_us1",
		'disabled' => 'disabled readonly'
	);
	

	return $buarray;
}

function nifty_backup_pro_email_us1() { }




add_action("nifty_backups_send_to_cloud_hook","nifty_backups_send_to_cloud_hook_email",11,1);
function nifty_backups_send_to_cloud_hook_email($data) {
	$nifty_backup_options = get_option("nifty_backup_options");
	if (isset($nifty_backup_options['offsite_selection']) && $nifty_backup_options['offsite_selection']['email'] == 1) {
		try {
		    $checker = nifty_backups_action_send_to_email(array("filelocation" => $data['filelocation'], "filename" => $data['filename']));
		    if (is_array($checker)) {
				echo $checker['error'];
				die();
			}

		} catch (Exception $e) {	
			/* something went wrong */
		}
	}
	return $data;
	
}

function nifty_backups_action_send_to_email($data) {

	$nifty_backup_options = get_option("nifty_backup_options");


	if (isset($nifty_backup_options['offsite_selection']) && $nifty_backup_options['offsite_selection']['email'] == 1) {
			
			if (isset($nifty_backup_options['offsite_selection']) && isset($nifty_backup_options['offsite_selection']['email']) && $nifty_backup_options['offsite_selection']['email'] == 1) {
				if (@filesize($data['filelocation']) < (20 * 1024 * 1024)) {
					$subject = __("Nifty Backups - Backup File Attachment","nifty-backups");
					$body_header = __("Backup File Attachment","nifty-backups");
					$body = sprintf(__("A request was received to email the backup file: %s"), $data['filelocation'] );
					$body_footer = __("Thank you for using <a href='http://niftybackups.com'>Nifty Backups</a>.","nifty-backups");
					$message_array = array(
						"body" => $body,
						"header" => $body_header,
						"footer" => $body_footer
					);
					
					$attachments = false;

					$attachments = array( $data['filelocation'] );
					CodeCabinBackups::notify($subject,$message_array,'',$attachments);
				
				} else {
					return array("error"=>__("The file is too big to send over email. There is a 20mb limit","nifty-backups"));


				}
			}
	}
}




add_filter("nifty_backups_filter_offsite_selection","nifty_backups_filter_control_offsite_selection_email",2,1);
function nifty_backups_filter_control_offsite_selection_email($buarray) {
	$buarray['email'] = array(
		'name' => 'email',
		'nice_name' => "Email",
		'formbuild' => "nifty_backup_pro_email_form",
		'disabled' => ''
	);
	

	return $buarray;
}



function nifty_backup_pro_email_form($buoption,$backup_options) {

	if (isset($backup_options['buoption_offsite_email'])) {
		$buoption_offsite_email = $backup_options['buoption_offsite_email'];
	} else {
		$buoption_offsite_email = false;
	}

	echo "<h2>".__("Email","nifty_backups")."</h2>";
	echo "<p>".__("All backup ZIP files under 20mb will be emailed.","nifty-backups")."</p>";


}




add_action("nifty_backups_filter_save_settings","nifty_backups_pro_filter_control_save_settings_offsite_email",99,1);
function nifty_backups_pro_filter_control_save_settings_offsite_email($params) {

	if (isset($params['nb_offsite_page']) && $params['nb_offsite_page'] == '1') {
		$nifty_backup_options = get_option("nifty_backup_options");

		$nifty_backup_options['offsite_selection']['email'] = 0;

		if (isset($params['nifty_offsite_list'])) {
			if (isset($params['nifty_offsite_list']) && is_array($params['nifty_offsite_list'])) {
				foreach ($params['nifty_offsite_list'] as $key => $val) {
					if ($val == 'email') { $nifty_backup_options['offsite_selection'][$val] = 1;	}
				}

				
			} else {
				$nifty_backup_options['offsite_selection'] = null;
			}
		}

		update_option("nifty_backup_options",$nifty_backup_options);
	}
	return $params;
}