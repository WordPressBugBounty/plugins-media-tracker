<?php
/**
 * Tab: Duplicates
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( '\Media_Tracker\Admin\Duplicate_Images' ) ) {
    $media_tracker_duplicate_instance = isset( $GLOBALS['media_tracker_duplicate'] ) && $GLOBALS['media_tracker_duplicate'] instanceof \Media_Tracker\Admin\Duplicate_Images
        ? $GLOBALS['media_tracker_duplicate']
        : new \Media_Tracker\Admin\Duplicate_Images();

    // Duplicate groups: hash => [attachment IDs]. The SQL already returns only hashes shared by 2+ images.
    $media_tracker_groups                = array();
    $media_tracker_all_ids               = array();
    $media_tracker_total_duplicate_images = 0;

    foreach ( $media_tracker_duplicate_instance->get_duplicate_groups() as $media_tracker_hash => $media_tracker_ids ) {
        $media_tracker_ids = array_map( 'intval', (array) $media_tracker_ids );
        if ( count( $media_tracker_ids ) < 2 ) {
            continue;
        }
        rsort( $media_tracker_ids );
        $media_tracker_groups[] = array(
            'hash'   => (string) $media_tracker_hash,
            'ids'    => $media_tracker_ids,
            'newest' => $media_tracker_ids[0],
        );
        foreach ( $media_tracker_ids as $media_tracker_id ) {
            $media_tracker_all_ids[ $media_tracker_id ] = true;
            $media_tracker_total_duplicate_images++;
        }
    }
    unset( $media_tracker_hash, $media_tracker_ids, $media_tracker_id );

    // Order groups by their newest image (newest first) — a group is never split.
    usort(
        $media_tracker_groups,
        function( $a, $b ) {
            return $b['newest'] <=> $a['newest'];
        }
    );

    // Update stored count for dashboard overview and clear cache to reflect changes immediately.
    if ( $media_tracker_total_duplicate_images !== (int) get_option( 'media_tracker_duplicate_count_last_scan' ) ) {
        update_option( 'media_tracker_duplicate_count_last_scan', $media_tracker_total_duplicate_images );
        delete_transient( 'media_tracker_dashboard_stats_v8' );
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verifying nonce is not required for reading sorting parameters from URL.
    $media_tracker_current_sort = isset( $_GET['mt_dup_sort'] ) ? sanitize_key( wp_unslash( $_GET['mt_dup_sort'] ) ) : '';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verifying nonce is not required for reading sorting parameters from URL.
    $media_tracker_current_dir  = isset( $_GET['mt_dup_dir'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['mt_dup_dir'] ) ) ? 'asc' : 'desc';

    // Usage sort works on whole groups so groups always stay together.
    if ( 'usage' === $media_tracker_current_sort && ! empty( $media_tracker_groups ) && class_exists( '\Media_Tracker\Admin\Media_Usage' ) ) {
        $media_tracker_usage = new \Media_Tracker\Admin\Media_Usage();
        $media_tracker_usage_map = $media_tracker_usage->get_usage_counts_batched( array_keys( $media_tracker_all_ids ) );

        foreach ( $media_tracker_groups as $media_tracker_index => $media_tracker_group ) {
            $media_tracker_weight = 0;
            foreach ( $media_tracker_group['ids'] as $media_tracker_id ) {
                $media_tracker_weight += isset( $media_tracker_usage_map[ $media_tracker_id ] ) ? (int) $media_tracker_usage_map[ $media_tracker_id ] : 0;
            }
            $media_tracker_groups[ $media_tracker_index ]['usage'] = $media_tracker_weight;
        }
        unset( $media_tracker_usage_map, $media_tracker_index, $media_tracker_group, $media_tracker_weight, $media_tracker_id );

        usort(
            $media_tracker_groups,
            function( $a, $b ) use ( $media_tracker_current_dir ) {
                $ua = isset( $a['usage'] ) ? (int) $a['usage'] : 0;
                $ub = isset( $b['usage'] ) ? (int) $b['usage'] : 0;
                if ( $ua === $ub ) {
                    return $b['newest'] <=> $a['newest'];
                }
                return ( 'asc' === $media_tracker_current_dir ) ? ( $ua <=> $ub ) : ( $ub <=> $ua );
            }
        );
    }

    // Get per page value from user settings (number of images per page).
    // Pages are filled with whole duplicate groups: a group is never split across pages.
    $media_tracker_per_page = get_user_meta( get_current_user_id(), 'duplicate_media_per_page', true );
    if ( empty( $media_tracker_per_page ) || $media_tracker_per_page < 1 ) {
        $media_tracker_per_page = 20;
    }
    $media_tracker_total_groups = count( $media_tracker_groups );
    $media_tracker_total_pages  = 1;
    $media_tracker_pages        = array();

    // Build pages by adding whole groups until the per-page image limit is reached.
    if ( $media_tracker_total_groups > 0 ) {
        $media_tracker_page_groups_buf = array();
        $media_tracker_page_image_count = 0;

        foreach ( $media_tracker_groups as $media_tracker_group ) {
            $media_tracker_group_size = count( $media_tracker_group['ids'] );

            if ( $media_tracker_page_image_count > 0 && ( $media_tracker_page_image_count + $media_tracker_group_size ) > $media_tracker_per_page ) {
                $media_tracker_pages[]           = $media_tracker_page_groups_buf;
                $media_tracker_page_groups_buf   = array();
                $media_tracker_page_image_count  = 0;
            }

            $media_tracker_page_groups_buf[] = $media_tracker_group;
            $media_tracker_page_image_count += $media_tracker_group_size;
        }

        if ( ! empty( $media_tracker_page_groups_buf ) ) {
            $media_tracker_pages[] = $media_tracker_page_groups_buf;
        }

        $media_tracker_total_pages = max( 1, count( $media_tracker_pages ) );
    }
    unset( $media_tracker_page_groups_buf, $media_tracker_page_image_count, $media_tracker_group_size );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verifying nonce is not required for reading pagination parameters from URL.
    $media_tracker_page = isset( $_GET['mt_dup_page'] ) ? max( 1, (int) $_GET['mt_dup_page'] ) : 1;
    if ( $media_tracker_page > $media_tracker_total_pages ) {
        $media_tracker_page = max( 1, $media_tracker_total_pages );
    }
    $media_tracker_page_groups = isset( $media_tracker_pages[ $media_tracker_page - 1 ] ) ? $media_tracker_pages[ $media_tracker_page - 1 ] : array();

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

    echo '<div class="mt-dup-controls mt-mb-3">';
    echo '<div class="mt-dup-wrap mt-scan-progress">';
        echo '<p class="mt-dup-scan-status mt-scan-progress-text">Scan status: Ready to scan...</p>';

        echo '<div id="mt-dup-progress" class="mt-scan-progress-track">';
            echo '<div class="mt-dup-progress-bar mt-scan-progress-fill"></div>';
            echo '<div class="mt-scan-progress-stripes"></div>';
        echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<form method="post" id="mt-duplicate-form">';
    echo '<input type="hidden" name="mt_duplicate_nonce" value="' . esc_attr( wp_create_nonce( 'mt_delete_duplicates' ) ) . '">';

    echo '<div class="duplicate-media-footer">';
        echo '<div class="alignleft actions bulkactions mt-dup-bulkactions">';
            echo '<label class="screen-reader-text" for="bulk-action-selector-top">' . esc_html__( 'Select bulk action', 'media-tracker' ) . '</label>';
            echo '<select name="action" id="bulk-action-selector-top">';
                echo '<option value="-1">' . esc_html__( 'Bulk actions', 'media-tracker' ) . '</option>';
                echo '<option value="delete">' . esc_html__( 'Delete permanently', 'media-tracker' ) . '</option>';
            echo '</select>';
            echo '<button type="button" id="doaction" class="button action">' . esc_html__( 'Apply', 'media-tracker' ) . '</button>';
            echo '<button type="button" class="button button-primary" id="mt-dup-scan">';
                echo '<span class="dashicons dashicons-update-alt mt-v-middle"></span> ';
                echo esc_html__( 'Scan Duplicates', 'media-tracker' );
            echo '</button>';
        echo '</div>';

        \Media_Tracker\Admin\Duplicate_Images::render_pagination( $media_tracker_page, $media_tracker_total_pages, $media_tracker_total_duplicate_images );
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

    // Single shared instance — counts are cached per request and in a 12h transient.
    $media_tracker_usage = class_exists( '\Media_Tracker\Admin\Media_Usage' ) ? new \Media_Tracker\Admin\Media_Usage() : null;

    foreach ( $media_tracker_page_groups as $media_tracker_group ) {
        foreach ( $media_tracker_group['ids'] as $media_tracker_id ) {
            $media_tracker_thumb = wp_get_attachment_thumb_url( $media_tracker_id );
            if ( ! $media_tracker_thumb ) {
                continue;
            }
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
            $media_tracker_usage_count = $media_tracker_usage ? $media_tracker_usage->count_media_usage( $media_tracker_id ) : 0;

            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" name="attachment_ids[]" value="' . esc_attr( $media_tracker_id ) . '"></th>';
            echo '<td><img src="' . esc_url( $media_tracker_thumb ) . '" alt="' . esc_attr( $media_tracker_title ) . '" class="mt-thumb-img"></td>';
            echo '<td><a href="' . esc_url( $media_tracker_edit_link ) . '">' . esc_html( $media_tracker_title ) . '</a><br><code>ID: ' . esc_html( $media_tracker_id ) . '</code></td>';
            echo '<td>' . ( $media_tracker_size_display ? esc_html( $media_tracker_size_display ) : '&mdash;' ) . '</td>';
            echo '<td class="mt-text-center"><a href="' . esc_url( $media_tracker_edit_link ) . '" class="mt-link-clean">' . esc_html( $media_tracker_usage_count ) . '</a></td>';
            echo '<td><button type="button" class="button mt-dup-delete-single" data-id="' . esc_attr( $media_tracker_id ) . '"><span class="dashicons dashicons-trash"></span></button></td>';
            echo '</tr>';
        }
    }
    unset( $media_tracker_group, $media_tracker_id );

    echo '</tbody>';
    echo '</table>';

    echo '<div class="duplicate-media-footer">';
        echo '<div class="alignleft actions bulkactions mt-dup-bulkactions">';
            echo '<label class="screen-reader-text" for="bulk-action-selector-bottom">' . esc_html__( 'Select bulk action', 'media-tracker' ) . '</label>';
            echo '<select name="action2" id="bulk-action-selector-bottom">';
                echo '<option value="-1">' . esc_html__( 'Bulk actions', 'media-tracker' ) . '</option>';
                echo '<option value="delete">' . esc_html__( 'Delete permanently', 'media-tracker' ) . '</option>';
            echo '</select>';
            echo '<button type="button" id="doaction2" class="button action">' . esc_html__( 'Apply', 'media-tracker' ) . '</button>';
        echo '</div>';

        \Media_Tracker\Admin\Duplicate_Images::render_pagination( $media_tracker_page, $media_tracker_total_pages, $media_tracker_total_duplicate_images );
    echo '</div>';

    echo '</form>';
} else {
    echo '<p>' . esc_html__( 'Duplicate images handler not available.', 'media-tracker' ) . '</p>';
}
