<?php

add_filter("nifty_backup_email_wrapper","nifty_backup_email_wrapper_control",10,1);
function nifty_backup_email_wrapper_control($data) {
	$dir = dirname(dirname(__FILE__));
	$template_content_template = file_get_contents($dir."/templates/mail_template.html");
	


	$template_content_template = str_replace("{logo}","<img src='".plugins_url("/images/nifty_email_logo.png",__FILE__)." alt='Nifty Backups' title='Nifty Backups' border='0' />",$template_content_template);
	$template_content_template = str_replace("{header}",$data['header'],$template_content_template);
	$template_content_template = str_replace("{message}",$data['message'],$template_content_template);
	$template_content_template = str_replace("{footer}",$data['footer'],$template_content_template);

	return $template_content_template;



}