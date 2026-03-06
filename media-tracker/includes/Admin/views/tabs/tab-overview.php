<?php
/**
 * Tab: Overview (Dashboard)
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-overview.php
 * - yourtheme/media-tracker/tabs/tab-overview.php
 * - media-tracker-pro/templates/tabs/tab-overview.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

// Get real data for dashboard.

// Get unused media count and size.
$media_tracker_unused_count     = 0;
$media_tracker_unused_size      = 0;

$media_tracker_has_scanned      = get_option( 'media_tracker_duplicates_scanned' );

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
		'unused_count'      => $media_tracker_unused_count,
		'unused_size'       => $media_tracker_unused_size,
		'duplicate_count'   => $media_tracker_duplicate_count,
	);
	set_transient( $media_tracker_cache_key, $media_tracker_stats, 30 * MINUTE_IN_SECONDS );
} else {
	$media_tracker_unused_count     = $media_tracker_stats['unused_count'];
	$media_tracker_unused_size      = $media_tracker_stats['unused_size'];
	$media_tracker_duplicate_count  = $media_tracker_stats['duplicate_count'];
}

// Get total attachments directly (real-time).
$media_tracker_count_posts       = wp_count_posts( 'attachment' );
$media_tracker_total_attachments = $media_tracker_count_posts->inherit;
$media_tracker_unused_size_formatted = size_format( $media_tracker_unused_size );

// Get most used media (top 5) - comprehensive usage tracking.
$media_tracker_most_used_raw = get_transient( 'media_tracker_most_used_media_stats' );
$media_tracker_is_cached     = ( false !== $media_tracker_most_used_raw );
$media_tracker_most_used     = $media_tracker_is_cached ? $media_tracker_most_used_raw : array();

// Get used media statistics (count and size).
$media_tracker_used_stats     = array();
$media_tracker_used_count      = 0;
$media_tracker_used_size       = 0;
$media_tracker_used_size_formatted = '0mb';

if ( class_exists( '\Media_Tracker\Admin\Media_Usage' ) ) {
	$media_tracker_used_stats = \Media_Tracker\Admin\Media_Usage::get_used_media_stats();
	if ( $media_tracker_used_stats ) {
		$media_tracker_used_count = intval( $media_tracker_used_stats->used_count );
		$media_tracker_used_size  = intval( $media_tracker_used_stats->used_size );
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
		<h3 style="display: flex; align-items: center; gap: 8px;">
			<i class="dashicons dashicons-chart-bar" style="color: #10b981;"></i>
			<?php esc_html_e( 'Media Usages Count', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php
			/* translators: %d: Number of used media files. */
			printf( esc_html__( '%d Files', 'media-tracker' ), intval( $media_tracker_used_count ) );
			?>
		</span>

		<span style="display: flex; align-items: center; color: #64748b; font-size: 12px;">
			<?php
			/* translators: %s: Formatted file size (e.g. 1.5 MB). */
			printf( esc_html__( 'Used Size: %s', 'media-tracker' ), esc_html( $media_tracker_used_size_formatted ) );
			?>
			<button id="media-tracker-refresh-stats" class="button button-small" style="margin-left: 8px;">
				<i class="dashicons dashicons-update" style="font-size: 14px; line-height: 1.5;"></i>
				<?php esc_html_e( 'Refresh', 'media-tracker' ); ?>
			</button>
		</span>
	</div>

	<div class="card">
		<h3 style="display: flex; align-items: center; gap: 8px;">
			<i class="dashicons dashicons-format-image" style="color: #ef4444;"></i>
			<?php esc_html_e( 'Unused Media', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php
			/* translators: %d: Number of unused files. */
			printf( esc_html__( '%d Files', 'media-tracker' ), intval( $media_tracker_unused_count ) );
			?>
		</span>

		<?php if ( $media_tracker_unused_size > 0 ) : ?>
		<span style="color: #64748b; font-size: 12px;">
			<?php
			/* translators: %s: Formatted file size (e.g. 1.5 MB). */
			printf( esc_html__( 'Potential saving: %s', 'media-tracker' ), esc_html( $media_tracker_unused_size_formatted ) );
			?>
		</span>
		<?php endif; ?>
	</div>

	<div class="card">
		<h3 style="display: flex; align-items: center; gap: 8px;">
			<i class="dashicons dashicons-images-alt2" style="color: #f59e0b;"></i>
			<?php esc_html_e( 'Duplicates Found', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php
			if ( ! $media_tracker_has_scanned ) {
				echo '<a href="' . esc_url( admin_url( 'upload.php?page=media-tracker&tab=duplicates' ) ) . '" style="font-size:14px; text-decoration:none;">' . esc_html__( 'Scan Required', 'media-tracker' ) . '</a>';
			} else {
				/* translators: %d: Number of duplicate files. */
				printf( esc_html__( '%d Files', 'media-tracker' ), intval( $media_tracker_duplicate_count ) );
			}
			?>
		</span>
		<span style="color: #64748b; font-size: 12px;">
			<?php esc_html_e( 'Based on file hash matching', 'media-tracker' ); ?>
		</span>
	</div>

	<div class="card">
		<h3 style="display: flex; align-items: center; gap: 8px;">
			<i class="dashicons dashicons-admin-media" style="color: #6366f1;"></i>
			<?php esc_html_e( 'Total Media', 'media-tracker' ); ?>
		</h3>
		<span class="value">
			<?php
			/* translators: %d: Total number of media files. */
			printf( esc_html__( '%d Files', 'media-tracker' ), intval( $media_tracker_total_attachments ) );
			?>
		</span>
		<span style="color: #64748b; font-size: 12px;">
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

		<div class="setting-item" style="margin-top: 10px;">
			<div>
				<strong><?php esc_html_e( 'Usages Count', 'media-tracker' ); ?></strong>
				<p style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Scan all content to find unused media files.', 'media-tracker' ); ?></p>
			</div>
			<button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="location.href='<?php echo esc_url( admin_url( 'upload.php?highlight=usage_count' ) ); ?>'">
				<?php esc_html_e( 'Find', 'media-tracker' ); ?>
			</button>
		</div>

		<div class="setting-item" style="margin-top: 10px;">
			<div>
				<strong><?php esc_html_e( 'Scan for Unused Media', 'media-tracker' ); ?></strong>
				<p style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Scan all content to find unused media files.', 'media-tracker' ); ?></p>
			</div>
			<button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="location.href='<?php echo esc_url( admin_url( 'upload.php?page=media-tracker&tab=unused-media' ) ); ?>'">
				<?php esc_html_e( 'Scan', 'media-tracker' ); ?>
			</button>
		</div>

		<div class="setting-item">
			<div>
				<strong><?php esc_html_e( 'Find Duplicates', 'media-tracker' ); ?></strong>
				<p style="font-size: 12px; color: #64748b;"><?php esc_html_e( 'Detects duplicate images using file hash matching.', 'media-tracker' ); ?></p>
			</div>
			<button class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="location.href='<?php echo esc_url( admin_url( 'upload.php?page=media-tracker&tab=duplicates' ) ); ?>'">
				<?php esc_html_e( 'Find', 'media-tracker' ); ?>
			</button>
		</div>

		<div class="setting-item">
			<div>
				<strong><?php esc_html_e( 'Bulk Delete Unused', 'media-tracker' ); ?></strong>
				<p style="font-size: 12px; color: #64748b;">
					<?php
					/* translators: %d: Number of unused files. */
					printf( esc_html__( '%d unused files found. Delete safely after backup.', 'media-tracker' ), intval( $media_tracker_unused_count ) );
					?>
				</p>
			</div>
			<button class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="location.href='<?php echo esc_url( admin_url( 'upload.php?page=media-tracker&tab=unused-media' ) ); ?>'">
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
					<div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8fafc; border-radius: 8px;">
						<i class="dashicons dashicons-media-default" style="color: #6366f1; width: 20px; text-align: center;"></i>
						<div style="flex: 1;">
							<div style="font-size: 14px; font-weight: 500;">
								<?php echo esc_html( str_replace( 'image/', '', $media_tracker_mime->post_mime_type ) ); ?>
							</div>
							<div class="sub-label">
								<?php
								/* translators: %d: Number of files for a specific mime type. */
								printf( esc_html__( '%d files', 'media-tracker' ), intval( $media_tracker_mime->count ) );
								?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<p style="color: #64748b; font-size: 12px; text-align: center; padding: 20px;">
					<?php esc_html_e( 'No media files found yet.', 'media-tracker' ); ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="card" style="margin-top: 1.5rem;">
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
							<th style="text-align: center;"><?php esc_html_e( 'Actions', 'media-tracker' ); ?></th>
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
									<span class="tag" style="background: #10b981; color: white;">
										<?php
										/* translators: %d: Number of times the media is used. */
										printf( esc_html__( '%d times', 'media-tracker' ), intval( $media_tracker_media->usage_count ) );
										?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( $media_tracker_edit_link ); ?>" class="btn btn-outline" style="padding: 5px 10px; text-decoration: none;">
										<i class="dashicons dashicons-visibility"></i>
										<?php esc_html_e( 'View', 'media-tracker' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p style="color: #64748b; text-align: center; padding: 40px;">
					<i class="dashicons dashicons-chart-bar" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px; height: 48px; width: 48px;"></i><br>
					<?php esc_html_e( 'No media usage data available.', 'media-tracker' ); ?>
				</p>
			<?php endif; ?>
		<?php else : ?>
			<div id="media-tracker-loading-stats" style="text-align: center; padding: 40px;">
				<span class="spinner is-active" style="float:none; margin:0 10px 0 0;"></span>
				<?php esc_html_e( 'Loading usage statistics...', 'media-tracker' ); ?>
			</div>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'media_tracker_get_most_used'
					},
					success: function(response) {
						if (response.success) {
							$('#media-tracker-most-used-container').html(response.data.html);
						} else {
							$('#media-tracker-most-used-container').html('<p style="padding:20px;text-align:center;">' + (response.data || 'Error loading stats.') + '</p>');
						}
					},
					error: function() {
						$('#media-tracker-most-used-container').html('<p style="padding:20px;text-align:center;">Error loading stats.</p>');
					}
				});
			});
			</script>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Refresh stats button handler
	$('#media-tracker-refresh-stats').on('click', function(e) {
		e.preventDefault();

		var $button = $(this);
		var $icon = $button.find('.dashicons-update');
		var originalText = $button.html();

		// Show loading state
		$button.prop('disabled', true);
		$icon.addClass('spin-animation');
		$button.html('<span class="spinner is-active" style="float:none; margin:0;"></span> <?php echo esc_js( 'Refreshing...', 'media-tracker' ); ?>');

		// AJAX request to refresh stats
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'media_tracker_refresh_used_stats',
				nonce: '<?php echo esc_js( wp_create_nonce( 'media_tracker_refresh' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					// Update the count
					var countElement = $button.closest('.card').find('.value');
					var formattedCount = response.data.used_count + ' <?php echo esc_js( 'Files', 'media-tracker' ); ?>';
					countElement.text(formattedCount);

					// Update the size
					var sizeElement = $button.closest('.card').find('span').last();
					var currentSizeText = sizeElement.text().split(':')[0];
					sizeElement.html(currentSizeText + ': ' + response.data.used_size_formatted);

					// Show success message
					$button.html('<i class="dashicons dashicons-yes" style="font-size: 14px; line-height: 1.5;"></i> <?php echo esc_js( 'Refreshed!', 'media-tracker' ); ?>');
					setTimeout(function() {
						$button.html(originalText);
						$button.prop('disabled', false);
					}, 2000);
				} else {
					// Show error
					$button.html('<i class="dashicons dashicons-no" style="font-size: 14px; line-height: 1.5;"></i> <?php echo esc_js( 'Error', 'media-tracker' ); ?>');
					setTimeout(function() {
						$button.html(originalText);
						$button.prop('disabled', false);
					}, 2000);
				}
			},
			error: function() {
				// Show error
				$button.html('<i class="dashicons dashicons-no" style="font-size: 14px; line-height: 1.5;"></i> <?php echo esc_js( 'Error', 'media-tracker' ); ?>');
				setTimeout(function() {
					$button.html(originalText);
					$button.prop('disabled', false);
				}, 2000);
			}
		});
	});

	// Add CSS for spinner animation
	$('<style>.spin-animation{animation: spin 1s linear infinite;}@keyframes spin{from{transform:rotate(0deg);}to{transform:rotate(360deg);}}</style>').appendTo('head');
});
</script>
