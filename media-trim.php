<?php
/**
 * Plugin Name:       Media Trim
 * Plugin URI:        https://protoxen.com/media-trim/
 * Description:       Ultra-safe, high-performance unused media detector, quarantine manager, and disk space optimizer for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ProtoXen
 * Author URI:        https://protoxen.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       media-trim
 * Domain Path:       /languages
 *
 * @package           Media_Trim
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'MEDIA_TRIM_VERSION', '1.0.0' );

/**
 * Plugin directory path with trailing slash.
 */
define( 'MEDIA_TRIM_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL with trailing slash.
 */
define( 'MEDIA_TRIM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin basename.
 */
define( 'MEDIA_TRIM_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load core orchestrator class.
 */
require_once MEDIA_TRIM_PATH . 'includes/class-media-trim.php';

/**
 * Initialize Media Trim on plugins_loaded.
 */
function media_trim_run() {
	Media_Trim::get_instance();
}
add_action( 'plugins_loaded', 'media_trim_run' );
