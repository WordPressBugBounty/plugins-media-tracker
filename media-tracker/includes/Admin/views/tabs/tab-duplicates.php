<?php
/**
 * Tab: Duplicates
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-duplicates.php
 * - yourtheme/media-tracker/tabs/tab-duplicates.php
 * - media-tracker-pro/templates/tabs/tab-duplicates.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( '\Media_Tracker\Admin\Duplicate_Images' ) ) {
    $media_tracker_duplicate_instance = isset( $GLOBALS['media_tracker_duplicate'] ) && $GLOBALS['media_tracker_duplicate'] instanceof \Media_Tracker\Admin\Duplicate_Images
        ? $GLOBALS['media_tracker_duplicate']
        : new \Media_Tracker\Admin\Duplicate_Images();

    $media_tracker_ref_class = new \ReflectionClass( $media_tracker_duplicate_instance );
    $media_tracker_ref_method = $media_tracker_ref_class->getMethod( 'get_all_duplicate_hashes' );
    $media_tracker_ref_method->setAccessible( true );
    $media_tracker_duplicate_hashes = $media_tracker_ref_method->invoke( $media_tracker_duplicate_instance, 300 );

    $media_tracker_rows = array();
    $media_tracker_duplicate_ids_set = array();
    foreach ( $media_tracker_duplicate_hashes as $media_tracker_hash => $media_tracker_ids ) {
        if ( count( $media_tracker_ids ) < 2 ) {
            continue;
        }
        foreach ( $media_tracker_ids as $media_tracker_id ) {
            $media_tracker_duplicate_ids_set[ (int) $media_tracker_id ] = true;
            $media_tracker_rows[] = array(
                'id'   => (int) $media_tracker_id,
                'hash' => (string) $media_tracker_hash,
            );
        }
    }

    if ( ! empty( $media_tracker_rows ) ) {
        usort(
            $media_tracker_rows,
            function( $a, $b ) {
                $a_id = isset( $a['id'] ) ? (int) $a['id'] : 0;
                $b_id = isset( $b['id'] ) ? (int) $b['id'] : 0;
                if ( $a_id === $b_id ) {
                    return 0;
                }
                return ( $a_id > $b_id ) ? -1 : 1;
            }
        );
    }

    $media_tracker_total_duplicate_images = count( $media_tracker_duplicate_ids_set );

    // Update stored count for dashboard overview and clear cache to reflect changes immediately.
    if ( $media_tracker_total_duplicate_images !== (int) get_option( 'media_tracker_duplicate_count_last_scan' ) ) {
        update_option( 'media_tracker_duplicate_count_last_scan', $media_tracker_total_duplicate_images );
        delete_transient( 'media_tracker_dashboard_stats_v8' );
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verifying nonce is not required for reading sorting parameters from URL.
    $media_tracker_current_sort = isset( $_GET['mt_dup_sort'] ) ? sanitize_key( wp_unslash( $_GET['mt_dup_sort'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verifying nonce is not required for reading sorting parameters from URL.
    $media_tracker_current_dir  = isset( $_GET['mt_dup_dir'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['mt_dup_dir'] ) ) ? 'asc' : 'desc';

    if ( 'usage' === $media_tracker_current_sort && ! empty( $media_tracker_rows ) && class_exists( '\Media_Tracker\Admin\Media_Usage' ) ) {
        $media_tracker_usage_map = array();
        $media_tracker_usage = new \Media_Tracker\Admin\Media_Usage();
        $media_tracker_ref_usage = new \ReflectionClass( $media_tracker_usage );
        if ( $media_tracker_ref_usage->hasMethod( 'get_media_usage_count' ) ) {
            $media_tracker_m = $media_tracker_ref_usage->getMethod( 'get_media_usage_count' );
            $media_tracker_m->setAccessible( true );
            foreach ( $media_tracker_rows as $media_tracker_row ) {
                $media_tracker_id = (int) $media_tracker_row['id'];
                if ( isset( $media_tracker_usage_map[ $media_tracker_id ] ) ) {
                    continue;
                }
                $media_tracker_usage_map[ $media_tracker_id ] = (int) $media_tracker_m->invoke( $media_tracker_usage, $media_tracker_id );
            }
            usort(
                $media_tracker_rows,
                function( $a, $b ) use ( $media_tracker_usage_map, $media_tracker_current_dir ) {
                    $ua = isset( $media_tracker_usage_map[ $a['id'] ] ) ? (int) $media_tracker_usage_map[ $a['id'] ] : 0;
                    $ub = isset( $media_tracker_usage_map[ $b['id'] ] ) ? (int) $media_tracker_usage_map[ $b['id'] ] : 0;
                    if ( $ua === $ub ) {
                        return 0;
                    }
                    if ( 'asc' === $media_tracker_current_dir ) {
                        return ( $ua < $ub ) ? -1 : 1;
                    }
                    return ( $ua > $ub ) ? -1 : 1;
                }
            );
        }
    }

    // Get per page value from user settings
    $media_tracker_per_page = get_user_meta( get_current_user_id(), 'duplicate_media_per_page', true );
    if ( empty( $media_tracker_per_page ) || $media_tracker_per_page < 1 ) {
        $media_tracker_per_page = 20;
    }
    $media_tracker_total_items = count( $media_tracker_rows );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verifying nonce is not required for reading pagination parameters from URL.
    $media_tracker_page = isset( $_GET['mt_dup_page'] ) ? max( 1, (int) $_GET['mt_dup_page'] ) : 1;
    $media_tracker_total_pages = $media_tracker_per_page > 0 ? (int) ceil( $media_tracker_total_items / $media_tracker_per_page ) : 1;
    $media_tracker_offset = ( $media_tracker_page - 1 ) * $media_tracker_per_page;
    $media_tracker_page_rows = array_slice( $media_tracker_rows, $media_tracker_offset, $media_tracker_per_page );

    // Screen Options
    echo '<div id="screen-meta-links" class="metabox-prefs">';
        echo '<div id="screen-options-link-wrap" class="hide-if-no-js screen-meta-toggle">';
            echo '<button type="button" id="show-settings-link" class="button show-settings" aria-expanded="false">';
            echo esc_html__( 'Screen Options', 'media-tracker' );
            echo '</button>';
        echo '</div>';
    echo '</div>';

    echo '<div id="screen-options" class="metabox-prefs hidden">';
        echo '<div class="screen-options-content">';
            echo '<form id="duplicate-media-screen-options-form" method="post">';
                wp_nonce_field( 'duplicate_media_screen_options', 'duplicate_media_screen_options_nonce' );
                echo '<input type="hidden" name="action" value="duplicate_media_save_screen_options">';

                echo '<div class="screen-options-per-page">';
                    echo '<label for="duplicate_media_per_page">';
                    echo esc_html__( 'Number of items per page:', 'media-tracker' );
                    echo '</label>';
                    echo '<input type="number" name="duplicate_media_per_page" id="duplicate_media_per_page"';
                    echo ' value="' . esc_attr( $media_tracker_per_page ) . '"';
                    echo ' min="1" max="999" step="1" class="screen-per-page">';
                    echo '<span class="description">' . esc_html__( '(1-999)', 'media-tracker' ) . '</span>';
                echo '</div>';

                echo '<p class="submit">';
                    echo '<button type="submit" name="duplicate_media_screen_options_submit"';
                    echo ' class="button button-primary">';
                    echo esc_html__( 'Apply', 'media-tracker' );
                    echo '</button>';
                echo '</p>';
            echo '</form>';
        echo '</div>';
    echo '</div>';

    echo '<div class="media-header">';
        echo '<div class="section-title">';
            echo '<h2><i class="dashicons dashicons-images-alt"></i> ' . esc_html__( 'Duplicate Media', 'media-tracker' ) . '</h2>';
            echo '<p class="page-subtitle">';
                echo esc_html__( 'Same hash, probable duplicate images grouped together. Use delete to remove selected images.', 'media-tracker' );
            echo '</p>';
        echo '</div>';

        echo '<div class="duplicate-media-count">';
            echo '<h2>';
                if ( $media_tracker_total_duplicate_images > 0 ) {
                    echo esc_html( $media_tracker_total_duplicate_images ) . ' ';
                }
                echo '<span>duplicate media found!</span>';
            echo '</h2>';
        echo '</div>';
    echo '</div>';

    echo '<div class="mt-dup-controls" style="margin-bottom:12px;">';
    echo '<div class="mt-dup-wrap" style="display:none; margin: 16px 0 20px; padding: 14px 15px 18px; border: 1px solid #e0e0e0; border-radius: 3px;">';
        echo '<p class="mt-dup-scan-status" style="margin: 0 0 8px;">Scan status: Ready to scan...</p>';

        echo '<div id="mt-dup-progress" style="position: relative; height: 15px; background: #f0f0f0; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden;">';
            echo '<div class="mt-dup-progress-bar" style="height: 100%; width: 0; background: #6366f1; background-size: 200% 100%; transition: width 0.3s ease-in-out; will-change: width;"></div>';
            echo '<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0.15) 75%, transparent 75%, transparent); background-size: 20px 20px;"></div>';
        echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<form method="post" id="mt-duplicate-form">';
    echo '<input type="hidden" name="mt_duplicate_nonce" value="' . esc_attr( wp_create_nonce( 'mt_delete_duplicates' ) ) . '">';

    echo '<div class="duplicate-media-footer">';
        echo '<button type="button" class="button button-primary" id="mt-dup-scan">';
        echo '<span class="dashicons dashicons-update-alt" style="vertical-align:middle;"></span> ';
        echo esc_html__( 'Scan Duplicates', 'media-tracker' );
        echo '</button>';

        \Media_Tracker\Admin\Duplicate_Images::render_pagination( $media_tracker_page, $media_tracker_total_pages, $media_tracker_total_items );
    echo '</div>';

    echo '<table class="wp-list-table widefat">';
    echo '<thead><tr>';
    echo '<td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="mt-dup-select-all"></td>';
    echo '<th>' . esc_html__( 'Thumbnail', 'media-tracker' ) . '</th>';
    echo '<th>' . esc_html__( 'Title', 'media-tracker' ) . '</th>';
    echo '<th>' . esc_html__( 'Size', 'media-tracker' ) . '</th>';
    $media_tracker_sort_base_url = remove_query_arg( array( 'mt_dup_page', 'mt_dup_sort', 'mt_dup_dir' ) );
    $media_tracker_next_dir = ( 'usage' === $media_tracker_current_sort && 'asc' === $media_tracker_current_dir ) ? 'desc' : 'asc';
    $media_tracker_usage_sort_url = add_query_arg(
        array(
            'tab'         => 'duplicates',
            'mt_dup_sort' => 'usage',
            'mt_dup_dir'  => $media_tracker_next_dir,
        ),
        $media_tracker_sort_base_url
    );
    echo '<th class="mt-text-center">';
    echo '<a href="' . esc_url( $media_tracker_usage_sort_url ) . '">';
    echo '<i class="dashicons dashicons-visibility mt-mr-1"></i>';
    echo esc_html__( 'Usages Count', 'media-tracker' );
    if ( 'usage' === $media_tracker_current_sort ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The entities are safe.
        echo ' ' . ( 'asc' === $media_tracker_current_dir ? '&#9650;' : '&#9660;' );
    }
    echo '</a>';
    echo '</th>';
    echo '<th>' . esc_html__( 'Actions', 'media-tracker' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ( $media_tracker_page_rows as $media_tracker_row ) {
        $media_tracker_id = $media_tracker_row['id'];
        $media_tracker_hash = $media_tracker_row['hash'];
        $media_tracker_thumb = wp_get_attachment_thumb_url( $media_tracker_id );
        $media_tracker_file_path = get_attached_file( $media_tracker_id );
        $media_tracker_title = get_the_title( $media_tracker_id );
        if ( '' === $media_tracker_title && $media_tracker_file_path ) {
            $media_tracker_title = wp_basename( $media_tracker_file_path );
        }
        $media_tracker_edit_link = get_edit_post_link( $media_tracker_id );
        $media_tracker_size_display = '';
        if ( $media_tracker_file_path && file_exists( $media_tracker_file_path ) ) {
            $media_tracker_bytes = filesize( $media_tracker_file_path );
            if ( $media_tracker_bytes > 0 ) {
                $media_tracker_size_display = size_format( $media_tracker_bytes );
            }
        }
        $media_tracker_usage_count = 0;
        if ( class_exists( '\Media_Tracker\Admin\Media_Usage' ) ) {
            $media_tracker_usage = new \Media_Tracker\Admin\Media_Usage();
            $media_tracker_ref_usage = new \ReflectionClass( $media_tracker_usage );
            if ( $media_tracker_ref_usage->hasMethod( 'get_media_usage_count' ) ) {
                $media_tracker_m = $media_tracker_ref_usage->getMethod( 'get_media_usage_count' );
                $media_tracker_m->setAccessible( true );
                $media_tracker_usage_count = (int) $media_tracker_m->invoke( $media_tracker_usage, $media_tracker_id );
            }
        }
        if ( ! $media_tracker_thumb ) {
            continue;
        }

        echo '<tr>';
        echo '<th scope="row" class="check-column"><input type="checkbox" name="attachment_ids[]" value="' . esc_attr( $media_tracker_id ) . '"></th>';
        echo '<td><img src="' . esc_url( $media_tracker_thumb ) . '" alt="' . esc_attr( $media_tracker_title ) . '" class="mt-thumb-img"></td>';
        echo '<td><a href="' . esc_url( $media_tracker_edit_link ) . '">' . esc_html( $media_tracker_title ) . '</a><br><code>ID: ' . esc_html( $media_tracker_id ) . '</code></td>';
        echo '<td>' . ( $media_tracker_size_display ? esc_html( $media_tracker_size_display ) : '&mdash;' ) . '</td>';
        echo '<td class="mt-text-center"><a href="' . esc_url( $media_tracker_edit_link ) . '" class="mt-link-clean">' . esc_html( $media_tracker_usage_count ) . '</a></td>';
        echo '<td><button type="button" class="button mt-dup-delete-single" data-id="' . esc_attr( $media_tracker_id ) . '"><span class="dashicons dashicons-trash"></span></button></td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    echo '<div class="duplicate-media-footer">';
        echo '<button type="button" class="button button-secondary" id="mt-dup-delete-selected">' . esc_html__( 'Delete Selected', 'media-tracker' ) . '</button>';

        \Media_Tracker\Admin\Duplicate_Images::render_pagination( $media_tracker_page, $media_tracker_total_pages, $media_tracker_total_items );
    echo '</div>';

    echo '</form>';
} else {
    echo '<p>' . esc_html__( 'Duplicate images handler not available.', 'media-tracker' ) . '</p>';
}
