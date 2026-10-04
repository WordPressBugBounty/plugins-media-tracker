/**
 * Media Tracker - Tab Navigation & Modal
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

(function($) {
    'use strict';

    // Initialize Lucide icons
    if (window.lucide && typeof lucide.createIcons === 'function') {
        lucide.createIcons();
    }

    // Tab Navigation
    var navItems = document.querySelectorAll('aside nav li');
    var tabContents = document.querySelectorAll('.tab-content');

    function setActiveTab(target) {
        if (!target) return;

        navItems.forEach(function(i) {
            i.classList.remove('active');
        });

        tabContents.forEach(function(section) {
            section.classList.remove('active');
        });

        navItems.forEach(function(i) {
            if (i.getAttribute('data-tab') === target) {
                i.classList.add('active');
            }
        });

        var activeSection = document.getElementById('tab-' + target);
        if (activeSection) {
            activeSection.classList.add('active');
        }
    }

    function updateUrlForTab(target) {
        if (typeof URL === 'undefined' || !window.history || typeof window.history.replaceState !== 'function') {
            return;
        }
        var url = new URL(window.location.href);

        // Clean up URL parameters: keep only 'page' and 'tab'
        // This prevents parameter pollution (merging) when switching tabs
        var page = url.searchParams.get('page') || 'media-tracker';

        // Create new search params
        var newParams = new URLSearchParams();
        newParams.set('page', page);
        newParams.set('tab', target);

        // Update URL with clean params
        url.search = newParams.toString();

        window.history.replaceState({}, '', url.toString());
    }

    function handleTabChange(target) {
        if (!target) return;

        var slug = String(target).toLowerCase();
        var baseUrl = (typeof mediaTracker !== 'undefined' && mediaTracker.base_url)
            ? mediaTracker.base_url
            : window.location.pathname + '?page=media-tracker';

        if (baseUrl.indexOf('?') !== -1) {
            window.location.href = baseUrl + '&' + encodeURIComponent(slug);
        } else {
            window.location.href = baseUrl + '?' + encodeURIComponent(slug);
        }
    }

    // Initialize active tab from URL (only if not already set server-side)
    (function() {
        // Check if active classes are already set correctly by PHP
        var hasActiveNav = false;
        var hasActiveContent = false;

        navItems.forEach(function(i) {
            if (i.classList.contains('active')) {
                hasActiveNav = true;
            }
        });

        tabContents.forEach(function(section) {
            if (section.classList.contains('active')) {
                hasActiveContent = true;
            }
        });

        // Only run JavaScript initialization if PHP didn't set active classes
        if (!hasActiveNav || !hasActiveContent) {
            if (typeof URL === 'undefined') {
                return;
            }
            var url = new URL(window.location.href);
            var initial = url.searchParams.get('tab');

            if (!initial) {
                var search = url.search || '';
                var knownTabs = ['overview', 'unused-media', 'duplicates'];
                for (var i = 0; i < knownTabs.length; i++) {
                    if (search.indexOf(knownTabs[i]) !== -1) {
                        initial = knownTabs[i];
                        break;
                    }
                }
            }

            if (initial) {
                setActiveTab(initial);
            }
        }
    })();

    // Connection Modal
    var addConnectionButton = document.getElementById('btn-add-connection');
    var connectionModal = document.getElementById('connection-modal');
    var connectionModalClose = document.getElementById('connection-modal-close');
    var connectionModalCancel = document.getElementById('connection-modal-cancel');
    var connectionModalTest = document.getElementById('connection-modal-test');
    var connectionModalSave = document.getElementById('connection-modal-save');

    function openConnectionModal() {
        if (connectionModal) {
            connectionModal.classList.add('active');
        }
    }

    function closeConnectionModal() {
        if (connectionModal) {
            connectionModal.classList.remove('active');
        }
    }

    if (addConnectionButton && connectionModal) {
        addConnectionButton.addEventListener('click', openConnectionModal);
    }

    if (connectionModalClose) {
        connectionModalClose.addEventListener('click', closeConnectionModal);
    }

    if (connectionModalCancel) {
        connectionModalCancel.addEventListener('click', closeConnectionModal);
    }

    // Duplicate Media Management
    $(function() {
        // Select all checkbox
        $('#mt-dup-select-all').on('change', function() {
            var checked = $(this).is(':checked');
            $('#mt-duplicate-form').find('tbody input[type="checkbox"]').prop('checked', checked);
        });

        // Delete duplicates function
        function mtDeleteDuplicates(ids) {
            if (!ids.length) {
                return;
            }
            if (!confirm('Are you sure you want to delete the selected duplicate images?')) {
                return;
            }

            var nonce = (window.mediaTracker && window.mediaTracker.nonce) ? window.mediaTracker.nonce : '';

            $.post((window.mediaTracker && window.mediaTracker.ajax_url) || ajaxurl, {
                action: 'mt_delete_duplicate_images',
                nonce: nonce,
                attachment_ids: ids
            }).done(function(res) {
                if (res && res.success) {
                    location.reload();
                } else if (res && res.data && res.data.message) {
                    alert(res.data.message);
                } else {
                    alert('Failed to delete duplicate images.');
                }
            }).fail(function() {
                alert('Failed to delete duplicate images.');
            });
        }

        // Bulk actions Apply buttons (top + bottom)
        $('#doaction, #doaction2').on('click', function(e) {
            e.preventDefault();
            var isBottom = $(this).attr('id') === 'doaction2';
            var action = isBottom ? $('#bulk-action-selector-bottom').val() : $('#bulk-action-selector-top').val();
            if (action !== 'delete') {
                return;
            }
            var ids = [];
            $('#mt-duplicate-form').find('tbody input[type="checkbox"]:checked').each(function() {
                ids.push($(this).val());
            });
            mtDeleteDuplicates(ids);
        });

        // Single delete button
        $(document).on('click', '.mt-dup-delete-single', function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            if (!id) return;
            mtDeleteDuplicates([id]);
        });

        // Scan duplicates button
        $('#mt-dup-scan').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);

            if (btn.data('running')) {
                return;
            }

            if (!confirm('Re-scan all media for duplicates? This may take some time on large libraries.')) {
                return;
            }

            var originalText = btn.html();
            btn.data('running', true).prop('disabled', true);
            // Add spinner as requested
            btn.html('<span class="spinner is-active mt-spinner-inline"></span> Scanning...');

            $('.mt-dup-wrap').show();

            var progressWrap = $('#mt-dup-progress');
            var progressBar = progressWrap.find('.mt-dup-progress-bar');
            var status = $('.mt-dup-scan-status');

            progressBar.stop(true).css('width', '0%');
            progressWrap.show();
            status.show().text('Scan status: Starting... (0%)');

            var nonce = (window.mediaTracker && window.mediaTracker.nonce) ? window.mediaTracker.nonce : '';

            // Prevent tab closing
            $(window).on('beforeunload.mediaTrackerDupScan', function() {
                return 'Scanning in progress. Please do not close this tab.';
            });

            // Step 1: Reset hashes and start
            $.post((window.mediaTracker && window.mediaTracker.ajax_url) || ajaxurl, {
                action: 'reset_duplicate_hashes',
                nonce: nonce
            }).done(function(res) {
                if (res.success) {
                    // Start recursive batch processing
                    processBatch();
                } else {
                    handleError('Failed to initialize scan.');
                }
            }).fail(function() {
                handleError('Failed to initialize scan.');
            });

            function processBatch() {
                $.post((window.mediaTracker && window.mediaTracker.ajax_url) || ajaxurl, {
                    action: 'mt_process_batch',
                    nonce: nonce
                }).done(function(res) {
                    if (res.success) {
                        var data = res.data;
                        var pct = data.percentage;

                        progressBar.css('width', pct + '%');

                        // Enhanced status message with ETA
                        var statusText = 'Scan status: Scanning... (' + pct + '%) - ' + data.processed + ' / ' + data.total + ' images';
                        if (data.eta && data.eta !== 'Complete!') {
                            statusText += ' <span class="mt-eta-text">(ETA: ' + data.eta + ')</span>';
                        }
                        status.html(statusText);

                        if (data.completed) {
                            status.html('✅ <strong>Scan Complete!</strong> (100%)');
                            progressBar.css('width', '100%');

                            // Remove tab closing prevention
                            $(window).off('beforeunload.mediaTrackerDupScan');

                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            // Continue processing next batch immediately (no delay for faster scanning)
                            processBatch();
                        }
                    } else {
                        handleError(res.data.message || 'Error processing batch.');
                    }
                }).fail(function() {
                    handleError('Network error during scan.');
                });
            }

            function handleError(msg) {
                status.html('❌ <strong>Error:</strong> ' + msg);
                // Restore button state
                btn.data('running', false).prop('disabled', false).html(originalText);

                // Remove tab closing prevention
                $(window).off('beforeunload.mediaTrackerDupScan');

                // Hide progress bar after delay
                setTimeout(function() {
                    progressWrap.fadeOut(300, function() {
                        status.text('Scan status: Ready to scan...');
                    });
                }, 3000);
            }
        });
    });

    // Overview (Dashboard) page — moved from includes/Admin/views/tabs/tab-overview.php
    $(function() {
        // i18n helper (same fallback pattern as mt-admin.js)
        var __ = window.wp && window.wp.i18n && window.wp.i18n.__
            ? window.wp.i18n.__
            : function(text, domain) {
                return text;
            };

        // Most Used Media loader (only when stats are not cached)
        if ($('#media-tracker-loading-stats').length) {
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
                        $('#media-tracker-most-used-container').html('<p class="mt-empty-state">' + (response.data || 'Error loading stats.') + '</p>');
                    }
                },
                error: function() {
                    $('#media-tracker-most-used-container').html('<p class="mt-empty-state">Error loading stats.</p>');
                }
            });
        }

        // Refresh stats button handler
        $('#media-tracker-refresh-stats').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $icon = $button.find('.dashicons-update');
            var originalText = $button.html();

            // Show loading state
            $button.prop('disabled', true);
            $icon.addClass('spin-animation');
            $button.html('<span class="spinner is-active mt-spinner-inline"></span> ' + __('Refreshing...', 'media-tracker'));

            // AJAX request to refresh stats
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'media_tracker_refresh_used_stats',
                    nonce: $button.data('nonce')
                },
                success: function(response) {
                    if (response.success) {
                        // Update the count
                        var countElement = $button.closest('.card').find('.value');
                        var formattedCount = response.data.used_count;
                        countElement.text(formattedCount);

                        // Update the size
                        var sizeElement = $button.closest('.card').find('span').last();
                        var currentSizeText = sizeElement.text().split(':')[0];
                        sizeElement.html(currentSizeText + ': ' + response.data.used_size_formatted);

                        // Show success message
                        $button.html('<i class="dashicons dashicons-yes mt-btn-icon"></i> ' + __('Refreshed!', 'media-tracker'));
                        setTimeout(function() {
                            $button.html(originalText);
                            $button.prop('disabled', false);
                        }, 2000);
                    } else {
                        // Show error
                        $button.html('<i class="dashicons dashicons-no mt-btn-icon"></i> ' + __('Error', 'media-tracker'));
                        setTimeout(function() {
                            $button.html(originalText);
                            $button.prop('disabled', false);
                        }, 2000);
                    }
                },
                error: function() {
                    // Show error
                    $button.html('<i class="dashicons dashicons-no mt-btn-icon"></i> ' + __('Error', 'media-tracker'));
                    setTimeout(function() {
                        $button.html(originalText);
                        $button.prop('disabled', false);
                    }, 2000);
                }
            });
        });
    });

})(jQuery);
