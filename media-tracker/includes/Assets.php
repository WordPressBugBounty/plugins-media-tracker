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
}
