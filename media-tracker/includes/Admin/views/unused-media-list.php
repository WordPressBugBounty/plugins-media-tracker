<?php
/**
 * Unused Media List View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="unused-media-list">
	<div class="media-header">
		<div class="section-title">
			<h2>
				<i class="dashicons dashicons-format-image"></i>
				<?php esc_html_e( 'Unused Media', 'media-tracker' ); ?>
			</h2>
			<p class="page-subtitle">
				<?php esc_html_e( 'Detect media files not used anywhere on your site for safe cleanup.', 'media-tracker' ); ?>
			</p>
		</div>

		<div class="unused-image-found">
			<?php
				// Use the same cached total as the table to ensure consistency.
				$media_tracker_search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$media_tracker_initial_count = $media_tracker_unused_media_list->get_total_items( $media_tracker_search );
			?>
			<h2><?php echo '<span id="unused-count">'.esc_html( $media_tracker_initial_count ).'</span>' . ' ' . esc_html__( 'unused media found!', 'media-tracker' ); ?></h2>
		</div>
	</div>

	<div id="media-scan-progress" class="mt-scan-progress">
		<p id="media-scan-progress-text" class="mt-scan-progress-text">
			<?php esc_html_e( 'Scan status: Ready to scan...', 'media-tracker' ); ?>
		</p>
		<div id="media-scan-progress-bar" class="mt-scan-progress-track">
			<div id="media-scan-progress-fill" class="mt-scan-progress-fill"></div>
			<div class="mt-scan-progress-stripes"></div>
		</div>
	</div>

	<!-- Fallback container for scan button when no items exist -->
	<div class="media-scan-controls"></div>

	<form method="post">
		<?php $media_tracker_unused_media_list->display(); ?>
	</form>
</div>
