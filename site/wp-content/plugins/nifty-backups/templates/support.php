<div id='nifty_dashboard_inner_top'>

</div>
<div id='nifty_dashboard_content'>
	<form id='nifty-save-settings'>
		<h2><?php _e("Support","nifty-backups"); ?></h2>
		
		<p>For support, please <a href='http://niftybackups.com/contact-us/' target='_BLANK'>contact us</a> and we'll get back to you as soon as humanly possible! You may want to reference the below information in your support request so we can assist you quicker!</p>


		<?php
		global $wpdb;
		$wpdb->query( 'SET @@global.max_allowed_packet = ' . 1 * 1024 * 1024 );
		$maxp = $wpdb->get_var( 'SELECT @@global.max_allowed_packet' );

		$safemode = ini_get('safe_mode');
		if ($safemode) { $safe = "true"; } else { $safe = "false"; }

		$memory_limit = ini_get('memory_limit');
		if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
		    if ($matches[2] == 'M') {
		        $memory_limit = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
		    } else if ($matches[2] == 'K') {
		        $memory_limit = $matches[1] * 1024; // nnnK -> nnn KB
		    }
		}

		$nifty_backup_options = get_option("nifty_backup_options");
		if (isset($nifty_backup_options['nifty_db_rows'])) { $rows = $nifty_backup_options['nifty_db_rows']; } else { $rows = 200; }
		if (isset($nifty_backup_options['nifty_files'])) { $files = $nifty_backup_options['nifty_files']; } else { $files = 1500; }



		if (class_exists("ZIPArchive")) { $zip_en = 'true'; } else { $zip_en = 'false'; }
		?>
		<table class='wp-list-table widefat fixed'>
			<tr>
				<td>WordPress version</td>
				<td><?php echo get_bloginfo('version'); ?></td>
			</tr>
			<tr>
				<td>PHP version</td>
				<td><?php echo phpversion(); ?></td>
			</tr>
			<tr>
				<td>Memory</td>
				<td><?php echo $this->format_size($memory_limit); ?></td>
			</tr>
			<tr>
				<td>Safe mode enabled</td>
				<td><?php echo $safe; ?></td>
			</tr>
			<tr>
				<td>ZIPArchive enabled</td>
				<td><?php echo $zip_en; ?></td>
			</tr>
			<tr>
				<td>Database max packet size</td>
				<td><?php echo number_format($maxp); ?> bytes</td>
			</tr>
			<tr>
				<td>Table rows per backup iteration</td>
				<td><?php echo number_format($rows); ?> rows</td>
			</tr>
			<tr>
				<td>Files per backup iteration</td>
				<td><?php echo number_format($files); ?> files</td>
			</tr>
			<tr>
				<td>Multisite</td>
				<td><?php if ( is_multisite() ) { echo 'Yes'; } else { echo 'No'; } ?></td>
			</tr>

			
		</table>
		</pre>

	</form>

</div>