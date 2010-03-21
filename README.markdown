# Wordpress FTP Backup #

Based on the fabulous [Wordpress S3 Backup](http://wordpress.org/extend/plugins/wp-s3-backups/ "Wordpress S3 Backup").

## Description ##

Using this plugin, you can easily and automatically backup important parts of
your WordPress install to any FTP server.

Important caveat: this plugin currently has to be run on a linux server. 
Also, the wp-content/uploads folder has to be server-writable or it won't be
able to create the zips for backup.