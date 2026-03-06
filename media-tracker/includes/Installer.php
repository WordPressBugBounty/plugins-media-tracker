<?php

namespace Media_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Installer class
 */
class Installer {

    /**
     * Run the installer
     *
     * @since   1.0.0
     * @access  public
     * @param   none
     * @return  void
     */
    public function run() {
        $this->add_version();
        $this->optimize_database_indexes();
        $this->schedule_cron_jobs();
    }

    /**
     * Schedule cron jobs for background processing
     *
     * @since   1.2.3
     * @access  public
     * @param   none
     * @return  void
     */
    public function schedule_cron_jobs() {
        // Schedule the batch processing cron job
        if (!wp_next_scheduled('media_tracker_batch_process')) {
            // Prefer the faster schedule when available
            $interval = 'five_minutes';
            $schedules = wp_get_schedules();
            if (!isset($schedules[$interval])) {
                $interval = 'hourly';
            }
            wp_schedule_event(time(), $interval, 'media_tracker_batch_process');
        }
    }

    /**
     * Clear scheduled cron jobs on deactivation
     *
     * @since   1.2.3
     * @access  public
     * @param   none
     * @return  void
     */
    public static function clear_cron_jobs() {
        // Clear the batch processing cron job
        $timestamp = wp_next_scheduled('media_tracker_batch_process');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'media_tracker_batch_process');
        }

        // Also clear any scheduled hooks that might have been set
        wp_clear_scheduled_hook('media_tracker_batch_process');
    }

    /**
     * Optimize database indexes for better performance
     *
     * @since   1.1.1
     * @access  public
     * @param   none
     * @return  void
     */
    public function optimize_database_indexes() {
        global $wpdb;

        // Check if indexes already exist to avoid duplicate creation
        $indexes_created = get_option( 'media_tracker_indexes_created', false );

        if ( ! $indexes_created ) {
            // MySQL-compatible indexes (no partial WHERE clauses)
            // Speed up duplicate hash aggregation: index on meta_key and meta_value prefix
            // Note: meta_value is LONGTEXT; we index first 64 chars which matches hash length.
            // Create indexes only if they don't already exist (compatible with MySQL < 8)

            // Check if postmeta index exists - direct query necessary as no WP API available
            $existing_postmeta_idx = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'idx_postmeta_media_tracker_hash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( empty( $existing_postmeta_idx ) ) {
                // Create index for performance optimization - direct query necessary as no WP API available
                $wpdb->query( "CREATE INDEX idx_postmeta_media_tracker_hash ON {$wpdb->postmeta} (meta_key, meta_value(64))" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            }

            // Check if posts index exists - direct query necessary as no WP API available
            $existing_posts_idx = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = 'idx_posts_type_status'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( empty( $existing_posts_idx ) ) {
                // Create index for performance optimization - direct query necessary as no WP API available
                $wpdb->query( "CREATE INDEX idx_posts_type_status ON {$wpdb->posts} (post_type, post_status)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            }

            // New: index to accelerate attachment/mime filtering used by duplicate queries
            // Check if posts mime index exists - direct query necessary as no WP API available
            $existing_posts_mime_idx = $wpdb->get_results( "SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = 'idx_posts_type_mime'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( empty( $existing_posts_mime_idx ) ) {
                // Create index for performance optimization - direct query necessary as no WP API available
                $wpdb->query( "CREATE INDEX idx_posts_type_mime ON {$wpdb->posts} (post_type, post_mime_type)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
            }

            update_option( 'media_tracker_indexes_created', true );
        }
    }

    /**
     * Add time and version on DB
     *
     * @since   1.0.0
     * @access  public
     * @param   none
     * @return  void
     */
    public function add_version() {
        $installed = get_option( 'media_tracker_installed' );

        if ( ! $installed ) {
            update_option( 'media_tracker_installed', time() );
        }

        update_option( 'media_tracker_version', MEDIA_TRACKER_VERSION );
    }

    public static function deactivate() {
        add_action( 'admin_footer', array( __CLASS__, 'feedback_modal_html' ) );
    }

    /**
     * AJAX handler to save feedback
     */
    public static function save_feedback() {
        check_ajax_referer( 'media_tracker_nonce', 'nonce' );

        $feedback = isset($_POST['feedback']) ? sanitize_textarea_field(wp_unslash($_POST['feedback'])) : '';

        if ( ! empty( $feedback ) ) {
            $to = 'hello@thebitcraft.com';
            $subject = __( 'Media Tracker Plugin Feedback', 'media-tracker' );
            $message = "Feedback:\n\n" . $feedback;
            $headers = array( 'Content-Type: text/plain; charset=UTF-8' );

            wp_mail( $to, $subject, $message, $headers );

            wp_send_json_success();
        }
    }

    /**
     * Output HTML for feedback modal
     */
    public static function feedback_modal_html() { ?>
        <div id="mt-feedback-modal">
            <div class="mt-feedback-modal-content">
                <header class="mt-feedback-modal-header">
                    <span class="close">&times;</span>
                    <h3><?php esc_html_e( "If you have a moment, we'd love to know why you're deactivating the Media Tracker plugin!", "media-tracker" ); ?></h3>
                </header>

                <div class="mt-feedback-modal-body">
                    <textarea name="feedback" placeholder="<?php esc_html_e( 'Enter your feedback here...', 'media-tracker' ) ?>"></textarea>
                </div>

                <footer class="mt-feedback-modal-footer">
                    <button id="mt-skip-feedback"><?php esc_html_e( 'Skip & Deactivate', 'media-tracker' ); ?></button>
                    <button id="mt-submit-feedback"><?php esc_html_e( 'Submit & Deactivate', 'media-tracker' ); ?></button>
                </footer>
            </div>
        </div>
        <?php
    }
}
