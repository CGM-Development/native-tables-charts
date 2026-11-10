<?php
/**
 * Plugin Name: Native Tables & Charts
 * Description: Gutenberg-native responsive tables, charts, reusable datasets, presets, and League Table migration.
 * Version: 3.2.1
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: CGMagazine
 * Text Domain: native-tables-charts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NTC_VERSION', '3.2.1' );
define( 'NTC_FILE', __FILE__ );
define( 'NTC_DIR', plugin_dir_path( __FILE__ ) );
define( 'NTC_URL', plugin_dir_url( __FILE__ ) );

require_once NTC_DIR . 'includes/class-ntc-activator.php';
require_once NTC_DIR . 'includes/class-ntc-repository.php';
require_once NTC_DIR . 'includes/class-ntc-formulas.php';
require_once NTC_DIR . 'includes/class-ntc-sync.php';
require_once NTC_DIR . 'includes/class-ntc-posts-query.php';
require_once NTC_DIR . 'includes/class-ntc-advanced-charts.php';
require_once NTC_DIR . 'includes/class-ntc-renderer.php';
require_once NTC_DIR . 'includes/class-ntc-rest.php';
require_once NTC_DIR . 'includes/class-ntc-admin.php';
require_once NTC_DIR . 'includes/class-ntc-migrator.php';
require_once NTC_DIR . 'includes/class-ntc-plugin.php';

register_activation_hook( __FILE__, array( 'NTC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NTC_Activator', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'NTC_Activator', 'uninstall' ) );

add_action(
	'plugins_loaded',
	static function () {
		NTC_Plugin::instance()->boot();
	}
);
