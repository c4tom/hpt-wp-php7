var backup_ping;
var restore_ping;
var backup_ping_in_progress = false;
var orig_elems = new Array();

jQuery(document).ready(function(){
	console.log("loaded basic admin");


	function nifty_findGetParameter(parameterName) {
	    var result = null,
	        tmp = [];
	    location.search
	    .substr(1)
	        .split("&")
	        .forEach(function (item) {
	        tmp = item.split("=");
	        if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
	    });
	    return result;
	}
	var auto_menu_nav = nifty_findGetParameter("tab");
	console.log(auto_menu_nav);
	if (typeof auto_menu_nav !== "undefined" && auto_menu_nav !== null) {
		console.log("okie dokie");
		var elemn = jQuery( "a[menuitem='"+auto_menu_nav+"']" );
		console.log(elemn[0]);
		setTimeout(function() { jQuery(elemn[0]).trigger('click'); },1000);	
	}

	if (typeof nifty_backup_perc !== 'undefined' && nifty_backup_perc) {
		nifty_update_button(nifty_localize_backup_started_information,"x");
		backup_ping = true;

		backup_pinger();


	}

    jQuery("body").on("click",".nifty-cloud-upload", function() {
        var bid = jQuery(this).attr('bid');
        jQuery(this).prop('disabled',true);
        orig_elems[bid] = jQuery(this).html();
        var orig_e = jQuery(this);



        jQuery(this).html('<i class="fa fa-circle-o-notch fa-spin"></i>');
        var data = {
            action: 'nifty_cloud_upload',
            bid: bid,
            security: nifty_backup_nonce
        };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
                console.log(response);
                if (response === "1") {
                    jQuery("#nifty_dashboard_content").prepend("Successfully uploaded "+bid+"<br />");
                    jQuery(orig_e).prop('disabled',false);
                    jQuery(orig_e).html(orig_elems[bid]);
                } else {
                    jQuery("#nifty_dashboard_content").prepend("Failed to upload "+bid + "<br />"+response);
                    jQuery(orig_e).prop('disabled',false);
                    jQuery(orig_e).html(orig_elems[bid]);
                }
            },
            error: function(response) {
                jQuery("#nifty_dashboard_content").prepend("Failed to upload "+bid + "<br />"+response);
                jQuery(orig_e).prop('disabled',false);
                jQuery(orig_e).html(orig_elems[bid]);
            }
        });

        
    });


    jQuery("body").on("click", ".offsite_selector", function() {
        var target_id = jQuery(this).attr('bid');
        jQuery(".buoption_"+target_id).toggle();
    })
    jQuery("body").on("nifty_backups_content_loaded", function() {
        jQuery('.offsite_selector').each(function () {
            if (this.checked) {
                var target_id = jQuery(this).attr('bid');
                //jQuery(".buoption_"+target_id).toggle();
            }
            
        });

    });

	jQuery("body").on("click", ".nifty_bu_cancel", function() {
		var proceed = confirm('Are you sure you want to cancel this backup?');

		if (proceed) {
			backup_ping = false;
			jQuery(".nifty-bu-information").html("Cancelling... Please be patient.");			
			var data = {
		        action: 'nifty_cancel_backup',
		        security: nifty_backup_nonce
		    };
	        jQuery.ajax({
	            url: nifty_backup_ajaxurl,
	            data:data,
	            type:"POST",
	            success: function(response) {
	            	console.log(response);
	            	if (response === "1") {
	            		backup_ping_in_progress = false
	            		jQuery(".nifty-bu-information").remove();
	            		jQuery("#nifty_dashboard_content").html("");
        				var elemn = jQuery( "a[menuitem='dashboard']" );
						setTimeout(function() { jQuery(elemn[0]).trigger('click'); },500);	
	            	} else {
	            		
	            	}
	            }
	        });
		}
	});

	jQuery("body").on("click",".nifty-button-restore-file", function() {
		var bid = jQuery(this).attr('bid');
		nifty_restore_button_file(bid,"0");
		console.log(bid);
		restore_file_ajax(bid);
		return;
	});
	jQuery("body").on("click",".nifty-button-restore-db", function() {
		var bid = jQuery(this).attr('bid');
		nifty_restore_button_db(bid,"0");
		console.log(bid);
		restore_db_ajax(bid);
		return;
	});	
	

	jQuery("body").on("click",".nifty-button-delete", function() {
		var bid = jQuery(this).attr('bid');
		var data = {
	        action: 'nifty_delete_file',
	        bid: bid,
	        security: nifty_backup_nonce
	    };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
            	console.log(response);
            	if (response === "1") {
            		var element = document.getElementById("tr_"+bid);
					element.parentNode.removeChild(element);
            	} else {
            		alert("Error deleting this file. Please contact support");
            	}
            }
        });

		console.log(bid);
	});
		

	jQuery("body").on("click","#nifty_backups_backup_from_url", function() {
		jQuery(this).prop('disabled',true);
		var ext_file = jQuery("#nifty_backups_file_url").val();
		jQuery(".nb_desc").html("<strong><em>"+nifty_localize_restore_external+"</em></strong>");
		jQuery(".nb_desc").toggle();

		if (ext_file.length > 0) {
			var data = {
		        action: 'nifty_restore_external',
		        ext_file: ext_file,
		        security: nifty_backup_nonce
		    };
	        jQuery.ajax({
	            url: nifty_backup_ajaxurl,
	            data:data,
	            type:"POST",
	            success: function(response) {
					jQuery(this).prop('disabled',false);
					jQuery(".nb_desc").toggle();
	            	var resp = JSON.parse(response);
	            	if ( typeof resp.filen !== "undefined" ) {

	            		if (resp.filetype == "db") {
	            			menu_item = 'restore_db';
	            		} else {
	            			menu_item = 'restore_file';
	            		}

	            		var extra_item = resp.filen;
	            		var data = {
					        action: 'view_change',
					        menu_item: menu_item,
					        extra_item: extra_item,
					        security: nifty_backup_nonce
					    };
				        jQuery.ajax({
				            url: nifty_backup_ajaxurl,
				            data:data,
				            type:"POST",
				            success: function(response) {
				            	jQuery("#nifty_dashboard_inner").html(response);
				            	console.log("trigger");
				        	    jQuery("body").trigger( 'nifty_backups_content_loaded' );

				            }
				        });


	            	}
	            	if (typeof resp.err !== "undefined") {
	            		alert(resp.err);

	            	}
	            },
	            error: function(response) {
					jQuery(this).prop('disabled',false);
        			jQuery(".nb_desc").html("Something went wrong, please try again. (Error: "+response+")");
	            }
	        });
		} else {
			jQuery(this).prop('disabled',false);

			alert("Please insert a URL");
		}
	});

	jQuery("body").on("click",".nifty-backup-button-menu", function() {

		var orig_btn = jQuery(".nifty-backup-button").html();
		var elem = jQuery(".nifty-backup-button");
		nifty_update_button("Identifying tables and files...","0");
		jQuery("#nifty_dashboard_content").fadeOut("slow");
		get_bu_info(null,null);


	});
	jQuery("body").on("click",".nifty-backup-button", function() {
		var orig_btn = jQuery(this).html();
		var elem = jQuery(this);
		var confirm_backup = jQuery(this).attr('confirm-backup');
		if (confirm_backup) {
			jQuery(this).remove();
			jQuery("#nifty_dashboard_inner_top").html(elem);
			jQuery("#nifty_dashboard_content").html(nifty_localize_backup_started_html);
			nifty_update_button(nifty_localize_backup_started_information,"x");
			backup_ping = true;
			backup_pinger_start();
		} else {
			nifty_update_button("Identifying tables and files...","0");
			jQuery("#nifty_dashboard_content").fadeOut("slow");
			get_bu_info(elem,orig_btn);
		}


	});


	jQuery("body").on("click",".nifty_menu_item", function() {
		jQuery("#nifty_dashboard_inner").html("<span class='nifty_content_loader'><i class='fa fa-circle-o-notch fa-spin fa-5x fa-fw margin-bottom'></i></span>");
		var menu_item = jQuery(this).attr('menuitem');
		var extra_item = jQuery(this).attr('extraitem');
	    var data = {
	        action: 'view_change',
	        menu_item: menu_item,
	        extra_item: extra_item,
	        security: nifty_backup_nonce
	    };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
            	jQuery("#nifty_dashboard_inner").html(response);
            	console.log("trigger");
        	    jQuery("body").trigger( 'nifty_backups_content_loaded' );

            }
        });


	});

	jQuery("body").on("click","#nifty-save-settings-button", function(event) {
		event.preventDefault();
		var orig_btn = jQuery(this).html();
		var ele = jQuery(this);
		jQuery(this).html("<i class='fa fa-cog fa-spin fa-fw margin-bottom'></i> "+nifty_save_string+"</span>");
		var type = jQuery(this).attr('type');
		var input_data = jQuery("#nifty-save-settings").serialize();

		console.log(input_data);
		var data = {
	        action: 'nifty-save-settings',
	        type: type,
	        input_data: input_data,
	        security: nifty_backup_nonce
	    };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
            	console.log("eh");
            	console.log(response);
            	jQuery(ele).html(orig_btn);
            	console.log("YES");
            	jQuery("#save-settings-response").html(nifty_save_successful).fadeIn("slow");
            	jQuery("body").trigger( 'nifty_settings_saved' );
            	setTimeout(function(){ jQuery("#save-settings-response").fadeOut("slow"); },2000);
            }
        });
		
	});

	jQuery('#niftymenu li.active').addClass('open').children('ul').show();
	jQuery('#niftymenu li.has-sub>a').on('click', function(){
		jQuery(this).removeAttr('href');
		var element = jQuery(this).parent('li');
		if (element.hasClass('open')) {
			element.removeClass('open');
			element.find('li').removeClass('open');
			element.find('ul').slideUp(200);
		}
		else {
			element.addClass('open');
			element.children('ul').slideDown(200);
			element.siblings('li').children('ul').slideUp(200);
			element.siblings('li').removeClass('open');
			element.siblings('li').find('li').removeClass('open');
			element.siblings('li').find('ul').slideUp(200);
		}
	});

	
	function nifty_restore_button_file(bid,perc) {
		jQuery(".nifty-button-restore-file").attr('disabled','disabled');
		jQuery(".restore-feedback").html("<strong>"+nifty_localize_restore_maintenance+"</strong>");
		jQuery('.nifty-button-restore-db_span').html("<i class='fa fa-cog fa-spin fa-fw margin-bottom'></i>");

	}
	function nifty_restore_button_db(bid,perc) {
		jQuery(".nifty-button-restore-db").attr('disabled','disabled');
		jQuery(".restore-feedback").html("<strong>"+nifty_localize_restore_maintenance+"</strong>");
		jQuery('.nifty-button-restore-db_span').html("<i class='fa fa-cog fa-spin fa-fw margin-bottom'></i>");

	}	
	
	function nifty_update_button(backup_string, nifty_backup_perc) {
		jQuery(".nifty-backup-button").attr('disabled','disabled');
		jQuery(".nifty-backup-button").css('display','none');
		if (nifty_backup_perc === "x") {
			jQuery('#nifty_dashboard_inner_top').html("<span class='nifty-bu-information'>"+backup_string+" <i class='fa fa-cog fa-spin fa-fw margin-bottom'></i>  <a href='javascript:void(0);' class='nifty_bu_cancel'>cancel</a></span>");
		} else {
			jQuery('#nifty_dashboard_inner_top').html("<span class='nifty-bu-information'>"+backup_string+" "+nifty_backup_perc+"%  <i class='fa fa-cog fa-spin fa-fw margin-bottom'></i>  <a href='javascript:void(0);' class='nifty_bu_cancel'>cancel</a></span>");
		}

	}
	function get_bu_info(elem,orig_btn) {
		var data = {
	        action: 'nifty_backup_info',
	        security: nifty_backup_nonce
	    };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
                
                if(response){
                	aresp = JSON.parse(response);
                	jQuery("#nifty_dashboard_content").html(aresp.data);
                	var cont = aresp.cont;
        			jQuery("#nifty_dashboard_content").fadeIn("slow");
					
					jQuery("#nifty_dashboard_inner_top").html("");
        			/*
					var new_elem = elem;
					jQuery(elem).html(orig_btn);
					jQuery(elem).removeAttr('disabled');
					jQuery(elem).remove();
					jQuery("#nifty_dashboard_content").append(new_elem);
					jQuery("#nifty_dashboard_content").append("<p>&nbsp;</p>");
                	if (cont === false) { 
						jQuery(new_elem).css("display","block");
                		jQuery(new_elem).prop('disabled',true);
						jQuery(new_elem).css("cursor","not-allowed");
                	} else {
						jQuery(new_elem).removeClass("align-right");
						jQuery(new_elem).attr("confirm-backup","true");
						jQuery(new_elem).css("display","block");
					}
					*/

                }
            }
        });
	}

	function backup_pinger_start() {
			console.log("Sending INITIAL backup ping");
		    var data = {
		        action: 'nifty_backup_start',
		        security: nifty_backup_nonce
		    };
	        jQuery.ajax({
	            url: nifty_backup_ajaxurl,
	            data:data,
	            type:"POST",
	            success: function(response) {
	                
	                if(response){
	                	response = JSON.parse(response);
	                	console.log(response);
	                	if (response['db'] === 100) {
							nifty_update_button("Backing up the database","100");
	                		

							if (response['files'] !== 100) {
								/* busy with files now */
								nifty_update_button("Backing up files",response['files']);
								setTimeout(function() { backup_pinger(); }, 2000);

							}

							if (response['files'] === 100) {
								nifty_update_button("Backing up files",response['files']);								
								location.reload();
							}

	                		
	                		/* we are done */
	                	} else {
	                		/* keep going.. */
	                		perc = response['db'];
							nifty_update_button("Backing up the database",perc);

	                		setTimeout(function() { backup_pinger(); }, 2000);
	                	}
	                } else {
	                	/* may have timed out ? */
	                	setTimeout(function() { backup_pinger(); }, 2000);

	                }
	            },
			    error: function (request, status, error) {
			    	console.log("error "+status+ " : " +error);	
			        setTimeout(function() { backup_pinger(); }, 10000);
			    }
	        });
		
	}
	function backup_pinger() {
		if (!backup_ping_in_progress) {
			backup_ping_in_progress = true;
			if (backup_ping) {
				console.log("Sending backup ping");
			    var data = {
			        action: 'nifty_backup',
			        security: nifty_backup_nonce
			    };
		        jQuery.ajax({
		            url: nifty_backup_ajaxurl,
		            data:data,
		            type:"POST",
		            success: function(response) {
				    	backup_ping_in_progress = false;
	                
		                if(response){
		                	response = JSON.parse(response);
		                	console.log(response);
		                	if (response['db'] === 100) {
								nifty_update_button("Backing up the database","100");
		                		

								if (response['files'] !== 100) {
									/* busy with files now */
									nifty_update_button("Backing up files",response['files']);
									setTimeout(function() { backup_pinger(); }, 2000);

								}

								if (response['files'] === 100) {
									nifty_update_button("Backing up files",response['files']);								
									location.reload();
								}

		                		
		                		/* we are done */
		                	} else {
		                		/* keep going.. */
		                		perc = response['db'];
								nifty_update_button("Backing up the database",perc);

		                		setTimeout(function() { backup_pinger(); }, 2000);
		                	}
		                } else {
		                	console.log("no response from server");
		                	/* may have timed out ? */
		                	setTimeout(function() { backup_pinger(); }, 2000);

		                }
		            },
				    error: function (request, status, error) {
				    	backup_ping_in_progress = false;
				        setTimeout(function() { backup_pinger(); }, 10000);
				    }
		        });
			}
		} else {
			console.log("not running backup ping now!");
		}
	}
	function restore_file_ajax(bid) {
	    var data = {
	        action: 'nifty_restore',
	        bid: bid,
	        security: nifty_backup_nonce
	    };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
                
                if (response === "1") {
                	jQuery(".restore-buttons-p").hide();
                	jQuery(".restore-feedback").html("<strong>Restore successful!</strong>");
                }
                else {
                	jQuery(".restore-buttons-p").hide();
                	jQuery(".restore-feedback").html("There was an error restoring the ZIP file. Please contact <a href='http://niftybackups.com'>Nifty Backups Support</a>.");
                }
                
            }
        });
	}

	function restore_db_ajax(bid) {
	    var data = {
	        action: 'nifty_restore',
	        bid: bid,
	        security: nifty_backup_nonce
	    };
        jQuery.ajax({
            url: nifty_backup_ajaxurl,
            data:data,
            type:"POST",
            success: function(response) {
                
                if (response === "1") {
                	jQuery(".restore-buttons-p").hide();
                	jQuery(".restore-feedback").html("<strong>Restore successful!</strong>");
                }
                else {
                	jQuery(".restore-buttons-p").hide();
                	jQuery(".restore-feedback").html("There was an error restoring the Database file. Please contact <a href='http://niftybackups.com'>Nifty Backups Support</a>.");
                }
                
            }
        });
	}

});
