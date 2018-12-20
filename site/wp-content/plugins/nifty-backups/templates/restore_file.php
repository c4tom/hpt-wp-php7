<div id='nifty_dashboard_inner_top'>
	&nbsp;
</div>

<?php 

	$safemode = ini_get('safe_mode');
	 
?>

<?php
		$score = 0;
		if (isset($_POST['extra_item'])) {
	 		$file = nifty_bu_upload_dir.$_POST['extra_item'];
	 	} else {
	 		global $nb_ext_file;
	 		$file = $nb_ext_file;
	 	}
	 	?>
<div id='nifty_dashboard_content'>
	<h3><?php _e("Restore (files)","nifty-backups"); ?></h3>
	<h4><?php echo __("Filename:","nifty-backups"). " ".$file; ?></h4>
	<h4><?php _e("IMPORTANT INFORMATION","nifty-backups"); ?></h4>
	<p><?php _e("Please note that in order for the restoration to take place, the script that handles the restore must start and end in one loop. In order for this to happen, the PHP time limit of your host must not be restricted.","nifty-backups"); ?></p>

	<p><?php _e("The length of time required to run the restore is dependant on the size of the ZIP file that is being handled.","nifty-backups"); ?></p>
	<?php 
		if($safemode){ ?>
	<span class='update-nag'>
	<p><?php _e("Safe mode is enabled on this server. This means that we cannot increase the timeout limit for PHP programmatically, which may negatively affect your restore process.", "easy-backup"); ?></p>
	<p><?php  _e("Before contuing, we recommend that you either ask your host to remove 'PHP safe mode' or that you upload the SQL file yourself using PHPMyAdmin or equivalent.", "easy-backup");  ?></p></span>
<?php } 

	
	 $orig_filesize = filesize($file);
	
	 $filesize = $this->format_size($orig_filesize);
	 
	 if ($safemode) { 
	 	$safe_col = 'nifty-red'; $safe_text = 'high risk of failure'; $safe_desc = 'Yes';
	 	$score--;
 	 } else {
 	 	$safe_col = 'nifty-green'; $safe_text = 'low risk of failure'; $safe_desc = 'No';
 	 	$score++;
 	 }

	 if ($orig_filesize < 500000000) { $size_col = 'nifty-green'; $size_text = 'low risk of failure'; $score++; }
	 if ($orig_filesize >= 500000000 && $orig_filesize < 1000000000) { $size_col = 'nifty-orange'; $size_text = 'medium risk of failure'; }
	 if ($orig_filesize >= 1000000000) { $size_col = 'nifty-red'; $size_text = 'high risk of failure'; $score--; }


	$memory_limit = ini_get('memory_limit');
	if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
	    if ($matches[2] == 'M') {
	        $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
	    } else if ($matches[2] == 'K') {
	        $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
	    }
	}

	
	 if ($memory_limit <= 33554432) { $mem_col = 'nifty-red'; $mem_text = 'high risk of failure'; $score--;  }
	 if ($memory_limit > 33554432 && $memory_limit <= 134217728) { $mem_col = 'nifty-orange'; $mem_text = 'medium risk of failure'; }
	 if ($memory_limit >= 134217728) { $mem_col = 'nifty-green'; $mem_text = 'low risk of failure'; $score++; }


	

	$risk_perc = (100 - (round($score / 3)*100));
	if ($risk_perc <= 33) {
		$risk_desc = __("Low risk","easy-backup");
		$risk_style = 'style="color:green;"';
	}
	if ($risk_perc > 33) {
		$risk_desc = __("High risk of failure. Please use PHPMyAdmin to restore this SQL file instead.","nifty-backups");
		$risk_style = 'style="color:red;"';
	}
	


	?>

	<p>
		<table cellpadding='5'>
			<tr>
				<td>File size</td>
				<td><strong> <?php echo $filesize; ?></strong></td>
				<td><span class='<?php echo $size_col; ?>'><?php echo $size_text; ?></span></td>
				<td></td>
			</tr>
			<tr>
				<td>PHP Safe Mode Enabled</td>
				<td><strong> <?php echo $safe_desc; ?></strong></td>
				<td><span class='<?php echo $safe_col; ?>'><?php echo $safe_text; ?></span></td>
				<td></td>
			</tr>
			<tr>
				<td>PHP Memory</td>
				<td><strong> <?php echo $this->format_size($memory_limit); ?></strong></td>
				<td><span class='<?php echo $mem_col; ?>'><?php echo $mem_text; ?></span></td>
				<td></td>
			</tr>
			<tr>
				<td colspan='4'> &nbsp; </td>
			</tr>			
			<tr>
				<td>Risk:</td>
				<td><h4><?php echo $risk_perc; ?>%</h4></td>
				<td><span <?php echo $risk_style; ?>><?php echo $risk_desc; ?></span></td>
				<td><a id='' href='' style='font-style:italic'><?php _e("Need help? Hire a backup specialist.","nifty-backups"); ?></a></td>
		</table>

	</p>

	<p class='restore-buttons-p'>

		<button id='nifty-button-restore-files_<?php echo $_POST['extra_item']; ?>' class='nifty-button-restore-file button button-primary' bid='<?php echo $_POST['extra_item'] ?>' value=''><span class='nifty-button-restore-file_span'><?php _e("Restore now","nifty-backups"); ?></span></button>
		<button id='' class='nifty_menu_item button button-default' menuitem='dashboard' value=''><?php _e("Cancel","nifty-backups"); ?></button>



	</p>
	<p class='restore-feedback'></p>
	<p>	 </p>

	

</div>