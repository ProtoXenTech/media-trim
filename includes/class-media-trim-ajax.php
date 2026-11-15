<?php
/**
 * Media Trim AJAX Handlers.
 *
 * @package Media_Trim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Trim_Ajax
 */
class Media_Trim_Ajax {

	/**
	 * Core instance.
	 *
	 * @var Media_Trim
	 */
	private static $core;

	/**
	 * Initialize AJAX listeners.
	 *
	 * @param Media_Trim $core Core instance.
	 */
	public static function init( $core ) {
		self::$core = $core;

		add_action( 'wp_ajax_mtrim_get_scan_overview', array( __CLASS__, 'ajax_get_scan_overview' ) );
		add_action( 'wp_ajax_mtrim_scan_batch', array( __CLASS__, 'ajax_scan_batch' ) );
		add_action( 'wp_ajax_mtrim_quarantine_items', array( __CLASS__, 'ajax_quarantine_items' ) );
		add_action( 'wp_ajax_mtrim_restore_items', array( __CLASS__, 'ajax_restore_items' ) );
		add_action( 'wp_ajax_mtrim_delete_items', array( __CLASS__, 'ajax_delete_items' ) );
		add_action( 'wp_ajax_mtrim_toggle_whitelist', array( __CLASS__, 'ajax_toggle_whitelist' ) );
	}

	/**
	 * Check security nonce and capability.
	 */
	private static function verify_auth() {
		check_ajax_referer( 'mtrim_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized user.', 'media-trim' ) ), 403 );
		}
	}

	/**
	 * Return total attachments to prepare client for chunked scan.
	 */
	public static function ajax_get_scan_overview() {
		self::verify_auth();
		$total       = Media_Trim_Scanner::get_total_attachments();
		$quarantined = Media_Trim_Quarantine::get_quarantined_count();

		wp_send_json_success(
			array(
				'total'       => $total,
				'quarantined' => $quarantined,
				'batch_size'  => 50,
			)
		);
	}

	/**
	 * Scan a single batch of 50 attachments and stream back analysis.
	 */
	public static function ajax_scan_batch() {
		self::verify_auth();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50;

		$batch_ids = Media_Trim_Scanner::get_attachment_batch( $offset, $limit );
		$results   = array();

		foreach ( $batch_ids as $id ) {
			$results[] = Media_Trim_Scanner::inspect_attachment( $id );
		}

		wp_send_json_success(
			array(
				'count'   => count( $results ),
				'results' => $results,
			)
		);
	}

	/**
	 * Move selected attachments to quarantine.
	 */
	public static function ajax_quarantine_items() {
		self::verify_auth();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();

		if ( empty( $raw_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No items specified.', 'media-trim' ) ) );
		}

		$success_count = 0;
		foreach ( $raw_ids as $id ) {
			if ( Media_Trim_Quarantine::move_to_quarantine( $id ) ) {
				$success_count++;
			}
		}

		wp_send_json_success(
			array(
				'quarantined_count' => $success_count,
				'total_quarantined' => Media_Trim_Quarantine::get_quarantined_count(),
			)
		);
	}

	/**
	 * Restore selected items from quarantine.
	 */
	public static function ajax_restore_items() {
		self::verify_auth();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();

		if ( empty( $raw_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No items specified.', 'media-trim' ) ) );
		}

		$restored_count = 0;
		foreach ( $raw_ids as $id ) {
			if ( Media_Trim_Quarantine::restore_from_quarantine( $id ) ) {
				$restored_count++;
			}
		}

		wp_send_json_success(
			array(
				'restored_count'    => $restored_count,
				'total_quarantined' => Media_Trim_Quarantine::get_quarantined_count(),
			)
		);
	}

	/**
	 * Permanently delete items from quarantine.
	 */
	public static function ajax_delete_items() {
		self::verify_auth();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();

		if ( empty( $raw_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No items specified.', 'media-trim' ) ) );
		}

		$deleted_count = 0;
		foreach ( $raw_ids as $id ) {
			if ( Media_Trim_Quarantine::permanently_delete( $id ) ) {
				$deleted_count++;
			}
		}

		wp_send_json_success(
			array(
				'deleted_count'     => $deleted_count,
				'total_quarantined' => Media_Trim_Quarantine::get_quarantined_count(),
			)
		);
	}

	/**
	 * Toggle whitelist status of an attachment.
	 */
	public static function ajax_toggle_whitelist() {
		self::verify_auth();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id        = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$whitelist = (array) get_option( 'media_trim_whitelist', array() );

		if ( in_array( $id, $whitelist, true ) ) {
			$whitelist = array_diff( $whitelist, array( $id ) );
			$status    = false;
		} else {
			$whitelist[] = $id;
			$status      = true;
		}

		update_option( 'media_trim_whitelist', array_values( array_unique( $whitelist ) ) );

		wp_send_json_success(
			array(
				'whitelisted' => $status,
				'id'          => $id,
			)
		);
	}
}
