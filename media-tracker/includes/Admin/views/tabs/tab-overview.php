<?php
/**
 * Tab: Overview (Dashboard)
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

// Get unused media count and size.
$media_tracker_unused_count = 0;
$media_tracker_unused_size  = 0;

$media_tracker_has_scanned = get_option( 'media_tracker_duplicates_scanned' );

if ( ! $media_tracker_has_scanned ) {
	// Fallback: Check if any hashes exist in DB (migration support).
	$media_tracker_hash_exists = false;
	if ( class_exists( '\Media_Tracker\Admin\Duplicate_Images' ) ) {
		$media_tracker_hash_exists = \Media_Tracker\Admin\Duplicate_Images::has_generated_hashes();
	}
	if ( $media_tracker_hash_exists ) {
		update_option( 'media_tracker_duplicates_scanned', true );
		$media_tracker_has_scanned = true;
		delete_transient( 'media_tracker_dashboard_stats_v8' );
	}
}

// Check cached data first.
$media_tracker_cache_key = 'media_tracker_dashboard_stats_v8';
$media_tracker_stats     = get_transient( $media_tracker_cache_key );

if ( false === $media_tracker_stats ) {
	// Fast fallback: Use stored options from last scan to avoid heavy calculation on page load.
	$media_tracker_unused_count    = (int) get_option( 'media_tracker_unused_count_last_scan', 0 );
	$media_tracker_unused_size     = (int) get_option( 'media_tracker_unused_size_last_scan', 0 );
	$media_tracker_duplicate_count = (int) get_option( 'media_tracker_duplicate_count_last_scan', 0 );

	$media_tracker_stats = array(
		'unused_count'    => $media_tracker_unused_count,
		'unused_size'     => $media_tracker_unused_size,
		'duplicate_count' => $media_tracker_duplicate_count,
	);
	set_transient( $media_tracker_cache_key, $media_tracker_stats, 30 * MINUTE_IN_SECONDS );
} else {
	$media_tracker_unused_count    = $media_tracker_stats['unused_count'];
	$media_tracker_unused_size     = $media_tracker_stats['unused_size'];
	$media_tracker_duplicate_count = $media_tracker_stats['duplicate_count'];
}

// Get total attachments directly (real-time).
$media_tracker_count_posts           = wp_count_posts( 'attachment' );
$media_tracker_total_attachments     = $media_tracker_count_posts->inherit;
$media_tracker_unused_size_formatted = size_format( $media_tracker_unused_size );

// Get most used media (top 5) - comprehensive usage tracking.
$media_tracker_most_used_raw = get_transient( 'media_tracker_most_used_media_stats' );
$media_tracker_is_cached     = ( false !== $media_tracker_most_used_raw );
$media_tracker_most_used     = $media_tracker_is_cached ? $media_tracker_most_used_raw : array();

// Get used media statistics (count and size).
$media_tracker_used_stats          = array();
$media_tracker_used_count          = 0;
$media_tracker_used_size           = 0;
$media_tracker_used_size_formatted = '0mb';

if ( class_exists( '\Media_Tracker\Admin\Media_Usage' ) ) {
	$media_tracker_used_stats = \Media_Tracker\Admin\Media_Usage::get_used_media_stats();
	if ( $media_tracker_used_stats ) {
		$media_tracker_used_count          = intval( $media_tracker_used_stats->used_count );
		$media_tracker_used_size           = intval( $media_tracker_used_stats->used_size );
		$media_tracker_used_size_formatted = size_format( $media_tracker_used_size );
	}
}
?>

<div class="media-header-overview">
	<div class="media-header">
		<div class="section-title">
			<h2><i class="dashicons dashicons-admin-home"></i> <?php esc_html_e( 'Dashboard', 'media-tracker' ); ?></h2>
			<p class="page-subtitle">
				<?php esc_html_e( 'Manage unused media, duplicate, optimization and cloud offload from a clean dashboard with Media Tracker.', 'media-tracker' ); ?>
			</p>
		</div>
	</div>
</div>

<div class="stats-grid">
	<div class="card">
		<h3 class="mt-stat-title">
			<i class="dashicons dashicons-chart-bar mt-icon-green"></i>
			<?php esc_html_e( 'Media Usages Count', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php echo esc_html( number_format_i18n( intval( $media_tracker_used_count ) ) ); ?>
		</span>

		<span class="mt-flex-center mt-stat-subtitle">
			<?php
			/* translators: %s: Formatted file size (e.g. 1.5 MB). */
			printf( esc_html__( 'Used Size: %s', 'media-tracker' ), esc_html( $media_tracker_used_size_formatted ) );
			?>
			<button id="media-tracker-refresh-stats" class="button button-small" data-nonce="<?php echo esc_attr( wp_create_nonce( 'media_tracker_refresh' ) ); ?>">
				<i class="dashicons dashicons-update mt-btn-icon"></i>
				<?php esc_html_e( 'Refresh', 'media-tracker' ); ?>
			</button>
		</span>
	</div>

	<div class="card">
		<h3 class="mt-stat-title">
			<i class="dashicons dashicons-format-image mt-icon-red"></i>
			<?php esc_html_e( 'Unused Media', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php echo esc_html( number_format_i18n( intval( $media_tracker_unused_count ) ) ); ?>
		</span>

		<?php if ( $media_tracker_unused_size > 0 ) : ?>
		<span class="mt-stat-subtitle">
			<?php
			/* translators: %s: Formatted file size (e.g. 1.5 MB). */
			printf( esc_html__( 'Potential saving: %s', 'media-tracker' ), esc_html( $media_tracker_unused_size_formatted ) );
			?>
		</span>
		<?php endif; ?>
	</div>

	<div class="card">
		<h3 class="mt-stat-title">
			<i class="dashicons dashicons-images-alt2 mt-icon-amber"></i>
			<?php esc_html_e( 'Duplicates Found', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php
			if ( ! $media_tracker_has_scanned ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=media-tracker-duplicates' ) ) . '" class="mt-scan-info-link">' . esc_html__( 'Scan Required', 'media-tracker' ) . '</a>';
			} else {
				echo esc_html( number_format_i18n( intval( $media_tracker_duplicate_count ) ) );
			}
			?>
		</span>
		<span class="mt-stat-subtitle">
			<?php esc_html_e( 'Based on file hash matching', 'media-tracker' ); ?>
		</span>
	</div>

	<div class="card">
		<h3 class="mt-stat-title">
			<i class="dashicons dashicons-admin-media mt-icon-indigo"></i>
			<?php esc_html_e( 'Total Media', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php echo esc_html( number_format_i18n( intval( $media_tracker_total_attachments ) ) ); ?>
		</span>
		<span class="mt-stat-subtitle">
			<?php esc_html_e( 'Total files in library', 'media-tracker' ); ?>
		</span>
	</div>
</div>

<div class="grid-two">
	<div class="card">
		<div class="section-title">
			<i class="dashicons dashicons-superhero"></i>
			<?php esc_html_e( 'Quick Actions', 'media-tracker' ); ?>
		</div>

		<div class="setting-item mt-mt-10">
			<div>
				<strong><?php esc_html_e( 'Usages Count', 'media-tracker' ); ?></strong>
				<p class="mt-helper-text"><?php esc_html_e( 'Scan all content to find unused media files.', 'media-tracker' ); ?></p>
			</div>
			<button class="btn btn-primary mt-btn-sm" onclick="location.href='<?php echo esc_url( admin_url( 'upload.php?highlight=usage_count' ) ); ?>'">
				<?php esc_html_e( 'Find', 'media-tracker' ); ?>
			</button>
		</div>

		<div class="setting-item">
			<div>
				<strong><?php esc_html_e( 'Scan for Unused Media', 'media-tracker' ); ?></strong>
				<p class="mt-helper-text">
					<?php esc_html_e( 'Scan all content to find unused media files.', 'media-tracker' ); ?>
				</p>
			</div>
			<button class="btn btn-primary mt-btn-sm" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=media-tracker-unused-media' ) ); ?>'">
				<?php esc_html_e( 'Scan', 'media-tracker' ); ?>
			</button>
		</div>

		<div class="setting-item">
			<div>
				<strong><?php esc_html_e( 'Find Duplicates', 'media-tracker' ); ?></strong>
				<p class="mt-helper-text"><?php esc_html_e( 'Detects duplicate images using file hash matching.', 'media-tracker' ); ?></p>
			</div>
			<button class="btn btn-primary mt-btn-sm" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=media-tracker-duplicates' ) ); ?>'">
				<?php esc_html_e( 'Find', 'media-tracker' ); ?>
			</button>
		</div>

		<div class="setting-item">
			<div>
				<strong><?php esc_html_e( 'Bulk Delete Unused', 'media-tracker' ); ?></strong>
				<p class="mt-helper-text">
					<?php
					/* translators: %d: Number of unused files. */
					printf( esc_html__( '%d unused files found. Delete safely after backup.', 'media-tracker' ), intval( $media_tracker_unused_count ) );
					?>
				</p>
			</div>
			<button class="btn btn-danger mt-btn-sm" onclick="location.href='<?php echo esc_url( admin_url( 'admin.php?page=media-tracker-unused-media' ) ); ?>'">
				<?php esc_html_e( 'Delete', 'media-tracker' ); ?>
			</button>
		</div>
	</div>

	<div class="card">
		<div class="section-title">
			<i class="dashicons dashicons-chart-bar"></i>
			<?php esc_html_e( 'Media Statistics', 'media-tracker' ); ?>
		</div>
		<div class="stacked">
			<?php
			// Get media type statistics.
			$media_tracker_mime_types = array();
			if ( class_exists( '\Media_Tracker\Admin\Media_Usage' ) ) {
				$media_tracker_mime_types = \Media_Tracker\Admin\Media_Usage::get_mime_type_stats();
			}
			?>

			<?php if ( ! empty( $media_tracker_mime_types ) ) : ?>
				<?php foreach ( $media_tracker_mime_types as $media_tracker_mime ) : ?>
					<div class="mt-mime-item">
						<i class="dashicons dashicons-media-default mt-mime-icon"></i>
						<div class="mt-flex-1 statistics">
							<div class="mt-font-medium">
								<?php echo esc_html( str_replace( 'image/', '', $media_tracker_mime->post_mime_type ) ); ?>
							</div>
							<div class="sub-label">
								<?php echo esc_html( number_format_i18n( intval( $media_tracker_mime->count ) ) ); ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p class="mt-empty-state">
					<?php esc_html_e( 'No media files found yet.', 'media-tracker' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="card mt-mt-6">
	<div class="section-title">
		<i class="dashicons dashicons-chart-area"></i>
		<?php esc_html_e( 'Most Used Media', 'media-tracker' ); ?>
	</div>

	<div id="media-tracker-most-used-container">
		<?php if ( $media_tracker_is_cached ) : ?>
			<?php if ( ! empty( $media_tracker_most_used ) ) : ?>
				<table class="mt-overview-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'File Name', 'media-tracker' ); ?></th>
							<th><?php esc_html_e( 'Type', 'media-tracker' ); ?></th>
							<th><?php esc_html_e( 'Usage Count', 'media-tracker' ); ?></th>
							<th class="mt-text-center"><?php esc_html_e( 'Actions', 'media-tracker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $media_tracker_most_used as $media_tracker_media ) : ?>
							<?php
							$media_tracker_file_type = $media_tracker_media->post_mime_type ? str_replace( 'image/', '', $media_tracker_media->post_mime_type ) : '-';
							$media_tracker_edit_link = get_edit_post_link( $media_tracker_media->ID );
							$media_tracker_view_link = get_permalink( $media_tracker_media->ID );
							?>
							<tr>
								<td><strong><?php echo esc_html( $media_tracker_media->post_title ); ?></strong></td>
								<td><?php echo esc_html( $media_tracker_file_type ); ?></td>
								<td>
									<span class="tag mt-tag-success">
										<?php
										/* translators: %d: Number of times the media is used. */
										printf( esc_html__( '%d times', 'media-tracker' ), intval( $media_tracker_media->usage_count ) );
										?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( $media_tracker_edit_link ); ?>" class="btn btn-outline mt-btn-xs-clean">
										<i class="dashicons dashicons-visibility"></i>
										<?php esc_html_e( 'View', 'media-tracker' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="mt-empty-state-large">
					<i class="dashicons dashicons-chart-bar mt-chart-icon-large"></i><br>
					<?php esc_html_e( 'No media usage data available.', 'media-tracker' ); ?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<div id="media-tracker-loading-stats" class="mt-loading-state">
				<span class="spinner is-active mt-spinner-inline"></span>
				<?php esc_html_e( 'Loading usage statistics...', 'media-tracker' ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
