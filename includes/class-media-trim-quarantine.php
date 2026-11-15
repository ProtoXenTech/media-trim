<?php
/**
 * Media Trim Quarantine & Recovery Subsystem.
 *
 * @package Media_Trim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Trim_Quarantine
 */
class Media_Trim_Quarantine {

	/**
	 * Custom post status for quarantined attachments.
	 */
	const STATUS = 'mtrim-quarantine';

	/**
	 * Initialize custom post status.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_quarantine_status' ) );
	}

	/**
	 * Register private quarantine post status.
	 */
	public static function register_quarantine_status() {
		register_post_status(
			self::STATUS,
			array(
				'label'                     => _x( 'Quarantined', 'post status', 'media-trim' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				/* translators: %s: number of quarantined items */
				'label_count'               => _n_noop( 'Quarantined <span class="count">(%s)</span>', 'Quarantined <span class="count">(%s)</span>', 'media-trim' ),
			)
		);
	}

	/**
	 * Get absolute path to the quarantine directory in uploads.
	 *
	 * @return string
	 */
	public static function get_quarantine_dir() {
		$upload_dir = wp_upload_dir();
		$quarantine = trailingslashit( $upload_dir['basedir'] ) . 'media-trim-quarantine';

		if ( ! file_exists( $quarantine ) ) {
			wp_mkdir_p( $quarantine );
			// Write security index.php
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( trailingslashit( $quarantine ) . 'index.php', '<?php // Silence is golden.' );
			// Write security .htaccess
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( trailingslashit( $quarantine ) . '.htaccess', "Deny from all\n" );
		}

		return $quarantine;
	}

	/**
	 * Move an attachment and all its intermediate sizes to the quarantine directory.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function move_to_quarantine( $attachment_id ) {
		$main_file = get_attached_file( $attachment_id );
		if ( ! $main_file || ! file_exists( $main_file ) ) {
			return false;
		}

		$quarantine_dir = self::get_quarantine_dir();
		$upload_dir     = wp_upload_dir();
		$base_dir       = trailingslashit( $upload_dir['basedir'] );

		// Compute relative path
		$rel_path    = str_replace( $base_dir, '', $main_file );
		$target_file = trailingslashit( $quarantine_dir ) . $rel_path;
		$target_dir  = dirname( $target_file );

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		$moved_files = array();

		// Move primary attached file
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( rename( $main_file, $target_file ) ) {
			$moved_files[ $target_file ] = $main_file;
		} else {
			return false;
		}

		// Move all intermediate thumbnail sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			$file_dir = dirname( $main_file );
			foreach ( $metadata['sizes'] as $size => $data ) {
				if ( ! empty( $data['file'] ) ) {
					$thumb_source = trailingslashit( $file_dir ) . $data['file'];
					$thumb_target = trailingslashit( $target_dir ) . $data['file'];
					if ( file_exists( $thumb_source ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
						if ( rename( $thumb_source, $thumb_target ) ) {
							$moved_files[ $thumb_target ] = $thumb_source;
						}
					}
				}
			}
		}

		// Save restoration manifest in postmeta
		update_post_meta( $attachment_id, '_mtrim_quarantine_manifest', $moved_files );
		update_post_meta( $attachment_id, '_mtrim_original_file', $main_file );

		// Update post status to quarantine
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_status' => self::STATUS,
			)
		);

		return true;
	}

	/**
	 * Restore a quarantined attachment back to its original location.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function restore_from_quarantine( $attachment_id ) {
		$manifest = get_post_meta( $attachment_id, '_mtrim_quarantine_manifest', true );
		if ( ! is_array( $manifest ) || empty( $manifest ) ) {
			return false;
		}

		foreach ( $manifest as $quarantine_path => $original_path ) {
			if ( file_exists( $quarantine_path ) ) {
				$dest_dir = dirname( $original_path );
				if ( ! file_exists( $dest_dir ) ) {
					wp_mkdir_p( $dest_dir );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $quarantine_path, $original_path );
			}
		}

		// Clean quarantine meta
		delete_post_meta( $attachment_id, '_mtrim_quarantine_manifest' );
		delete_post_meta( $attachment_id, '_mtrim_original_file' );

		// Revert post status to 'inherit'
		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_status' => 'inherit',
			)
		);

		return true;
	}

	/**
	 * Permanently delete a quarantined attachment from disk and database.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function permanently_delete( $attachment_id ) {
		$manifest = get_post_meta( $attachment_id, '_mtrim_quarantine_manifest', true );
		if ( is_array( $manifest ) ) {
			foreach ( $manifest as $quarantine_path => $original_path ) {
				if ( file_exists( $quarantine_path ) ) {
					wp_delete_file( $quarantine_path );
				}
			}
		}

		// Delete from database
		return (bool) wp_delete_post( $attachment_id, true );
	}

	/**
	 * Get total count of currently quarantined attachments.
	 *
	 * @return int
	 */
	public static function get_quarantined_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = %s",
				self::STATUS
			)
		);
		return absint( $count );
	}

	/**
	 * Get list of currently quarantined attachments with metadata.
	 *
	 * @return array
	 */
	public static function get_quarantined_items() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => self::STATUS,
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);

		$items = array();
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $id ) {
				$orig_file = get_post_meta( $id, '_mtrim_original_file', true );
				$items[]   = array(
					'id'       => $id,
					'filename' => $orig_file ? wp_basename( $orig_file ) : sprintf( 'Asset #%d', $id ),
					'date'     => get_the_date( 'Y-m-d H:i', $id ),
				);
			}
		}

		return $items;
	}
}
