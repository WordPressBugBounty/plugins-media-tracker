<?php
/**
 * Tab: Documents
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="media-header-overview">
	<div class="media-header">
		<div class="section-title">
			<h2><i class="dashicons dashicons-media-document"></i> <?php esc_html_e( 'Documents', 'media-tracker' ); ?></h2>
			<p class="page-subtitle">
				<?php esc_html_e( 'Manage and track your document files.', 'media-tracker' ); ?>
			</p>
		</div>
	</div>
</div>

<div class="media-info-grid">
	<!-- Card 1: Overview -->
	<div class="info-card">
		<div class="info-card-icon" style="background: #eff6ff; color: #3b82f6;">
			<i class="dashicons dashicons-media-document"></i>
		</div>
		<h3 class="info-card-title"><?php esc_html_e( 'Documentation', 'media-tracker' ); ?></h3>
		<p class="info-card-desc">
			<?php esc_html_e( 'Learn how to track media usage and clean up unused files in WordPress.', 'media-tracker' ); ?>
		</p>

		<a href="https://thebitcraft.com/docs/media-tracker/" target="_blank" class="info-card-link">
			<?php esc_html_e( 'Read More', 'media-tracker' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>
	</div>

	<!-- Card 2: Join Our Community -->
	<div class="info-card">
		<div class="info-card-icon" style="background: #e0e7ff; color: #4338ca;">
			<i class="dashicons dashicons-groups"></i>
		</div>
		<h3 class="info-card-title"><?php esc_html_e( 'Join Our Community', 'media-tracker' ); ?></h3>
		<p class="info-card-desc">
			<?php esc_html_e( 'Join our community to discuss features, share ideas, and get help from other users.', 'media-tracker' ); ?>
		</p>
		<a href="https://mediatracker.thebitcraft.com" target="_blank" class="info-card-link">
			<?php esc_html_e( 'Join Now', 'media-tracker' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>
	</div>

	<!-- Card 3: Show your love -->
	<div class="info-card">
		<div class="info-card-icon" style="background: #ffe4e6; color: #e11d48;">
			<i class="dashicons dashicons-heart"></i>
		</div>
		<h3 class="info-card-title"><?php esc_html_e( 'Show your love', 'media-tracker' ); ?></h3>
		<p class="info-card-desc">
			<?php esc_html_e( 'Enjoying Media Tracker? Please rate us on WordPress.org to help us grow.', 'media-tracker' ); ?>
		</p>
		<a href="https://wordpress.org/support/plugin/media-tracker/reviews/#new-post" target="_blank" class="info-card-link">
			<?php esc_html_e( 'Rate Us', 'media-tracker' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
		</a>
	</div>
</div>

<div class="video-tutorials-section" style="margin-top: 2.5rem;">
	<h2 class="section-title" style="margin-bottom: 1.5rem; font-size: 16px;">
		<i class="dashicons dashicons-video-alt3"></i>
		<?php esc_html_e( 'Video Tutorials', 'media-tracker' ); ?>
	</h2>

	<!-- Single Featured Video Card -->
	<div class="media-video-featured-card" id="mt-open-video-modal" data-video-id="2eMRuW5X-iI">
		<div class="video-thumbnail-wrapper">
			<img src="<?php echo esc_url( MEDIA_TRACKER_ASSETS ); ?>/dist/images/youtube-thumb.webp" class="video-thumbnail" alt="<?php esc_attr_e( 'Getting Started', 'media-tracker' ); ?>">
			<div class="play-button-overlay">
				<div class="play-icon">
					<span class="dashicons dashicons-controls-play"></span>
				</div>
			</div>
		</div>
		<div class="video-card-content">
			<div class="video-text">
				<h3 class="info-card-title">
					<?php esc_html_e( 'Getting Started with Media Tracker', 'media-tracker' ); ?>
				</h3>
				<p class="info-card-desc">
					<?php esc_html_e( 'Learn the basics of Media Tracker in 2 minutes. Watch our comprehensive guide to master file management.', 'media-tracker' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>

<!-- Video Modal -->
<div id="mt-video-modal-backdrop" class="mt-video-modal-backdrop">
	<div class="mt-video-modal">
		<div class="mt-video-modal-header">
			<h3 id="mt-video-modal-title"><?php esc_html_e( 'Getting Started', 'media-tracker' ); ?></h3>
			<button type="button" class="mt-video-modal-close" id="mt-close-video-modal">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="mt-video-modal-body">
			<div class="mt-responsive-video-wrapper" id="mt-video-container">
				<!-- iframe will be injected here -->
			</div>
		</div>
	</div>
</div>

<script>
	jQuery(document).ready(function($) {
		const $modalBackdrop = $('#mt-video-modal-backdrop');
		const $openBtn = $('#mt-open-video-modal');
		const $closeBtn = $('#mt-close-video-modal');
		const $videoContainer = $('#mt-video-container');
		const videoId = $openBtn.data('video-id');

		// Open Modal
		$openBtn.on('click', function(e) {
			e.preventDefault();
			$modalBackdrop.addClass('active');

			// Inject iframe only when opening (Lazy Load)
			if ($videoContainer.find('iframe').length === 0) {
				const iframeHtml = '<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0&modestbranding=1&showinfo=0&iv_load_policy=3" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
				$videoContainer.html(iframeHtml);
			}
		});

		// Close Modal Function
		function closeModal() {
			$modalBackdrop.removeClass('active');
			// Clear iframe to stop video playback
			setTimeout(function() {
				$videoContainer.empty();
			}, 300); // Wait for transition
		}

		// Close events
		$closeBtn.on('click', closeModal);

		$modalBackdrop.on('click', function(e) {
			if ($(e.target).is('#mt-video-modal-backdrop')) {
				closeModal();
			}
		});

		// ESC key to close
		$(document).on('keydown', function(e) {
			if (e.key === "Escape" && $modalBackdrop.hasClass('active')) {
				closeModal();
			}
		});
	});
</script>
