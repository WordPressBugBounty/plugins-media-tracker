jQuery(document).ready(function($) {
    /**
     * Deactivate Popup Modal
     * */
    var deactivateLink = $('#the-list').find('[data-slug="media-tracker"] .deactivate a');

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
});
