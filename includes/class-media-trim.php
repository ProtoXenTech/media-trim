<?php
/**
 * Master Orchestrator for Media Trim.
 *
 * @package Media_Trim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Trim
 */
class Media_Trim {

	/**
	 * Singleton instance.
	 *
	 * @var Media_Trim|null
	 */
	private static $instance = null;

	/**
	 * Active settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private $defaults = array(
		'scan_batch_size'      => 50,
		'check_featured'       => true,
		'check_content'        => true,
		'check_theme_mods'     => true,
		'check_woo_galleries'  => true,
		'check_page_builders'  => true,
	);

	/**
	 * Get singleton instance.
	 *
	 * @return Media_Trim
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_settings();
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load settings from wp_options.
	 */
	private function load_settings() {
		$saved          = get_option( 'media_trim_settings', array() );
		$this->settings = wp_parse_args( is_array( $saved ) ? $saved : array(), $this->defaults );
	}

	/**
	 * Retrieve a setting.
	 *
	 * @param string|null $key Key.
	 * @param mixed       $default Default.
	 * @return mixed
	 */
	public function get_setting( $key = null, $default = null ) {
		if ( null === $key ) {
			return $this->settings;
		}

		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Get defaults.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return $this->defaults;
	}

	/**
	 * Require plugin submodules.
	 */
	private function load_dependencies() {
		require_once MEDIA_TRIM_PATH . 'includes/class-media-trim-scanner.php';
		require_once MEDIA_TRIM_PATH . 'includes/class-media-trim-quarantine.php';

		if ( is_admin() ) {
			require_once MEDIA_TRIM_PATH . 'includes/class-media-trim-ajax.php';
			require_once MEDIA_TRIM_PATH . 'includes/class-media-trim-admin.php';
		}
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks() {
		Media_Trim_Quarantine::init();

		if ( is_admin() ) {
			Media_Trim_Admin::init( $this );
			Media_Trim_Ajax::init( $this );
		}
	}
}
