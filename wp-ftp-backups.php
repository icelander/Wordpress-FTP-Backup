<?php
/*
Plugin Name: WP S3 Backups
Plugin URI: http://wordpress.org/extend/plugins/wp-s3-backups/
Description: Automatically upload backups of important parts of your blog to Amazon S3
Author: Dan Coulter
Version: 0.3.0
Author URI: http://dancoulter.com/
*/ 

/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * @package wp-s3-backups
 */

if ( !defined('WP_CONTENT_URL') )
	define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if ( !defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	
class WPS3B {
	/**
	 * Uses the init action to catch changes in the schedule and pass those on to the scheduler.
	 *
	 */
	function init() {
		if ( isset($_POST['s3b-schedule']) ) {
			wp_clear_scheduled_hook('s3-backup');
			if ( $_POST['s3b-schedule'] != 'disabled' ) {
				wp_schedule_event(time(), $_POST['s3b-schedule'], 's3-backup');
			}
		}
		if ( isset($_POST['s3-new-bucket']) && !empty($_POST['s3-new-bucket']) ) {
			include_once 'S3.php';
			$_POST['s3-new-bucket'] = strtolower($_POST['s3-new-bucket']);
			$s3 = new S3(get_option('s3b-access-key'), get_option('s3b-secret-key')); 
			$s3->putBucket($_POST['s3-new-bucket']);
			$buckets = $s3->listBuckets();
			if ( is_array($buckets) && in_array($_POST['s3-new-bucket'], $buckets) ) {
				update_option('s3b-bucket', $_POST['s3-new-bucket']);
				$_POST['s3b-bucket'] = $_POST['s3-new-bucket'];
			} else {
				update_option('s3b-bucket', false);
			}
		}
		if ( !get_option('s3b-bucket') ) add_action('admin_notices', array('WPS3B','newBucketWarning'));
	}
	
	function newBucketWarning() {
		echo "
		<div id='s3-warning' class='updated fade'><p><strong>".__('You need to select a valid S3 bucket.', 'wp-s3-backups')."</strong> ".__('If you tried to create a new bucket, it may have been an invalid name.', 'wp-s3-backups')."</p></div>
		";

	}

	/**
	 * Return the filesystem path that the plugin lives in.
	 *
	 * @return string
	 */
	function getPath() {
		return dirname(__FILE__) . '/';
	}
	
	/**
	 * Returns the URL of the plugin's folder.
	 *
	 * @return string
	 */
	function getURL() {
		return WP_CONTENT_URL.'/plugins/'.basename(dirname(__FILE__)) . '/';
	}

	/**
     * Sets up the settings page
     *
     */
	function add_settings_page() {
		load_plugin_textdomain('wp-s3-backups', WPS3B::getPath() . 'i18n');
		add_options_page(__('S3 Backup', 'wp-s3-backups'), __('S3 Backup', 'wp-s3-backups'), 8, 's3-backup', array('WPS3B', 'settings_page'));	
	}
	
	/**
	 * Generates the settings page
	 *
	 */
	function settings_page() {
		include_once 'S3.php';
		$sections = get_option('s3b-section');
		if ( !$sections ) {
			$sections = array();
		}
		?>
			<script type="text/javascript">
				var ajaxTarget = "<?php echo self::getURL() ?>backup.ajax.php";
				var nonce = "<?php echo wp_create_nonce('wp-s3-backups'); ?>";
			</script>
			<div class="wrap">
				<h2><?php _e('S3 Backup', 'wp-s3-backups') ?></h2>
				<form method="post" action="options.php">
					<input type="hidden" name="action" value="update" />
					<?php wp_nonce_field('update-options'); ?>
					<input type="hidden" name="page_options" value="s3b-access-key,s3b-secret-key,s3b-bucket,s3b-section,s3b-schedule" />
					<p>
						<?php _e('AWS Access Key:', 'wp-s3-backups') ?>
						<input type="text" name="s3b-access-key" value="<?php echo get_option('s3b-access-key'); ?>" />
					</p>
					<p>
						<?php _e('AWS Secret Key:', 'wp-s3-backups') ?>
						<input type="text" name="s3b-secret-key" value="<?php echo get_option('s3b-secret-key'); ?>" />
					</p>
					<?php if ( get_option('s3b-access-key') && get_option('s3b-secret-key') ) : ?>
						<?php 
							$s3 = new S3(get_option('s3b-access-key'), get_option('s3b-secret-key')); 
							$buckets = $s3->listBuckets();
						?>
						<p>
							<span style="vertical-align: middle;"><?php _e('S3 Bucket Name:', 'wp-s3-backups') ?></span>
							<select name="s3b-bucket">
								<?php foreach ( $buckets as $b ) : ?>
									<option <?php if ( $b == get_option('s3b-bucket') ) echo 'selected="selected"' ?>><?php echo $b ?></option>
								<?php endforeach; ?>
							</select>
							
							<br />
							<span style="vertical-align: middle;"><?php _e('Or create a bucket:', 'wp-s3-backups') ?></span>
							<input type="text" name="s3-new-bucket" id="new-s3-bucket" value="" />
							
						</p>
						<p>
							<span style="vertical-align: middle;"><?php _e('Backup schedule:', 'wp-s3-backups') ?></span>
							<select name="s3b-schedule">
								<?php foreach ( array('Disabled','Daily','Weekly','Monthly') as $s ) : ?>
									<option value="<?php echo strtolower($s) ?>" <?php if ( strtolower($s) == get_option('s3b-schedule') ) echo 'selected="selected"' ?>><?php echo $s ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<?php _e('Parts of your blog to back up', 'wp-s3-backups') ?><br />
							<label for="s3b-section-config">
								<input <?php if ( in_array('config', $sections) ) echo 'checked="checked"' ?> type="checkbox" name="s3b-section[]" value="config" id="s3b-section-config" />
								<?php _e('Config file', 'wp-s3-backups') ?>
							</label><br />
							<label for="s3b-section-database">
								<input <?php if ( in_array('database', $sections) ) echo 'checked="checked"' ?> type="checkbox" name="s3b-section[]" value="database" id="s3b-section-database" />
								<?php _e('Database dump', 'wp-s3-backups') ?>
							</label><br />
							<label for="s3b-section-themes">
								<input <?php if ( in_array('themes', $sections) ) echo 'checked="checked"' ?> type="checkbox" name="s3b-section[]" value="themes" id="s3b-section-themes" />
								<?php _e('Themes folder', 'wp-s3-backups') ?>
							</label><br />
							<label for="s3b-section-plugins">
								<input <?php if ( in_array('plugins', $sections) ) echo 'checked="checked"' ?> type="checkbox" name="s3b-section[]" value="plugins" id="s3b-section-plugins" />
								<?php _e('Plugins folder', 'wp-s3-backups') ?>
							</label><br />
							<?php do_action('s3b_sections') ?>
							<label for="s3b-section-uploads">
								<input <?php if ( in_array('uploads', $sections) ) echo 'checked="checked"' ?> type="checkbox" name="s3b-section[]" value="uploads" id="s3b-section-uploads" />
								<?php _e('Uploaded content', 'wp-s3-backups') ?>
							</label><br />
						</p>
					<?php endif; ?>
					<p class="submit">
						<input type="submit" name="Submit" value="<?php _e('Save Changes', 'wp-s3-backups') ?>" />
					</p>
					

				</form>
				
				<?php //WPS3BU::backup() ?>
				
				<h3>Download recent backups</h3>
				<div id="backups">
					<?php 
						if ( get_option('s3b-bucket') ) {
							$backups = $s3->getBucket(get_option('s3b-bucket'), next(explode('//', get_bloginfo('siteurl'))));
							krsort($backups);
							$count = 0;
							foreach ( $backups as $key => $backup ) {
								$backup['label'] = sprintf(__('WordPress Backup from %s', 'wp-s3-backups'), mysql2date(__('F j, Y h:i a'), date('Y-m-d H:i:s', $backup['time'])));
								
								if ( preg_match('|\.uploads\.zip$|', $backup['name']) ) {
									$backup['label'] = sprintf(__('Uploads Backup from %s', 'wp-s3-backups'), mysql2date(__('F j, Y h:i a'), date('Y-m-d H:i:s', $backup['time'])));
								}
								
								$backup = apply_filters('s3b-backup-item', $backup);
								
								if ( ++$count > 20 ) break;
								?>
									<div class="backup"><a href="<?php echo $s3->getObjectURL(get_option('s3b-bucket'), $backup['name']) ?>"><?php echo $backup['label'] ?></a></div>
								<?php
							}
						}
					?>
					<div class="backup">
					</div>
				</div>
				
			</div>
		<?php
	}
	
	function rscandir($base='') {
		$data = array_diff(scandir($base), array('.', '..'));
	
		$subs = array();
		foreach($data as $key => $value) :
			if ( is_dir($base . '/' . $value) ) :
				unset($data[$key]);
				$subs[] = WPS3B::rscandir($base . '/' . $value);
			elseif ( is_file($base . '/' . $value) ) :
				$data[$key] = $base . '/' . $value;
			endif;
		endforeach;
	
		foreach ( $subs as $sub ) {
			$data = array_merge($data, $sub);
		}
		return $data;
	}
	
	function backup() {
		global $wpdb;
		require_once('S3.php');
		require_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');
		
		$sections = get_option('s3b-section');
		if ( !$sections ) {
			$sections = array();
		}
		
		$file = WP_CONTENT_DIR . '/uploads/wp-s3-backups.zip';
		$zip = new PclZip($file);
		$backups = array();
		if ( in_array('config', $sections) ) $backups[] = ABSPATH . '/wp-config.php';
		if ( in_array('database', $sections) ) {
			$tables = $wpdb->get_col("SHOW TABLES LIKE '" . $wpdb->prefix . "%'");
			$result = shell_exec('mysqldump --single-transaction -h ' . DB_HOST . ' -u ' . DB_USER . ' --password="' . DB_PASSWORD . '" ' . DB_NAME . ' ' . implode(' ', $tables) . ' > ' .  WP_CONTENT_DIR . '/uploads/wp-s3-database-backup.sql');
			$backups[] = WP_CONTENT_DIR . '/uploads/wp-s3-database-backup.sql';
		}
		if ( in_array('themes', $sections) ) $backups = array_merge($backups, WPS3B::rscandir(ABSPATH . 'wp-content/plugins'));
		if ( in_array('plugins', $sections) ) $backups = array_merge($backups, WPS3B::rscandir(ABSPATH . 'wp-content/themes'));
		//if ( in_array('uploads', $sections) ) $backups = array_merge($backups, WPS3B::rscandir(ABSPATH . 'wp-content/uploads'));
		
		if ( !empty($backups) ) {
			$zip->create($backups, '', ABSPATH);
			
			$s3 = new S3(get_option('s3b-access-key'), get_option('s3b-secret-key')); 
			$upload = $s3->inputFile($file);
			$s3->putObject($upload, get_option('s3b-bucket'), next(explode('//', get_bloginfo('siteurl'))) . '/' . date('Y-m-d') . '.zip');
			@unlink($file);
			@unlink(WP_CONTENT_DIR . '/uploads/wp-s3-database-backup.sql');
		}
		
		$cwd = getcwd();
		chdir(WP_CONTENT_DIR);
		if ( in_array('uploads', (array) $sections) ) {
			$file = 'uploads/wp-s3-file-backups.zip';
			$result = shell_exec('zip -r ' . $file . ' uploads');
			$s3 = new S3(get_option('s3b-access-key'), get_option('s3b-secret-key')); 
			$upload = $s3->inputFile($file);
			$s3->putObject($upload, get_option('s3b-bucket'), next(explode('//', get_bloginfo('siteurl'))) . '/' . date('Y-m-d') . '.uploads.zip');
			@unlink($file);
		}
		chdir($cwd);

	}
	
	function cron_schedules($schedules) {
		$schedules['weekly'] = array('interval'=>604800, 'display' => 'Once Weekly');
		$schedules['monthly'] = array('interval'=>2592000, 'display' => 'Once Monthly');
		return $schedules;
	}
}

add_filter('cron_schedules', array('WPS3B', 'cron_schedules'));
add_action('admin_menu', array('WPS3B', 'add_settings_page'));
add_action('s3-backup', array('WPS3B', 'backup'));
add_action('init', array('WPS3B', 'init'));

if ( $_GET['page'] == 's3-backup' ) {
	wp_enqueue_script('jquery');
}
?>
