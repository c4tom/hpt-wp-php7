<div id='nifty_dashboard_inner_top'>
	<?php echo do_action("nifty_bu_build_button"); ?>
</div>
<div id='nifty_dashboard_content'>

	<h3><?php _e("Available backups","nifty-backups"); ?></h3>

	<table class='wp-list-table widefat fixed'>
		<tr>
			<td><?php $this->get_available_backups(false); ?></td>
		</tr>
	</table>

</div>