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
                var knownTabs = ['overview', 'unused-media', 'duplicates', 'external-storage', 'optimization', 'security', 'multisite', 'settings', 'license'];
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

    if (connectionModalTest) {
        connectionModalTest.addEventListener('click', function() {
            alert('Test connection successful (demo).');
        });
    }

    if (connectionModalSave) {
        connectionModalSave.addEventListener('click', function() {
            alert('Connection saved (demo).');
            closeConnectionModal();
        });
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

        // Delete selected button
        $('#mt-dup-delete-selected').on('click', function(e) {
            e.preventDefault();
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
            btn.html('<span class="spinner is-active" style="float:none; margin:0 5px 0 0; visibility:visible;"></span> Scanning...');

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
                        status.text('Scan status: Scanning... (' + pct + '%)');

                        if (data.completed) {
                            status.html('✅ <strong>Scan Complete!</strong> (100%)');
                            progressBar.css('width', '100%');

                            // Remove tab closing prevention
                            $(window).off('beforeunload.mediaTrackerDupScan');

                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            // Continue processing next batch
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

})(jQuery);
