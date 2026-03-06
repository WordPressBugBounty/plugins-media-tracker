<?php
/**
 * Unused Media List View
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! isset( $GLOBALS['mediaTracker'] ) || ! isset( $GLOBALS['mediaTracker']['nonce'] ) ) {
	wp_localize_script( 'mt-admin-script', 'mediaTracker', array(
		'nonce' => wp_create_nonce( 'media_tracker_nonce' ),
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	) );
}
?>

<div class="unused-media-list">
	<div class="media-header">
		<div class="section-title">
			<h2><i class="dashicons dashicons-format-image"></i> <?php esc_html_e( 'Unused Media', 'media-tracker' ); ?></h2>
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

	<div id="media-scan-progress" style="display: none; margin: 16px 0 20px; padding: 14px 15px 18px; border: 1px solid #e0e0e0; border-radius: 3px;">
		<p id="media-scan-progress-text" style="margin: 0 0 8px;">
			<?php esc_html_e( 'Scan status: Ready to scan...', 'media-tracker' ); ?>
		</p>
		<div id="media-scan-progress-bar" style="position: relative; height: 15px; background: #f0f0f0; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden; ">
			<div id="media-scan-progress-fill" style="height: 100%; width: 0; background: #6366f1; background-size: 200% 100%; transition: width 0.3s ease-in-out; will-change: width;"></div>
			<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0.15) 75%, transparent 75%, transparent); background-size: 20px 20px; "></div>
		</div>
	</div>

	<!-- Fallback container for scan button when no items exist -->
	<div class="media-scan-controls" style="display: none; margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #6366f1; box-shadow: 0 1px 1px rgba(0,0,0,.04);"></div>

	<form method="post">
		<?php
			$media_tracker_unused_media_list->display();
		?>
	</form>
</div>

<script type="text/javascript">
(function($){
	// Ensure mediaTracker and ajaxUrl are defined before using
	if (typeof window.mediaTracker === 'undefined') {
		window.mediaTracker = {
			ajax_url: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
			nonce: '<?php echo esc_js( wp_create_nonce( 'media_tracker_nonce' ) ); ?>'
		};
	}
	var AJAX_URL = (window.mediaTracker && (window.mediaTracker.ajax_url || window.mediaTracker.ajaxUrl)) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>');

	var NONCE = (window.mediaTracker && window.mediaTracker.nonce) || '<?php echo esc_js( wp_create_nonce( 'media_tracker_nonce' ) ); ?>';
	var pollInterval = null;
	var stuckChecks = 0; // consecutive polls stuck near start
	var syncTriggered = false;
	var clientProgress = 0; // Client-side progress simulation
	var targetProgress = 0; // Target progress from server
	var currentStepName = 'Starting...';
	var progressAnimInterval = null; // Animation interval

	function ensureScanButtonExists(label){
		// Deduplicate any accidental duplicates first
		var $all = $('#run-media-scan');
		if ($all.length > 1) { $all.slice(1).remove(); }
		var $btn = $('#run-media-scan');
		if ($btn.length) {
			if (label) { $btn.first().text(label); }
			return $btn.first();
		}
		label = label || 'Scan Unused Media';
		var $newBtn = $('<button/>', { id: 'run-media-scan', class: 'button button-primary', text: label });
		var $topBulk = $('.tablenav.top .bulkactions');
		if ($topBulk.length) {
			$newBtn.css({ marginLeft: '8px', marginTop: '0' });
			$topBulk.append($newBtn);
			$('.media-scan-controls').hide();
		} else {
			// No bulkactions, use fallback container with message
			var $message = $('<p/>', {
				html: '<strong>No unused media found.</strong> Click the button below to scan your site for unused media files.',
				css: { margin: '0 0 12px 0', color: '#646970' }
			});
			$('.media-scan-controls').show().empty().append($message).append($newBtn);
		}

		// Add Remove All button if it doesn't exist
		if ($('#remove-all-unused-media').length === 0) {
			var $removeBtn = $('<button/>', { id: 'remove-all-unused-media', class: 'button button-secondary', text: 'Remove all unused media' });
			$removeBtn.css({ marginLeft: '8px' });
			$newBtn.after($removeBtn);
		}

		return $newBtn;
	}
	function repositionScanButton(label){
		// Ensure a single button instance exists
		ensureScanButtonExists(label);
		var $btn = $('#run-media-scan').first();
		var $topApply = $('.tablenav.top .bulkactions #doaction');
		var $topBulk = $('.tablenav.top .bulkactions');
		if ($btn.length && $topApply.length) {
			$btn.detach().css({ marginLeft: '8px', marginTop: '0' });
			$btn.insertAfter($topApply);
			$('.media-scan-controls').hide();
		} else if ($btn.length && $topBulk.length) {
			$btn.detach().css({ marginLeft: '8px', marginTop: '0' });
			$topBulk.append($btn);
			$('.media-scan-controls').hide();
		} else {
			// No bulkactions found (no items), use fallback container
			$('.media-scan-controls').show().empty().append($btn);
		}
	}
	$(function(){ repositionScanButton(); });

	function refreshListTable(label){
		var url = new URL(window.location.href);
		url.searchParams.set('refresh_cache','1');
		$.get(url.toString(), function(html){
			var $html = $('<div>').html(html);
			var $newForm = $html.find('.wrap.unused-media-list form').first();
			if ($newForm.length) {
				var $wrap = $('.wrap.unused-media-list');
				$wrap.find('form').first().replaceWith($newForm);
				// Guard: ensure only one form instance exists
				$wrap.find('form').slice(1).remove();
				repositionScanButton(label);
			}
		});
	}

	// Smooth progress animation function
	function animateProgress(){
		// Simulate continuous progress even if server is slow (Fake Progress)
		// Stop auto-incrementing if we reach 99% to wait for actual completion
		if (targetProgress < 99 && clientProgress >= targetProgress - 1) {
			targetProgress += 0.2;
		}

		// Gradually move clientProgress towards targetProgress
		if (clientProgress < targetProgress) {
			// Increase gradually based on distance
			var diff = targetProgress - clientProgress;
			// Smoother increments for float values
			var increment = diff > 30 ? 2 : (diff > 10 ? 1 : 0.2);

			clientProgress += increment;
			if (clientProgress > targetProgress) {
				clientProgress = targetProgress;
			}
			$('#media-scan-progress-fill').css('width', clientProgress + '%');
			$('#media-scan-progress-text').html('Scan status: ' + currentStepName + ' (' + Math.floor(clientProgress) + '%)');
		}
	}

	function updateProgress(){
		$.post(AJAX_URL, { action: 'get_media_scan_progress', nonce: NONCE }).done(function(res){
			if (!res || !res.success || !res.data) {
				// If no data yet, simulate gradual progress
				if (targetProgress < 90) {
					targetProgress += 5; // Add 5% gradually
				}
				return;
			}

			var d = res.data;
			var pct = Math.max(0, Math.min(100, parseInt(d.percentage, 10) || 0));

			// Prevent regression: only update target if server reports higher progress
			targetProgress = Math.max(targetProgress, pct);

			if (d.current_step) {
				currentStepName = d.current_step;
			}

			// If we are caught up, update text immediately
			if (clientProgress >= targetProgress) {
				$('#media-scan-progress-text').html('Scan status: ' + currentStepName + ' (' + Math.round(clientProgress) + '%)');
			}

			// Fallback: if scan appears stalled at start, trigger synchronous scan
			if (!syncTriggered) {
				if ((d.step <= 1) && (pct <= 20)) {
					stuckChecks++;
					if (stuckChecks >= 6) { // Changed from 3 to 6 (now ~3s at 500ms interval)
						syncTriggered = true;
						$.post(AJAX_URL, { action: 'run_media_scan_sync', nonce: NONCE }).always(function(){
							// continue polling; sync handler updates progress to completion
						});
					}
				} else {
					stuckChecks = 0;
				}
			}

			if (pct >= 100 || d.step >= d.total_steps) {
				// Stop animations
				clearInterval(pollInterval);
				clearInterval(progressAnimInterval);
				clientProgress = 100;
				targetProgress = 100;
				$('#media-scan-progress-fill').css('width', '100%');

				// Remove tab closing prevention
				$(window).off('beforeunload.mediaTrackerScan');

				// Clear progress transient to avoid sticky progress UI
				$.post(AJAX_URL, {
					action: 'clear_media_scan_progress',
					nonce: NONCE
				}).always(function(){
					// Get the count of unused images and display it
					$.post(AJAX_URL, {
						action: 'get_unused_media_count',
						nonce: NONCE,
						search: '<?php echo esc_js( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>',
						author_id: '<?php echo esc_js( isset( $_GET['author'] ) ? intval( $_GET['author'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>'
					}).done(function(countRes){
						if (countRes && countRes.success && countRes.data) {
							$('#media-scan-progress-text').html('✅ <strong>Scan Complete!</strong> - ' + countRes.data.message);
							$('#unused-count').text(countRes.data.count);
						} else {
							$('#media-scan-progress-text').html('✅ <strong>Scan Complete!</strong> (100%)');
						}
					}).fail(function(){
						$('#media-scan-progress-text').html('✅ <strong>Scan Complete!</strong> (100%)');
					}).always(function(){
						ensureScanButtonExists('Rescan').prop('disabled', false);
						// refresh list table so item count and pagination match new cache
						refreshListTable('Rescan');
						// keep progress bar visible for 3 seconds, then reload page
						setTimeout(function(){
							$('#media-scan-progress').fadeOut(300, function(){
								// Reload page to show fresh data
								location.reload();
							});
						}, 3000); // Reduced from 15000 to 3000
					});
				});
			}
		});
	}

	function startPolling(){
		if (pollInterval) { clearInterval(pollInterval); }
		if (progressAnimInterval) { clearInterval(progressAnimInterval); }

		// Start smooth animation interval (runs every 50ms)
		progressAnimInterval = setInterval(animateProgress, 50);

		// Start server polling (every 500ms)
		pollInterval = setInterval(updateProgress, 500);
		updateProgress();
	}

	$(document).on('click', '#run-media-scan', function(e){
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);

		// Prevent double clicks
		if ($btn.prop('disabled')) {
			return false;
		}

		// Reset progress variables
		clientProgress = 0;
		targetProgress = 5; // Start with 5%
		currentStepName = 'Starting...';
		syncTriggered = false;
		stuckChecks = 0;

		// Show progress bar with animation
		var $progress = $('#media-scan-progress');
		$progress.css('display', 'block').hide().fadeIn(300);

		$('#media-scan-progress-fill').css('width', '0%');
		$('#media-scan-progress-text').text('Scan status: Starting... (0%)');
		$btn.prop('disabled', true).html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0; vertical-align:middle;"></span> Starting...');

		// Prevent tab closing
		$(window).on('beforeunload.mediaTrackerScan', function() {
			return 'Scanning in progress. Please do not close this tab.';
		});

		$.post(AJAX_URL, {
			action: 'run_media_scan',
			nonce: NONCE
		}).done(function(res){
			$btn.html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0; vertical-align:middle;"></span> Scanning...');

			// Start smooth animation immediately
			clientProgress = 0;
			targetProgress = 10; // Jump to 10% quickly
			animateProgress();

			startPolling();
		}).fail(function(xhr, status, error){
			$('#media-scan-progress-text').html('❌ <strong>Error:</strong> ' + (xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to start scan. Please refresh and try again.'));
			setTimeout(function(){
				$('#media-scan-progress').fadeOut(300);
			}, 3000);
			ensureScanButtonExists('Scan Unused Media').prop('disabled', false);

			// Remove tab closing prevention
			$(window).off('beforeunload.mediaTrackerScan');
		});

		return false;
	});

	// Handle Remove All Unused Media button click
	$(document).on('click', '#remove-all-unused-media', function(e){
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);

		// Prevent double clicks
		if ($btn.prop('disabled')) {
			return false;
		}

		// Show confirmation alert
		if (!confirm('Are you sure you want to delete all unused media? This action cannot be undone. Continue?')) {
			return false;
		}

		$btn.prop('disabled', true).text('Deleting...');

		// Show progress bar
		var $progress = $('#media-scan-progress');
		$progress.css('display', 'block').hide().fadeIn(300);
		$('#media-scan-progress-text').text('Deleting all unused media...');
		$('#media-scan-progress-fill').css('width', '0%');

		$.post(AJAX_URL, {
			action: 'remove_all_unused_media',
			nonce: NONCE
		}).done(function(res){
			if (res && res.success) {
				$('#media-scan-progress-text').html('✅ <strong>Success!</strong> - ' + (res.data.message || 'All unused media has been deleted.'));
				$('#media-scan-progress-fill').css('width', '100%');
				$('#unused-count').text('0');

				// Refresh the list after 2 seconds
				setTimeout(function(){
					location.reload();
				}, 2000);
			} else {
				throw new Error(res.data && res.data.message || 'Unknown error');
			}
		}).fail(function(xhr, status, error){
			var errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete unused media.';
			$('#media-scan-progress-text').html('❌ <strong>Error:</strong> ' + errorMsg);
			setTimeout(function(){
				$('#media-scan-progress').fadeOut(300);
			}, 3000);
			$btn.prop('disabled', false).text('Remove all unused media');
		});

		return false;
	});

	// Removed auto polling on page load to prevent auto-rescan.
})(jQuery);
</script>
