
<center>

    <img style='display:block; margin:15px;' src="<?php echo plugins_url(dirname(plugin_basename(dirname(__FILE__)))); ?>/images/nifty_backups_logo.png"  width='30%'/>

    <h1 style="font-weight: 300; font-size: 50px; line-height: 50px;">
        <?php _e("Welcome!",'nifty-backups'); ?> 
    </h1>
    <div class="about-text" style="margin: 15px; font-size:26px; font-style:italic;"><?php _e("Nifty Backups is your complete backup and restore plugin","sola"); ?></div>
    <hr />
    <h2 style="font-size: 25px;"><?php _e("How did you find us?","sola"); ?></h2>
    <form method="post" name="nifty_bu_find_us_form" style="font-size: 16px;">
        <div  style="text-align: left; width:275px;">
            <input type="radio" name="nifty_bu_find_us" id="wordpress" value='repository'>
            <label for="wordpress">
                <?php _e('WordPress.org plugin repository ', 'nifty-backups'); ?>
            </label>
            <br/>
            <input type='text' placeholder="<?php _e('Search Term', 'nifty-backups'); ?>" name='nifty_bu_nl_search_term' style='margin-top:5px; margin-left: 23px; width: 100%  '>
            <br/>
            <input type="radio" name="nifty_bu_find_us" id="search_engine" value='search_engine'>
            <label for="search_engine">
                <?php _e('Google or other search Engine', 'nifty-backups'); ?>
            </label>
            <br/>
            <input type="radio" name="nifty_bu_find_us" id="friend" value='friend'>
            
            <label for='friend'>
                <?php _e('Friend recommendation', 'nifty-backups'); ?>
            </label>
            <br/>   
            <input type="radio" name="nifty_bu_find_us" id='other' value='other'>
            
            <label for='other'>
                <?php _e('Other', 'nifty-backups'); ?>
            </label>
            <br/>
            
            <textarea placeholder="<?php _e('Please Explain', 'nifty-backups'); ?>" style='margin-top:5px; margin-left: 23px; width: 100%' name='nifty_bu_nl_findus_other_url'></textarea>
        </div>
        <div>
            
        </div>
        <div>
            
        </div>
        <div style='margin-top: 20px;'>
            <button name='action' value='nifty_bu_submit_find_us' class="button-primary" style="font-size: 30px; line-height: 60px; height: 60px; margin-bottom: 10px;"><?php _e('Submit', 'nifty-backups'); ?></button>
            <br/>
            <button name='action' value="nifty_bu_skip_find_us" class="button"><?php _e('Skip', 'nifty-backups'); ?></button>
        </div>
    </form> 
</center>

