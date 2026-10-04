jQuery(document).ready(function($) {
    /**
     * Deactivate Popup Modal
     * */
    var deactivateLink = $('#the-list').find('[data-slug="media-tracker"] .deactivate a');

    // Check if wp.i18n is available for translations
    var __ = window.wp && window.wp.i18n && window.wp.i18n.__
        ? window.wp.i18n.__
        : function(text, domain) {
            return text;
        };

    if (deactivateLink.length) {
        deactivateLink.on('click', function(e) {
            e.preventDefault();
            $('#mt-feedback-modal').show();
        });
    }

    $('#mt-submit-feedback').on('click', function() {
        var feedback = $('textarea[name="feedback"]').val();

        $.post(mediaTracker.ajax_url, {
            action: 'mt_save_feedback',
            feedback: feedback,
            nonce: mediaTracker.nonce
        }, function(response) {
            if (response.success) {
                window.location.href = deactivateLink.attr('href');
            } else {
                alert('There was an error. Please try again.');
            }
        });
    });

    $('#mt-skip-feedback').on('click', function() {
        window.location.href = deactivateLink.attr('href');
    });

    // Close modal when clicking outside of it
    $(window).on('click', function(e) {
        if ($(e.target).is('#mt-feedback-modal')) {
            $('#mt-feedback-modal').hide();
        }
    });

    // Optional: Add a close button inside the modal if you prefer
    $('#mt-feedback-modal').append('<span class="close">&times;</span>');
    $('#mt-feedback-modal .close').on('click', function() {
        $('#mt-feedback-modal').hide();
    });


    // Broken Media Link Detection: quick-edit-form
    $('.editinline').on('click', function(e) {
        e.preventDefault();

        const classList = $(this).attr('class').split(/\s+/);
        let targetClass = '';

        classList.forEach(function(cls) {
            if (cls.startsWith('quick-edit-item-')) {
                targetClass = cls;
            }
        });

        $('.quick-edit-form').addClass('hidden');
        $('.' + targetClass).removeClass('hidden');
    });

    $('.quick-edit-form .cancel').on('click', function() {
        $(this).closest('.quick-edit-form').addClass('hidden');
    });

    // clear-broken-links-transient
    $('#clear-broken-links-transient').on('click', function(e) {
        e.preventDefault();
        var button = $(this);

        $.ajax({
            url: mediaTracker.ajax_url,
            type: 'POST',
            data: {
                action: 'clear_broken_links_transient',
                nonce: mediaTracker.nonce
            },
            beforeSend: function() {
                button.text('Clearing...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    if (response.data) {
                        // Show success message
                        $('#success-message').text('Transient cache cleared successfully!').fadeIn();

                        // Reload after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            },
            complete: function() {
                button.text('Clear Transient Cache').prop('disabled', false);
            }
        });
    });

    // Move screen elements to proper location
    function initializeScreenOptions() {
        var $screenMetaLinks = $('#screen-meta-links');
        var $screenOptions = $('#screen-options');
        var $wpbody = $('#wpbody-content');

        // Show and move screen meta links
        $screenMetaLinks.show().insertBefore($wpbody);

        // Move screen options panel
        $screenOptions.insertAfter($screenMetaLinks);
    }

    // Initialize
    initializeScreenOptions();

    // Handle screen options toggle - WordPress native behavior
    $(document).on('click', '#show-settings-link', function(e) {
        e.preventDefault();
        var $button = $(this);
        var panel = $('#screen-options');

        // Toggle aria-expanded
        var isExpanded = $button.attr('aria-expanded') === 'true';
        $button.attr('aria-expanded', !isExpanded);

        // Toggle visibility with animation
        if (!isExpanded) {
            panel.removeClass('hidden').slideDown('fast', function() {
                $button.addClass('toggle-arrow-on');
            });
        } else {
            panel.slideUp('fast', function() {
                panel.addClass('hidden');
                $button.removeClass('toggle-arrow-on');
            });
        }
    });

    // Handle form submission via AJAX
    $(document).on('submit', '#unused-media-screen-options-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        var perPage = $('#unused_media_per_page').val();
        var nonce = $('#unused_media_screen_options_nonce').val();
        var $input = $('#unused_media_per_page');

        // Validate input
        if (!perPage || perPage < 1 || perPage > 999) {
            // Add error styling and shake animation
            $input.addClass('error').focus();

            // Shake animation
            $input.parent().addClass('shake');
            setTimeout(function() {
                $input.removeClass('error');
                $input.parent().removeClass('shake');
            }, 500);

            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('button-disabled')
               .data('original-text', originalText)
               .text(__('Applying...', 'media-tracker'));

        // AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'unused_media_save_screen_options',
                nonce: nonce,
                per_page: perPage
            },
            success: function(response) {
                if (response.success) {
                    // Check if value was unchanged
                    if (response.data && response.data.unchanged) {
                        // Value unchanged - show brief success and close panel
                        $button.text(__('Settings unchanged', 'media-tracker'))
                               .removeClass('button-primary')
                               .addClass('button-success');

                        setTimeout(function() {
                            // Close screen options panel
                            $('#show-settings-link').trigger('click');

                            // Reset button
                            $button.prop('disabled', false)
                                   .removeClass('button-disabled button-success')
                                   .addClass('button-primary')
                                   .text(originalText);
                        }, 800);
                    } else {
                        // Success feedback
                        $button.text(__('Applied!', 'media-tracker'))
                               .removeClass('button-primary')
                               .addClass('button-success');

                        // Reload after brief delay
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }
                } else {
                    // Error handling
                    var errorMsg = response.data && response.data.message
                        ? response.data.message
                        : __('Error saving settings. Please try again.', 'media-tracker');

                    $button.prop('disabled', false)
                           .removeClass('button-disabled')
                           .text(originalText);

                    // Show error with visual feedback
                    $input.addClass('error').focus();
                    setTimeout(function() {
                        $input.removeClass('error');
                    }, 2000);

                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $button.prop('disabled', false)
                       .removeClass('button-disabled')
                       .text(originalText);

                alert(__('Network error. Please check your connection and try again.', 'media-tracker'));
            }
        });
    });

    // Handle duplicate media form submission via AJAX
    $(document).on('submit', '#duplicate-media-screen-options-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.text();
        var perPage = $('#duplicate_media_per_page').val();
        var nonce = $('#duplicate_media_screen_options_nonce').val();
        var $input = $('#duplicate_media_per_page');

        // Validate input
        if (!perPage || perPage < 1 || perPage > 999) {
            // Add error styling and shake animation
            $input.addClass('error').focus();

            // Shake animation
            $input.parent().addClass('shake');
            setTimeout(function() {
                $input.removeClass('error');
                $input.parent().removeClass('shake');
            }, 500);

            return;
        }

        // Show loading state
        $button.prop('disabled', true)
               .addClass('button-disabled')
               .data('original-text', originalText)
               .text(__('Applying...', 'media-tracker'));

        // AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'duplicate_media_save_screen_options',
                nonce: nonce,
                per_page: perPage
            },
            success: function(response) {
                if (response.success) {
                    // Check if value was unchanged
                    if (response.data && response.data.unchanged) {
                        // Value unchanged - show brief success and close panel
                        $button.text(__('Settings unchanged', 'media-tracker'))
                               .removeClass('button-primary')
                               .addClass('button-success');

                        setTimeout(function() {
                            // Close screen options panel
                            $('#show-settings-link').trigger('click');

                            // Reset button
                            $button.prop('disabled', false)
                                   .removeClass('button-disabled button-success')
                                   .addClass('button-primary')
                                   .text(originalText);
                        }, 800);
                    } else {
                        // Success feedback
                        $button.text(__('Applied!', 'media-tracker'))
                               .removeClass('button-primary')
                               .addClass('button-success');

                        // Reload after brief delay
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }
                } else {
                    // Error handling
                    var errorMsg = response.data && response.data.message
                        ? response.data.message
                        : __('Error saving settings. Please try again.', 'media-tracker');

                    $button.prop('disabled', false)
                           .removeClass('button-disabled')
                           .text(originalText);

                    // Show error with visual feedback
                    $input.addClass('error').focus();
                    setTimeout(function() {
                        $input.removeClass('error');
                    }, 2000);

                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                $button.prop('disabled', false)
                       .removeClass('button-disabled')
                       .text(originalText);

                alert(__('Network error. Please check your connection and try again.', 'media-tracker'));
            }
        });
    });

    // Handle Enter key in number input
    $(document).on('keydown', '#unused_media_per_page', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $(this).closest('form').submit();
        }
    });

    // Visual feedback on focus
    $(document).on('focus', '#unused_media_per_page', function() {
        $(this).parent('.screen-options-per-page').addClass('focused');
    }).on('blur', '#unused_media_per_page', function() {
        $(this).parent('.screen-options-per-page').removeClass('focused');
    });

    // Handle Enter key in duplicate media number input
    $(document).on('keydown', '#duplicate_media_per_page', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $(this).closest('form').submit();
        }
    });

    // Visual feedback on focus for duplicate media
    $(document).on('focus', '#duplicate_media_per_page', function() {
        $(this).parent('.screen-options-per-page').addClass('focused');
    }).on('blur', '#duplicate_media_per_page', function() {
        $(this).parent('.screen-options-per-page').removeClass('focused');
    });

    /**
     * Unused Media scan / remove-all controls
     * (moved from includes/Admin/views/unused-media-list.php)
     * Only active on the Unused Media page.
     */
    if ($('.unused-media-list').length) {
        // Ensure mediaTracker and ajaxUrl are defined before using
        if (typeof window.mediaTracker === 'undefined') {
            window.mediaTracker = {
                ajax_url: '',
                nonce: ''
            };
        }
        var AJAX_URL = (window.mediaTracker && (window.mediaTracker.ajax_url || window.mediaTracker.ajaxUrl)) || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');

        var NONCE = (window.mediaTracker && window.mediaTracker.nonce) || '';
        var pollInterval = null;
        var stuckChecks = 0; // consecutive polls stuck near start
        var syncTriggered = false;
        var clientProgress = 0; // Client-side progress simulation
        var targetProgress = 0; // Target progress from server
        var currentStepName = 'Starting...';
        var progressAnimInterval = null; // Animation interval

        // Query params used by the count refresh (previously injected via PHP)
        var mtPageParams = new URLSearchParams(window.location.search);

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
                            search: mtPageParams.get('s') || '',
                            author_id: parseInt(mtPageParams.get('author'), 10) || ''
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
            $btn.prop('disabled', true).html('<span class="spinner is-active mt-spinner-inline"></span> Starting...');

            // Prevent tab closing
            $(window).on('beforeunload.mediaTrackerScan', function() {
                return 'Scanning in progress. Please do not close this tab.';
            });

            $.post(AJAX_URL, {
                action: 'run_media_scan',
                nonce: NONCE
            }).done(function(res){
                $btn.html('<span class="spinner is-active mt-spinner-inline"></span> Scanning...');

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
    }
});