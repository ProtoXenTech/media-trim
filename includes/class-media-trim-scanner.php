<?php
/**
 * Media Trim Reference Scanner Engine.
 *
 * @package Media_Trim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Trim_Scanner
 */
class Media_Trim_Scanner {

	/**
	 * Get total count of registered attachments (excluding quarantined ones).
	 *
	 * @return int
	 */
	public static function get_total_attachments() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'mtrim-quarantine'"
		);
		return absint( $count );
	}

	/**
	 * Retrieve a slice of attachment IDs for chunked scanning.
	 *
	 * @param int $offset Offset.
	 * @param int $limit Batch limit.
	 * @return array
	 */
	public static function get_attachment_batch( $offset = 0, $limit = 50 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'mtrim-quarantine' ORDER BY ID ASC LIMIT %d OFFSET %d",
				absint( $limit ),
				absint( $offset )
			)
		);
		return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
	}

	/**
	 * Scan an individual attachment to determine if it is currently in use.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Result metadata { 'is_used' => bool, 'reason' => string, 'filesize' => int, 'filename' => string, 'thumb' => string }
	 */
	public static function inspect_attachment( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		$filename  = $file_path ? wp_basename( $file_path ) : '';
		$filesize  = ( $file_path && file_exists( $file_path ) ) ? (int) filesize( $file_path ) : 0;
		$thumb_url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		// 1. Check User Whitelist
		$whitelist = (array) get_option( 'media_trim_whitelist', array() );
		if ( in_array( $attachment_id, $whitelist, true ) ) {
			return array(
				'id'       => $attachment_id,
				'is_used'  => true,
				'reason'   => __( 'Whitelisted by user', 'media-trim' ),
				'filename' => $filename,
				'filesize' => $filesize,
				'thumb'    => $thumb_url,
			);
		}

		// 2. Check Site Identity & Theme Mods (Logo, Icon, Header, Background)
		if ( self::is_used_in_theme_mods( $attachment_id ) ) {
			return array(
				'id'       => $attachment_id,
				'is_used'  => true,
				'reason'   => __( 'Site Logo / Theme Asset', 'media-trim' ),
				'filename' => $filename,
				'filesize' => $filesize,
				'thumb'    => $thumb_url,
			);
		}

		// 3. Check Featured Post Thumbnails (_thumbnail_id)
		if ( self::is_used_as_featured_image( $attachment_id ) ) {
			return array(
				'id'       => $attachment_id,
				'is_used'  => true,
				'reason'   => __( 'Featured Image', 'media-trim' ),
				'filename' => $filename,
				'filesize' => $filesize,
				'thumb'    => $thumb_url,
			);
		}

		// 4. Check WooCommerce Product Galleries
		if ( self::is_used_in_woocommerce_gallery( $attachment_id ) ) {
			return array(
				'id'       => $attachment_id,
				'is_used'  => true,
				'reason'   => __( 'WooCommerce Product Gallery', 'media-trim' ),
				'filename' => $filename,
				'filesize' => $filesize,
				'thumb'    => $thumb_url,
			);
		}

		// 5. Check Content & Blocks (wp_posts.post_content)
		if ( self::is_used_in_content( $attachment_id, $filename ) ) {
			return array(
				'id'       => $attachment_id,
				'is_used'  => true,
				'reason'   => __( 'Post / Page Content', 'media-trim' ),
				'filename' => $filename,
				'filesize' => $filesize,
				'thumb'    => $thumb_url,
			);
		}

		// 6. Check Page Builders (Elementor JSON metadata)
		if ( self::is_used_in_page_builders( $attachment_id ) ) {
			return array(
				'id'       => $attachment_id,
				'is_used'  => true,
				'reason'   => __( 'Page Builder Layout (Elementor)', 'media-trim' ),
				'filename' => $filename,
				'filesize' => $filesize,
				'thumb'    => $thumb_url,
			);
		}

		// If no references found, flag as unused
		return array(
			'id'       => $attachment_id,
			'is_used'  => false,
			'reason'   => __( 'No references found', 'media-trim' ),
			'filename' => $filename,
			'filesize' => $filesize,
			'thumb'    => $thumb_url,
		);
	}

	/**
	 * Check if image is site logo, favicon, header or background image.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	private static function is_used_in_theme_mods( $id ) {
		// Custom logo
		if ( absint( get_theme_mod( 'custom_logo' ) ) === $id ) {
			return true;
		}
		// Site icon / Favicon
		if ( absint( get_option( 'site_icon' ) ) === $id ) {
			return true;
		}
		// Header image
		$header_data = get_custom_header();
		if ( ! empty( $header_data->attachment_id ) && absint( $header_data->attachment_id ) === $id ) {
			return true;
		}
		// Background image
		$bg_image_url = get_background_image();
		if ( ! empty( $bg_image_url ) ) {
			$attached_url = wp_get_attachment_url( $id );
			if ( $attached_url && $attached_url === $bg_image_url ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if image is assigned as a featured image to any post.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	private static function is_used_as_featured_image( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
				$id
			)
		);
		return ! empty( $found );
	}

	/**
	 * Check if image is used in WooCommerce product galleries.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	private static function is_used_in_woocommerce_gallery( $id ) {
		global $wpdb;
		// Comma-separated check inside _product_image_gallery
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND FIND_IN_SET(%d, meta_value) LIMIT 1",
				$id
			)
		);
		return ! empty( $found );
	}

	/**
	 * Check if attachment is referenced in post_content (Blocks or Classic).
	 *
	 * @param int    $id Attachment ID.
	 * @param string $filename Basename.
	 * @return bool
	 */
	private static function is_used_in_content( $id, $filename ) {
		global $wpdb;

		// 1. Check ID reference in blocks or classes (wp-image-ID or "id":ID)
		$id_like = '%wp-image-' . $wpdb->esc_like( (string) $id ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_by_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish', 'future', 'draft', 'private') AND post_type != 'attachment' AND post_content LIKE %s LIMIT 1",
				$id_like
			)
		);

		if ( ! empty( $found_by_id ) ) {
			return true;
		}

		// 2. Check filename in content if available
		if ( ! empty( $filename ) ) {
			$name_like = '%' . $wpdb->esc_like( $filename ) . '%';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found_by_name = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('publish', 'future', 'draft', 'private') AND post_type != 'attachment' AND post_content LIKE %s LIMIT 1",
					$name_like
				)
			);
			if ( ! empty( $found_by_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if attachment is referenced in Elementor or page builder meta.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	private static function is_used_in_page_builders( $id ) {
		global $wpdb;

		// Search inside _elementor_data JSON strings
		$builder_like = '%"id":' . $wpdb->esc_like( (string) $id ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s LIMIT 1",
				$builder_like
			)
		);

		return ! empty( $found );
	}
}
