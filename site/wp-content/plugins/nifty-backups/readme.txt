=== Backup WordPress with Nifty Backups ===
Contributors: NickDuncan
Donate link: http://niftybackups.com
Tags: backup plugin, backup, backups, wordpress backup plugin, backups, plugin, backup tables, backup database, wordpress backup, file backup, file restore, website backup, site backup, backup to email, website backup plugin, backup schedule, cloud backup, plugin backup, content backup
Requires at least: 3.8
Tested up to: 4.7
Stable tag: trunk
License: GPLv3

Fully functional free backup plugin for WordPress. Backup and restore your database tables and WordPress files quickly, easily and reliably.

== Description ==

Fully functional free backup plugin for WordPress. Backup and restore your database tables and WordPress files quickly, easily and reliably.

= Features =

* Backup your database tables
* Backup your files
* Smart backup process - no performance issues
* Smart restore process
* Automatically Send backups to email when complete
* World-class support should you need it
* Choose between backing up files, database or both
* Send backup notifications via email
* Choose how many rows you would like to backup per backup iteration
* Choose how many files you would like to backup per backup iteration

= Pro Version =
* Stream your backup files to Google Drive. Dropbox coming soon!
* Stream your backup files to a FTP server
* Schedule backups by day, week or month
* Select tables to ignore when backing up the database
* Select to ignore certain file types when running a backup
* Select to ignore file over a certain size

Our backup plugin instantly gives you the ability to backup your files and database quickly and easily without any technical know-how. Our plugin will run through your tables and files and intelligently process a few manageable chunks at a time. This means that your site's speed and performance will not be negatively affected when a backup is in progress.

= Translations =
Get a free copy of one of [Nifty Backups Pro](http://niftybackups.com/) in exchange for translating our plugin!

* English

= Backup files sent via email =
The free version of the plugin allows you to send backup files via email, so long as the file is under 20mb in size.

= Backup files sent to Google Drive =
The pro version of Nifty Backups allows you to stream your backup files to Google Drive, within a "Nifty Backups" folder.

= Backup files sent to FTP =
Automatically send your backup files to a FTP server with Nifty Backups Pro.

= Backup Scheduling =
Schedule backups with the Pro version of Nifty Backups - Choose to backup your database or files daily, weekly or monthly at certain times.

= Smart backup process =
Our backup plugin breaks the backup process down into manageable chunks. This means that with each iteration of the backup loop, a portion of your site will be backed up. This ensures that no timeouts occur and that users on the front end are not negatively affected by the backup process. When done, an email is sent to the email(s) you have specified.

= Nifty Backup Pro =
Nifty Backups allows you to backup and restore your website, both database and files, quickly and easily with peace of mind. The free version also allows you to download your backups and/or send it to an email address. The pro version however, allows you to do so much more. For a once off payment of $19.99 [Nifty Backups Pro](http://niftybackups.com/) you will add backup scheduling, FTP streaming, Google Drive streaming, advanced backup options and more!

= Coming soon =
* Incremental backups - the backup plugin will create an initial backup of your files and database and then incrementally add whatever has changed on your server to the original backup. This means that we will be able to backup your site in an instant without having to wait for a lengthy file backup to complete.
* Encrypted backups - Within the next month (Dec'16) we will be adding backup encryption to our plugin via a secure two way encryption.
* Dropbox - Dropbox streaming will be available in Nov'16
* Amazon - Amazon streaming will be available in Dec'16
* File/Folder selection - Advanced file and folder selection will be available in Dec'16 - this will allow you to only backup certain folder or files
* Website change detection - We will be releasing an update to the pro version that will allow the backup plugin to identify whether your site has changed to a certain degree (5% or more) and then automatically backup your site

= Backup Best Practice =
When backing up your data, it is essential to use more than one backup solution. Although we have invested a lot of time and effort into making the backup and restore process as efficient and bullet-proof as possible, we encourage you to use more than one solution over and above a backup plugin. However, we do not encourage you to use two backup plugins but rather one backup plugin and one external backup service. This will ensure that your important data is well looked after. After all, two backups are better than one!



== Installation ==

1. Once activated, click the "Backups" link under your settings tabs in the left navigation menu of the backend.
2. Click the "Backup now" button
3. Sit back and relax while the plugin does all the hard work for you

== Frequently Asked Questions ==

For more information, please see [niftybackups.com(http://niftybackups.com/)

== Screenshots ==

1. Nifty Backup Plugin - Dashboard
2. Nifty Backup Plugin - Settings page (basic version)
3. Nifty Backup Plugin - Restore page
4. Nifty Backup Plugin - Backup page (pre-backup information)

== Upgrade Notice ==

Nothing here yet.

== Changelog ==

= 1.08 - 2016-11-25 =
* Added text domain and the first language pack (English)
* Stopped emails from being sent out incorrectly when the zipping of files fails
* Changed default backup table rows from 200 to 2000
* Added the correct filter to allow for HTML emails
* Added more stringent checks when zipping a file

= 1.07 - 2016-11-24 =
* Fixed a bug where we referenced $this incorrectly

= 1.06 - 2016-11-21 =
* Added a welcome page

= 1.05 - 2016-11-16 =
* Added "send backup files to email" as an offsite backup option
* Added comprehensive email notifications with a customisable backup email template
* Removed default verbose logging during backups
* Fixed PHP warning bugs

= 1.04 - 2016-11-14 =
* Added a link to a tutorial for users experiencing the "ZipArchive" module not found warning
 
= 1.03 =
* Modifications to the buttons
* Added integrity checking to the DB sequence
* Changed the style of the UI
 
= 1.02 =
* Maintenance mode now introduced when restoring a backup
* Added functionality to ignore backing up nifty system files
* The backup now automatically ignores "Thumbs.db" which causes issues on windows systems
* You now get notified via email when a backup is complete and when a restore is complete

= 1.01 =
* Added integrity checks for all files that have been backed up. The system now checks and makes sure it has backed up all the files it wanted to backup originally. If it cannot backup a file for any reason, it will display a list of all the files that couldnt be zipped

= 1.00 - 2016-11-13 =
* Launch

== Features ==
= Backup best practices =
Your data is worth a lot to you. Why risk all your sales records or user information? Data can easily be lost through via a variety of ways such as server crashes, user errors and hosting company errors. More importantly, hackers are targeting WordPress more and more lately due to a large number of plugin and theme exploits. How confident are you that your data is safe?

Here are some basic steps you can take to ensure your data is kept safe and able to be restored should the dreaded day ever come:

= Backup Schedule =
Make sure you create a backup schedule. Use the automated systems that you already have at your disposal. All cPanel installations come with backup software. Create a backup schedule and get email reports to make sure you are kept in the loop as to when a backup took place, or if there were any errors during the backup.

= Use multiple offsite backup servers =
Store your data on multiple servers. Don’t keep your backups in one place and whatever you do, do not just keep your backup files on your local server. Use services such as Google Drive and Dropbox to store backup files.

= Backup both your database and files =
WordPress uses MySQL and files to create and display your website to your visitors. Make sure you backup both the database and important files (wp-content folder mostly).

= Use more than one backup provider =
Use more than one backup provider. I cannot stress this enough. Using only one backup solution is not good enough. Things go wrong. Software is not perfect. This is exactly the reason as to why I created Nifty Backups, which I use in conjunction with cPanel backups.

= Efficient backup time delay =
Ensure that you create an efficient delay between backups for your type of business. If you are recording daily purchases on your website, then a daily backup would be recommended. Make sure to purge older backups so as to not take up too much space.

Your data is super important to you. Make sure you are covered!