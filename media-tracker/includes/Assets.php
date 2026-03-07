<?php

namespace Media_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * The admin class
 */
class Assets {

    /**
     * Initialize the class
     */
    function __construct() {
        $this->register_admin_assets();
    }

    /**
     * Register admin assets
     */
    function register_admin_assets() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_script' ) );
        add_action( 'wp_ajax_mt_save_feedback', array( '\Media_Tracker\Installer', 'save_feedback' ) );
        add_action( 'wp_ajax_unused_media_save_screen_options', array( $this, 'unused_media_handle_screen_options_save' ) );
        add_action( 'wp_ajax_duplicate_media_save_screen_options', array( $this, 'duplicate_media_handle_screen_options_save' ) );
        add_action( 'current_screen', function( $screen ) {
            if ( $screen && ( $screen->id === 'plugins' || $screen->id === 'plugins-network' ) ) {
                \Media_Tracker\Installer::deactivate();
            }
        } );
    }

        /**
     * Register necessary CSS and JS
     * @ Admin
     */
    public function admin_script( $hook ) {
        wp_enqueue_style( 'mt-admin-style', MEDIA_TRACKER_URL . '/assets/dist/css/mt-admin.css', false, MEDIA_TRACKER_VERSION );
        wp_enqueue_script( 'mt-admin-script', MEDIA_TRACKER_URL . '/assets/dist/js/mt-admin.js', array( 'jquery' ), MEDIA_TRACKER_VERSION, true );

        $data = array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'media_tracker_nonce' ),
            'i18n'     => array(
                'rescan_confirm' => __( 'Image hashes will be refreshed and all images will be re-scanned. Continue?', 'media-tracker' ),
                'rescanning'     => __( 'Re-scanning...', 'media-tracker' ),
                'rescan_error'   => __( 'Error re-scanning images', 'media-tracker' ),
            ),
        );

        // Only load on Media Tracker pages (all subpages)
        if ( strpos( $hook, 'media_page_media-tracker' ) === 0 ) {
            $data['base_url'] = admin_url( 'upload.php?page=media-tracker' );

            // Enqueue tab navigation script
            wp_enqueue_script( 'media-tracker-tab', MEDIA_TRACKER_ASSETS . '/dist/js/tab.js', array( 'jquery' ), MEDIA_TRACKER_VERSION, true );

            // Enqueue Pro lock overlay styles (only if Pro is not active)
            wp_enqueue_style( 'media-tracker-pro-lock', MEDIA_TRACKER_ASSETS . '/dist/css/pro-lock.css', array(), MEDIA_TRACKER_VERSION );
        }

        wp_localize_script( 'mt-admin-script', 'mediaTracker', $data );
    }

    /**
     * Handle screen options save via AJAX
     *
     * @return void
     */
    public function unused_media_handle_screen_options_save() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'unused_media_screen_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'media-tracker' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'You do not have permission to perform this action.', 'media-tracker' ) )
            );
        }

        // Get and validate per page value
        if ( ! isset( $_POST['per_page'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing per page value.', 'media-tracker' ) ) );
        }

        $per_page = intval( $_POST['per_page'] );
        if ( $per_page < 1 || $per_page > 999 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid per page value. Must be between 1 and 999.', 'media-tracker' ) ) );
        }

        // Save to user meta
        $user_id = get_current_user_id();
        $current_value = get_user_meta( $user_id, 'unused_media_cleaner_per_page', true );
        if ( $current_value == $per_page ) {
            wp_send_json_success( array(
                'message' => __( 'Settings saved successfully.', 'media-tracker' ),
                'unchanged' => true
            ) );
        }

        $updated = update_user_meta( $user_id, 'unused_media_cleaner_per_page', $per_page );
        if ( $updated ) {
            wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'media-tracker' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save settings.', 'media-tracker' ) ) );
        }
    }

    /**
     * Handle duplicate media screen options save via AJAX
     *
     * @return void
     */
    public function duplicate_media_handle_screen_options_save() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'duplicate_media_screen_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid nonce.', 'media-tracker' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'You do not have permission to perform this action.', 'media-tracker' ) )
            );
        }

        // Get and validate per page value
        if ( ! isset( $_POST['per_page'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing per page value.', 'media-tracker' ) ) );
        }

        $per_page = intval( $_POST['per_page'] );
        if ( $per_page < 1 || $per_page > 999 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid per page value. Must be between 1 and 999.', 'media-tracker' ) ) );
        }

        // Save to user meta
        $user_id = get_current_user_id();
        $current_value = get_user_meta( $user_id, 'duplicate_media_per_page', true );
        if ( $current_value == $per_page ) {
            wp_send_json_success( array(
                'message' => __( 'Settings saved successfully.', 'media-tracker' ),
                'unchanged' => true
            ) );
        }

        $updated = update_user_meta( $user_id, 'duplicate_media_per_page', $per_page );
        if ( $updated ) {
            wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'media-tracker' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save settings.', 'media-tracker' ) ) );
        }
    }
}
