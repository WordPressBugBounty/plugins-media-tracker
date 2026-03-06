<?php

namespace Media_Tracker\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Menu
 *
 * Handles administration menu and AJAX functionality for Media Tracker plugin.
 */
class Menu {

    /**
     * Menu constructor.
     *
     * Adds necessary actions and filters upon initialization.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_media_tracker_menu' ) );

        // Handle AJAX: clear plugin-related transients/cache
        add_action( 'wp_ajax_clear_broken_links_transient', array( $this, 'handle_clear_transient' ) );

        // Handle AJAX: get media scan progress
        add_action( 'wp_ajax_get_media_scan_progress', array( $this, 'handle_get_scan_progress' ) );

        // Handle AJAX: run media scan in background (start)
        add_action( 'wp_ajax_run_media_scan', array( $this, 'handle_run_media_scan' ) );

        // NEW: Handle AJAX: run media scan synchronously (fallback if cron stalls)
        add_action( 'wp_ajax_run_media_scan_sync', array( $this, 'handle_run_media_scan_sync' ) );

        // Background cron-like task to perform the scan asynchronously
        add_action( 'media_tracker_run_media_scan_bg', array( $this, 'run_scan_bg' ), 10, 1 );

        // Handle AJAX: clear scan progress once complete
        add_action( 'wp_ajax_clear_media_scan_progress', array( $this, 'handle_clear_scan_progress' ) );

        // Handle AJAX: get unused media count
        add_action( 'wp_ajax_get_unused_media_count', array( $this, 'handle_get_unused_media_count' ) );

        // Handle AJAX: remove all unused media
        add_action( 'wp_ajax_remove_all_unused_media', array( $this, 'handle_remove_all_unused_media' ) );

        // Hook into option updates to clear cache when site icon or theme mods change
        add_action( 'updated_option', array( $this, 'handle_option_update' ), 10, 3 );
    }

    /**
     * Clear unused media cache when site icon or theme settings change.
     *
     * @param string $option    Option name.
     * @param mixed  $old_value Old option value.
     * @param mixed  $new_value New option value.
     */
    public function handle_option_update( $option, $old_value, $new_value ) {
        $ids_to_remove = [];

        if ( 'site_icon' === $option && ! empty( $new_value ) ) {
            $ids_to_remove[] = intval( $new_value );
        } elseif ( 0 === strpos( $option, 'theme_mods_' ) ) {
            // Theme mods updated
            $mods = $new_value;
            if ( is_array( $mods ) ) {
                if ( ! empty( $mods['custom_logo'] ) ) {
                    $ids_to_remove[] = intval( $mods['custom_logo'] );
                }
                if ( ! empty( $mods['background_image_thumb'] ) ) {
                    $ids_to_remove[] = intval( $mods['background_image_thumb'] );
                }
                if ( ! empty( $mods['header_image_data'] ) && is_array( $mods['header_image_data'] ) && isset( $mods['header_image_data']['attachment_id'] ) ) {
                    $ids_to_remove[] = intval( $mods['header_image_data']['attachment_id'] );
                }
            }
        }

        if ( ! empty( $ids_to_remove ) ) {
            // Retrieve snapshot
            $snapshot = get_option( 'media_tracker_unused_ids_snapshot', array() );
            if ( ! empty( $snapshot ) ) {
                $original_count = count( $snapshot );
                $snapshot = array_diff( $snapshot, $ids_to_remove );

                if ( count( $snapshot ) !== $original_count ) {
                    // Update snapshot if changed
                    update_option( 'media_tracker_unused_ids_snapshot', $snapshot, false );
                }
            }

            // Also clear cache to be safe
            $list = new Unused_Media_List( '', null );
            if ( method_exists( $list, 'force_clear_cache' ) ) {
                $list->force_clear_cache();
            }
        }
    }

    /**
     * Add media page to WordPress admin menu.
     */
    public function register_media_tracker_menu() {
        // Main parent page - will show overview by default
        add_media_page(
            __( 'Media Tracker', 'media-tracker' ),
            __( 'Media Tracker', 'media-tracker' ),
            'manage_options',
            'media-tracker',
            array( $this, 'media_tracker_admin_page' )
        );

        // Add submenu pages for each tab
        add_submenu_page(
            null, // Parent slug - null to hide from sidebar menu
            __( 'Dashboard', 'media-tracker' ),
            __( 'Dashboard', 'media-tracker' ),
            'manage_options',
            'media-tracker-overview',
            array( $this, 'media_tracker_overview_page' )
        );

        add_submenu_page(
            null,
            __( 'Unused Media', 'media-tracker' ),
            __( 'Unused Media', 'media-tracker' ),
            'manage_options',
            'media-tracker-unused-media',
            array( $this, 'media_tracker_unused_media_page' )
        );

        add_submenu_page(
            null,
            __( 'Duplicate Media', 'media-tracker' ),
            __( 'Duplicate Media', 'media-tracker' ),
            'manage_options',
            'media-tracker-duplicates',
            array( $this, 'media_tracker_duplicates_page' )
        );

        add_submenu_page(
            null,
            __( 'External Storage', 'media-tracker' ),
            __( 'External Storage', 'media-tracker' ),
            'manage_options',
            'media-tracker-external-storage',
            array( $this, 'media_tracker_external_storage_page' )
        );

        add_submenu_page(
            null,
            __( 'Optimization', 'media-tracker' ),
            __( 'Optimization', 'media-tracker' ),
            'manage_options',
            'media-tracker-optimization',
            array( $this, 'media_tracker_optimization_page' )
        );

        add_submenu_page(
            null,
            __( 'Security & Logs', 'media-tracker' ),
            __( 'Security & Logs', 'media-tracker' ),
            'manage_options',
            'media-tracker-security',
            array( $this, 'media_tracker_security_page' )
        );

        add_submenu_page(
            null,
            __( 'Multi-site', 'media-tracker' ),
            __( 'Multi-site', 'media-tracker' ),
            'manage_options',
            'media-tracker-multisite',
            array( $this, 'media_tracker_multisite_page' )
        );

        add_submenu_page(
            null,
            __( 'Documents', 'media-tracker' ),
            __( 'Documents', 'media-tracker' ),
            'manage_options',
            'media-tracker-documents',
            array( $this, 'media_tracker_documents_page' )
        );

        if ( media_tracker_is_pro_active() ) {
            add_submenu_page(
                null,
                __( 'License', 'media-tracker' ),
                __( 'License', 'media-tracker' ),
                'manage_options',
                'media-tracker-license',
                array( $this, 'media_tracker_license_page' )
            );
        }
    }

    public function media_tracker_admin_page() {
        // Set the default tab to overview for main page
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required for determining the default tab.
        if ( ! isset( $_GET['tab'] ) ) {
            $_GET['tab'] = 'overview';
        }
        include __DIR__ . '/views/media-tracker.php';
    }

    public function media_tracker_overview_page() {
        $this->render_tab_page( 'overview' );
    }

    public function media_tracker_unused_media_page() {
        $this->render_tab_page( 'unused-media' );
    }

    public function media_tracker_duplicates_page() {
        $this->render_tab_page( 'duplicates' );
    }

    public function media_tracker_external_storage_page() {
        $this->render_tab_page( 'external-storage' );
    }

    public function media_tracker_optimization_page() {
        $this->render_tab_page( 'optimization' );
    }

    public function media_tracker_security_page() {
        $this->render_tab_page( 'security' );
    }

    public function media_tracker_multisite_page() {
        $this->render_tab_page( 'multisite' );
    }

    public function media_tracker_documents_page() {
        $this->render_tab_page( 'documents' );
    }

    public function media_tracker_license_page() {
        $this->render_tab_page( 'license' );
    }

    /**
     * Render individual tab page
     *
     * @param string $tab Tab slug to render
     */
    private function render_tab_page( $tab ) {
        // Set the current tab
        $_GET['tab'] = $tab;

        // Include the main layout which will only load the requested tab
        include __DIR__ . '/views/media-tracker.php';
    }

    public function handle_clear_transient() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'media-tracker' ) ), 403 );
        }

        check_ajax_referer( 'media_tracker_nonce', 'nonce' );

        // Clear unused media caches
        $list = new Unused_Media_List( '', null );
        if ( method_exists( $list, 'force_clear_cache' ) ) {
            $list->force_clear_cache();
        }

        wp_send_json_success( array( 'message' => __( 'Cache cleared successfully.', 'media-tracker' ) ) );
    }

    /**
     * Handle AJAX request to get media scan progress.
     */
    public function handle_get_scan_progress() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not have permission to perform this action.', 'media-tracker' ) ), 403 );
        }

        // Check nonce manually
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'media_tracker_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'media-tracker' ) ), 403 );
        }

        $progress_key = 'media_scan_progress_' . get_current_user_id();
        $progress = get_transient( $progress_key );

        if ( ! $progress || ! is_array( $progress ) ) {
            wp_send_json_success( array(
                'step' => 0,
                'total_steps' => 6,
                'current_step' => 'Ready to scan...',
                'percentage' => 0
            ) );
        } else {
            $step = isset( $progress['step'] ) ? (int) $progress['step'] : 0;
            $total = isset( $progress['total_steps'] ) ? (int) $progress['total_steps'] : 6;
            $current = isset( $progress['current_step'] ) ? $progress['current_step'] : 'Starting...';

            $percentage = $total > 0 ? round( ( $step / $total ) * 100 ) : 0;
            wp_send_json_success( array(
                'step' => $step,
                'total_steps' => $total,
                'current_step' => $current,
                'percentage' => $percentage
            ) );
        }
    }

    /**
     * Handle AJAX request to run media scan in background.
     * - Returns immediately to avoid long-running AJAX timeouts.
     * - Schedules an async task that performs the scan.
     */
    public function handle_run_media_scan() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not have permission to perform this action.', 'media-tracker' ) ), 403 );
        }

        // Check nonce manually to return proper JSON error
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'media_tracker_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'media-tracker' ) ), 403 );
        }

        $user_id = get_current_user_id();

        // Initialize progress - single atomic operation to avoid race condition
        $progress_key = 'media_scan_progress_' . $user_id;
        set_transient( $progress_key, array(
            'step' => 0,
            'total_steps' => 6,
            'current_step' => 'Starting scan...',
            'used_ids' => array()
        ), 1800 );

        // Schedule the background scan (runs via WP-Cron)
        wp_schedule_single_event( time() + 1, 'media_tracker_run_media_scan_bg', array( $user_id ) );

        // Ensure cron function is available and trigger immediately
        if ( ! function_exists( 'spawn_cron' ) ) {
            require_once ABSPATH . 'wp-includes/cron.php';
        }
        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron();
        }

        wp_send_json_success( array( 'message' => __( 'Scan started.', 'media-tracker' ) ) );
    }

    /**
     * NEW: Synchronous scan handler (AJAX) — fallback when cron is disabled or stalled.
     * Performs the scan within the AJAX request.
     */
    public function handle_run_media_scan_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not have permission to perform this action.', 'media-tracker' ) ), 403 );
        }

        // Check nonce manually
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'media_tracker_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'media-tracker' ) ), 403 );
        }

        $user_id = get_current_user_id();
        $progress_key = 'media_scan_progress_' . $user_id;

        // Initialize progress to visible state
        $progress = array(
            'step' => 0,
            'total_steps' => 6,
            'current_step' => 'Starting scan...',
            'used_ids' => array()
        );
        set_transient( $progress_key, $progress, 1800 );

        // Indicate scanning
        $progress['current_step'] = 'Scanning...';
        set_transient( $progress_key, $progress, 1800 );

        // Run the scan synchronously
        $list = new Unused_Media_List( '', null );
        if ( method_exists( $list, 'force_clear_cache' ) ) {
            $list->force_clear_cache();
        }

        // Use new method to scan and save snapshot
        if ( method_exists( $list, 'scan_and_save_snapshot' ) ) {
            $list->scan_and_save_snapshot();
        } else {
            // Fallback for safety
            $list->prepare_items();
        }

        // Mark complete
        set_transient( $progress_key, array(
            'step' => 6,
            'total_steps' => 6,
            'current_step' => 'Scan complete'
        ), 1800 );

        wp_send_json_success( array( 'message' => __( 'Scan completed.', 'media-tracker' ) ) );
    }

    /**
     * Background task runner that performs the actual scan.
     */
    public function run_scan_bg( $user_id = 0 ) {
        // Resolve user context if missing
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        // Verify the user exists and has proper capabilities before proceeding
        if ( $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
                // Invalid user or insufficient permissions - abort scan
                return;
            }
        }

        // Set current user for capability context if needed
        if ( function_exists( 'wp_set_current_user' ) && $user_id ) {
            wp_set_current_user( $user_id );
        }

        // Perform the scan
        $list = new Unused_Media_List( '', null );

        // Update progress state before heavy work
        $progress_key = 'media_scan_progress_' . $user_id;
        $progress = get_transient( $progress_key );
        if ( ! is_array( $progress ) ) {
            $progress = array( 'step' => 0, 'total_steps' => 6, 'current_step' => 'Starting scan...', 'used_ids' => array() );
        }
        $progress['current_step'] = 'Scanning...';
        set_transient( $progress_key, $progress, 1800 );

        if ( method_exists( $list, 'force_clear_cache' ) ) {
            $list->force_clear_cache();
        }

        // Use new method to scan and save snapshot
        if ( method_exists( $list, 'scan_and_save_snapshot' ) ) {
            $list->scan_and_save_snapshot();
        } else {
            // Fallback for safety
            $list->prepare_items();
        }

        // Mark progress as complete
        set_transient( $progress_key, array(
            'step' => 6,
            'total_steps' => 6,
            'current_step' => 'Scan complete'
        ), 1800 );
    }

    /**
     * Clear progress transient to prevent infinite reload loops.
     */
    public function handle_clear_scan_progress() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not have permission to perform this action.', 'media-tracker' ) ), 403 );
        }

        // Check nonce manually
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'media_tracker_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'media-tracker' ) ), 403 );
        }

        $progress_key = 'media_scan_progress_' . get_current_user_id();
        delete_transient( $progress_key );

        // Also clear the dashboard stats cache so the overview tab reflects the new scan results
        delete_transient( 'media_tracker_dashboard_stats_v6' );

        wp_send_json_success( array( 'message' => __( 'Progress cleared.', 'media-tracker' ) ) );
    }

    /**
     * Handle AJAX request to get the count of unused media items.
     */
    public function handle_get_unused_media_count() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not have permission to perform this action.', 'media-tracker' ) ), 403 );
        }

        // Check nonce manually
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'media_tracker_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'media-tracker' ) ), 403 );
        }

        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $author_id = isset( $_POST['author_id'] ) ? intval( $_POST['author_id'] ) : null;

        $list = new Unused_Media_List( $search, $author_id );
        // Use cached total to match table pagination
        $total_count = $list->get_total_items( $search );
        // Persist last count so it survives page reloads (optional)
        update_option( 'media_tracker_last_unused_count', $total_count );

        wp_send_json_success( array(
            'count' => $total_count,
            'message' => sprintf(
                /* translators: %d: number of unused media found! */
                _n( '%d unused image found', '%d unused media found!', $total_count, 'media-tracker' ),
                $total_count
            )
        ) );
    }

    /**
     * Handle AJAX request to remove all unused media.
     */
    public function handle_remove_all_unused_media() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not have permission to perform this action.', 'media-tracker' ) ), 403 );
        }

        // Check nonce manually
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'media_tracker_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'media-tracker' ) ), 403 );
        }

        // Get all unused media IDs using the new public method
        $list = new Unused_Media_List( '', null );
        $unused_ids = $list->get_unused_media_ids( '' );

        $deleted_count = 0;
        $failed_count = 0;

        // Delete each unused media item
        foreach ( $unused_ids as $post_id ) {
            $result = wp_delete_attachment( $post_id, true ); // true for force delete (bypass trash)
            if ( $result ) {
                $deleted_count++;
            } else {
                $failed_count++;
            }
        }

        // Clear the cache after deletion
        if ( method_exists( $list, 'force_clear_cache' ) ) {
            $list->force_clear_cache();
        }

        if ( $deleted_count > 0 ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %d: number of items deleted */
                    _n( 'Successfully deleted %d unused media item.', 'Successfully deleted %d unused media items.', $deleted_count, 'media-tracker' ),
                    $deleted_count
                ),
                'deleted_count' => $deleted_count,
                'failed_count' => $failed_count
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'No unused media items found or failed to delete.', 'media-tracker' )
            ) );
        }
    }
}
