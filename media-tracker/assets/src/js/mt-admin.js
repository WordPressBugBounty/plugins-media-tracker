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

    // Media Duplicate Filter - Auto trigger filter on change
    $('#media-duplicate-filter').on('change', function() {
        // Trigger the filter button click programmatically
        $('#post-query-submit').click();
    });

    // Duplicate Images Re-scan Button
    $('#rescan-duplicates-btn').on('click', function() {
        if (!confirm(mediaTracker.i18n.rescan_confirm || 'Image hashes will be refreshed and all images will be re-scanned. Continue?')) {
            return;
        }

        var button = $(this);
        var status = $('#rescan-status');

        button.prop('disabled', true);
        status.html('<span class="spinner is-active"></span> ' + (mediaTracker.i18n.rescanning || 'Re-scanning...')).show();

        $.ajax({
            url: mediaTracker.ajax_url,
            type: 'POST',
            data: {
                action: 'reset_duplicate_hashes',
                nonce: mediaTracker.nonce
            },
            success: function(response) {
                if (response.success) {
                    status.html(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    status.html('<span style="color:red;">' + (mediaTracker.i18n.rescan_error || 'Error re-scanning images') + '</span>');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                status.html('<span style="color:red;">' + (mediaTracker.i18n.rescan_error || 'Error re-scanning images') + '</span>');
                button.prop('disabled', false);
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
});