<?php
/**
 * Media Trim Admin Page.
 *
 * @package Media_Trim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Media_Trim_Admin
 */
class Media_Trim_Admin {

	/**
	 * Core instance.
	 *
	 * @var Media_Trim
	 */
	private static $core;

	/**
	 * Initialize admin page.
	 *
	 * @param Media_Trim $core Core instance.
	 */
	public static function init( $core ) {
		self::$core = $core;

		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . MEDIA_TRIM_BASENAME, array( __CLASS__, 'add_action_links' ) );
	}

	/**
	 * Register Tools sub-menu.
	 */
	public static function register_admin_menu() {
		add_management_page(
			esc_html__( 'Media Trim - Unused Media Cleaner', 'media-trim' ),
			esc_html__( 'Media Trim', 'media-trim' ),
			'manage_options',
			'media-trim',
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Add direct links to Plugins table.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function add_action_links( $links ) {
		$tool_url  = admin_url( 'tools.php?page=media-trim' );
		$tool_link = '<a href="' . esc_url( $tool_url ) . '">' . esc_html__( 'Open Scanner', 'media-trim' ) . '</a>';
		array_unshift( $links, $tool_link );
		return $links;
	}

	/**
	 * Enqueue admin styles and scripts strictly on Tools page.
	 *
	 * @param string $hook Admin hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'tools_page_media-trim' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'media-trim-admin',
			MEDIA_TRIM_URL . 'admin/css/admin.css',
			array(),
			MEDIA_TRIM_VERSION
		);

		wp_enqueue_script(
			'media-trim-admin',
			MEDIA_TRIM_URL . 'admin/js/admin.js',
			array(),
			MEDIA_TRIM_VERSION,
			true
		);

		wp_localize_script(
			'media-trim-admin',
			'mediaSweepVars',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'mtrim_admin_nonce' ),
				'i18n'      => array(
					'scanning'         => esc_html__( 'Scanning media library...', 'media-trim' ),
					'scanComplete'     => esc_html__( 'Scan complete!', 'media-trim' ),
					'noUnused'         => esc_html__( 'Great job! No unused media files detected.', 'media-trim' ),
					'quarantineConfirm'=> esc_html__( 'Move selected files to safe quarantine? They will not be deleted permanently.', 'media-trim' ),
					'deleteConfirm'    => esc_html__( 'Permanently delete selected files? This action CANNOT be undone.', 'media-trim' ),
					'restoreConfirm'   => esc_html__( 'Restore selected files back to their original upload folders?', 'media-trim' ),
				),
			)
		);
	}

	/**
	 * Render Media Trim Studio Dashboard.
	 */
	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$icon_url    = MEDIA_TRIM_URL . 'admin/images/icon-128x128.png';
		$logo_url    = MEDIA_TRIM_URL . 'admin/images/protoxen-logo.webp';
		$total_media = Media_Trim_Scanner::get_total_attachments();
		$quarantined = Media_Trim_Quarantine::get_quarantined_count();
		$q_items     = Media_Trim_Quarantine::get_quarantined_items();
		?>
		<div class="wrap mtrim-wrap">
			<!-- FLOATING TOAST NOTIFICATION -->
			<div class="mtrim-toast" id="mtrim_toast" role="status" aria-live="polite" style="display: none;">
				<div class="mtrim-toast-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="mtrim-toast-body">
					<strong class="mtrim-toast-title"></strong>
					<span class="mtrim-toast-sub"></span>
				</div>
			</div>

			<!-- HEADER BRANDING -->
			<div class="mtrim-header">
				<div class="mtrim-brand">
					<div class="mtrim-logo-badge">
						<img src="<?php echo esc_url( $icon_url ); ?>" alt="Media Trim Icon" width="44" height="44" />
					</div>
					<div>
						<h1><?php esc_html_e( 'Media Trim Studio', 'media-trim' ); ?> <span class="mtrim-badge">v<?php echo esc_html( MEDIA_TRIM_VERSION ); ?></span></h1>
						<p class="mtrim-subtitle"><?php esc_html_e( 'Deterministic unused media detection, zero false-positives, and safe quarantine.', 'media-trim' ); ?></p>
					</div>
				</div>
				<div class="mtrim-author-tag">
					<span><?php esc_html_e( 'Crafted by', 'media-trim' ); ?></span>
					<a href="https://protoxen.com/" target="_blank" rel="noopener noreferrer">
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="ProtoXen" width="88" height="20" class="mtrim-protoxen-logo" />
					</a>
				</div>
			</div>

			<!-- STATS OVERVIEW CARDS -->
			<div class="mtrim-stats-grid">
				<div class="mtrim-stat-card">
					<span class="mtrim-stat-label"><?php esc_html_e( 'Total Media Registered', 'media-trim' ); ?></span>
					<strong class="mtrim-stat-value" id="mtrim_stat_total"><?php echo esc_html( number_format_i18n( $total_media ) ); ?></strong>
					<span class="mtrim-stat-desc"><?php esc_html_e( 'In active WordPress library', 'media-trim' ); ?></span>
				</div>
				<div class="mtrim-stat-card">
					<span class="mtrim-stat-label"><?php esc_html_e( 'Detected Unused Files', 'media-trim' ); ?></span>
					<strong class="mtrim-stat-value text-amber" id="mtrim_stat_unused">0</strong>
					<span class="mtrim-stat-desc"><?php esc_html_e( 'Ready for safe review', 'media-trim' ); ?></span>
				</div>
				<div class="mtrim-stat-card">
					<span class="mtrim-stat-label"><?php esc_html_e( 'Reclaimable Disk Space', 'media-trim' ); ?></span>
					<strong class="mtrim-stat-value text-emerald" id="mtrim_stat_reclaimable">0 MB</strong>
					<span class="mtrim-stat-desc"><?php esc_html_e( 'Estimated hosting savings', 'media-trim' ); ?></span>
				</div>
				<div class="mtrim-stat-card">
					<span class="mtrim-stat-label"><?php esc_html_e( 'Files in Quarantine', 'media-trim' ); ?></span>
					<strong class="mtrim-stat-value text-sky" id="mtrim_stat_quarantined"><?php echo esc_html( number_format_i18n( $quarantined ) ); ?></strong>
					<span class="mtrim-stat-desc"><?php esc_html_e( 'Staged safely with 1-click restore', 'media-trim' ); ?></span>
				</div>
			</div>

			<!-- MAIN ACTION / SCAN CONTROLLER -->
			<div class="mtrim-card mtrim-controller-card">
				<div class="mtrim-controller-content">
					<div>
						<h3><?php esc_html_e( 'Deep Reference Media Scanner', 'media-trim' ); ?></h3>
						<p><?php esc_html_e( 'Scans posts, pages, featured images, WooCommerce galleries, customizer logos, and page builders without crashing your server.', 'media-trim' ); ?></p>
					</div>
					<div class="mtrim-controller-actions">
						<button type="button" class="button button-primary button-hero" id="mtrim_btn_start_scan">
							<span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Run Safe Media Scan', 'media-trim' ); ?>
						</button>
					</div>
				</div>

				<!-- PROGRESS BAR (Hidden by default) -->
				<div class="mtrim-progress-wrap" id="mtrim_progress_wrap" style="display: none;">
					<div class="mtrim-progress-header">
						<span id="mtrim_progress_label"><?php esc_html_e( 'Initializing scan...', 'media-trim' ); ?></span>
						<strong id="mtrim_progress_percent">0%</strong>
					</div>
					<div class="mtrim-progress-bar">
						<div class="mtrim-progress-fill" id="mtrim_progress_fill" style="width: 0%;"></div>
					</div>
				</div>
			</div>

			<!-- TABS NAVIGATION -->
			<div class="mtrim-tabs-nav">
				<button type="button" class="mtrim-tab-btn active" data-tab="tab-unused">
					<span class="dashicons dashicons-images-alt2"></span>
					<?php esc_html_e( 'Unused Media', 'media-trim' ); ?> (<span id="mtrim_badge_unused">0</span>)
				</button>
				<button type="button" class="mtrim-tab-btn" data-tab="tab-quarantine">
					<span class="dashicons dashicons-shield-alt"></span>
					<?php esc_html_e( 'Quarantine Staging', 'media-trim' ); ?> (<span id="mtrim_badge_quarantined"><?php echo esc_html( $quarantined ); ?></span>)
				</button>
			</div>

			<!-- TAB 1: UNUSED MEDIA INSPECTOR -->
			<div class="mtrim-tab-pane active" id="tab-unused">
				<div class="mtrim-card">
					<div class="mtrim-table-toolbar">
						<div class="mtrim-bulk-actions">
							<button type="button" class="button button-secondary" id="mtrim_btn_select_all"><?php esc_html_e( 'Select All', 'media-trim' ); ?></button>
							<button type="button" class="button button-primary" id="mtrim_btn_quarantine_selected" disabled>
								<span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Move Selected to Quarantine', 'media-trim' ); ?>
							</button>
						</div>
						<div class="mtrim-table-summary" id="mtrim_selection_summary">
							<?php esc_html_e( 'Run a scan to detect unused media.', 'media-trim' ); ?>
						</div>
					</div>

					<div class="mtrim-grid" id="mtrim_unused_grid">
						<div class="mtrim-empty-state">
							<span class="dashicons dashicons-cloud-saved"></span>
							<strong><?php esc_html_e( 'No scan data yet', 'media-trim' ); ?></strong>
							<p><?php esc_html_e( 'Click "Run Safe Media Scan" above to discover unused assets.', 'media-trim' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<!-- TAB 2: QUARANTINE STAGING -->
			<div class="mtrim-tab-pane" id="tab-quarantine">
				<div class="mtrim-card">
					<div class="mtrim-table-toolbar">
						<div class="mtrim-bulk-actions">
							<button type="button" class="button button-secondary" id="mtrim_btn_select_all_q"><?php esc_html_e( 'Select All', 'media-trim' ); ?></button>
							<button type="button" class="button button-secondary" id="mtrim_btn_restore_selected" disabled>
								<span class="dashicons dashicons-undo"></span> <?php esc_html_e( 'Restore to Original Location', 'media-trim' ); ?>
							</button>
							<button type="button" class="button button-link-delete" id="mtrim_btn_delete_selected" disabled>
								<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete Permanently', 'media-trim' ); ?>
							</button>
						</div>
					</div>

					<div class="mtrim-q-list" id="mtrim_q_list">
						<?php if ( ! empty( $q_items ) ) : ?>
							<?php foreach ( $q_items as $qi ) : ?>
								<div class="mtrim-q-item" data-id="<?php echo esc_attr( $qi['id'] ); ?>">
									<label class="mtrim-check-label">
										<input type="checkbox" class="mtrim-q-check" value="<?php echo esc_attr( $qi['id'] ); ?>" />
										<span class="mtrim-q-name"><strong><?php echo esc_html( $qi['filename'] ); ?></strong></span>
									</label>
									<span class="mtrim-q-date"><?php echo esc_html( $qi['date'] ); ?></span>
									<div class="mtrim-q-actions">
										<button type="button" class="button button-small mtrim-single-restore" data-id="<?php echo esc_attr( $qi['id'] ); ?>"><?php esc_html_e( 'Restore', 'media-trim' ); ?></button>
										<button type="button" class="button button-small button-link-delete mtrim-single-delete" data-id="<?php echo esc_attr( $qi['id'] ); ?>"><?php esc_html_e( 'Delete', 'media-trim' ); ?></button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php else : ?>
							<div class="mtrim-empty-state">
								<span class="dashicons dashicons-shield"></span>
								<strong><?php esc_html_e( 'Quarantine is empty', 'media-trim' ); ?></strong>
								<p><?php esc_html_e( 'Any files you move to quarantine will appear here for safe staging.', 'media-trim' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
