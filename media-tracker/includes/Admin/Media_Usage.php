<?php

namespace Media_Tracker\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * The Menu handler class
 */
class Media_Usage {

    /**
     * Initialize the class
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_media_meta_box' ) );
        // Add "Usages Count" column to Media Library list view
        add_filter( 'manage_media_columns', array( $this, 'add_usage_count_column' ) );
        add_action( 'manage_media_custom_column', array( $this, 'render_usage_count_column' ), 10, 2 );
        // Make the column sortable
        add_filter( 'manage_upload_sortable_columns', array( $this, 'make_usage_count_column_sortable' ) );
        // Handle sorting logic
        add_filter( 'posts_clauses', array( $this, 'sort_by_usage_count' ), 10, 2 );
        // Column width/styles only on Media Library list screen
        add_action( 'admin_head-upload.php', array( $this, 'print_usage_count_column_css' ) );
        // AJAX for dashboard stats
        add_action( 'wp_ajax_media_tracker_get_most_used', array( $this, 'ajax_get_most_used' ) );
        // AJAX for refreshing used media stats
        add_action( 'wp_ajax_media_tracker_refresh_used_stats', array( $this, 'ajax_refresh_used_stats' ) );
    }

    /**
     * AJAX handler to get most used media.
     */
    public function ajax_get_most_used() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $media_tracker_most_used = self::get_dashboard_most_used_media();
        set_transient( 'media_tracker_most_used_media_stats', $media_tracker_most_used, HOUR_IN_SECONDS );

        ob_start();
        if ( ! empty( $media_tracker_most_used ) ) {
            ?>
            <table class="mt-overview-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'File Name', 'media-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'media-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Usage Count', 'media-tracker' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'media-tracker' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $media_tracker_most_used as $media_tracker_media ) : ?>
                        <?php
                        $media_tracker_file_type = $media_tracker_media->post_mime_type ? str_replace( 'image/', '', $media_tracker_media->post_mime_type ) : '-';
                        $media_tracker_edit_link = get_edit_post_link( $media_tracker_media->ID );
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $media_tracker_media->post_title ); ?></strong></td>
                            <td><?php echo esc_html( $media_tracker_file_type ); ?></td>
                            <td>
                                <span class="tag" style="background: #10b981; color: white;">
                                    <?php
                                    /* translators: %d: Number of times the media is used. */
                                    printf( esc_html__( '%d times', 'media-tracker' ), intval( $media_tracker_media->usage_count ) );
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $media_tracker_edit_link ); ?>" class="btn btn-outline" style="padding: 5px 10px; text-decoration: none;">
                                    <i class="dashicons dashicons-visibility"></i>
                                    <?php esc_html_e( 'View', 'media-tracker' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p style="color: #64748b; text-align: center; padding: 40px;">';
            echo '<i class="dashicons dashicons-chart-bar" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px; height: 48px; width: 48px;"></i><br>';
            esc_html_e( 'No media usage data available.', 'media-tracker' );
            echo '</p>';
        }
        $html = ob_get_clean();
        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX handler to refresh used media stats and clear cache.
     */
    public function ajax_refresh_used_stats() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        // Clear the cache
        delete_transient( 'media_tracker_used_media_stats' );

        // Get fresh stats
        $stats = self::get_used_media_stats();

        if ( $stats ) {
            wp_send_json_success( array(
                'used_count' => $stats->used_count,
                'used_size'  => $stats->used_size,
                'used_size_formatted' => size_format( $stats->used_size ),
                'message'    => 'Stats refreshed successfully'
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Failed to refresh stats' ) );
        }
    }

    /**
     * Get most used media for dashboard stats.
     * Optimized to scan post content instead of iterating all attachments.
     *
     * @return array Array of objects with ID, post_title, post_mime_type, usage_count.
     */
    public static function get_dashboard_most_used_media() {
        global $wpdb;

        $media_tracker_all_usage = array();

        // Source 1: Featured images (_thumbnail_id).
        // This is efficient as it uses an index.
        $media_tracker_featured_media = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT p.ID, p.post_title, p.post_mime_type, COUNT(pm.meta_value) as usage_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.meta_value
            WHERE pm.meta_key = '_thumbnail_id'
            AND p.post_type = 'attachment'
            AND p.post_status = 'inherit'
            GROUP BY p.ID"
        );

        foreach ( $media_tracker_featured_media as $media_tracker_item ) {
            $media_tracker_id = $media_tracker_item->ID;
            if ( ! isset( $media_tracker_all_usage[ $media_tracker_id ] ) ) {
                $media_tracker_all_usage[ $media_tracker_id ] = array(
                    'ID'             => $media_tracker_id,
                    'post_title'     => $media_tracker_item->post_title,
                    'post_mime_type' => $media_tracker_item->post_mime_type,
                    'usage_count'    => 0,
                );
            }
            $media_tracker_all_usage[ $media_tracker_id ]['usage_count'] += $media_tracker_item->usage_count;
        }

        // Source 2: Content usage - Scan posts instead of attachments.
        // We process posts in chunks to avoid memory issues.
        $limit      = 500;
        $offset     = 0;
        $id_counts  = array();

        // Only scan published posts, pages, and other public types
        $post_types = array( 'post', 'page' );
        // Include WooCommerce products if available
        if ( class_exists( 'WooCommerce' ) ) {
            $post_types[] = 'product';
        }
        $post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        while ( true ) {
            $query_args = array_merge( $post_types, array( $limit, $offset ) );
            $sql = "SELECT ID, post_content FROM {$wpdb->posts}
                    WHERE post_type IN ($post_types_placeholders)
                    AND post_status = 'publish'
                    LIMIT %d OFFSET %d";

            $posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders.
                    $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL.
                    $query_args
                )
            );

            if ( empty( $posts ) ) {
                break;
            }

            foreach ( $posts as $post ) {
                $content = $post->post_content;
                if ( empty( $content ) ) {
                    continue;
                }

                // Match wp-image-ID class
                if ( preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
                    foreach ( $matches[1] as $id ) {
                        $id = intval( $id );
                        $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                    }
                }

                // Match Gutenberg blocks with "id":ID
                if ( preg_match_all( '/"id":(\d+)/', $content, $matches ) ) {
                    foreach ( $matches[1] as $id ) {
                        $id = intval( $id );
                        // Basic check to avoid false positives (e.g. small numbers that might not be attachment IDs)
                        // We will verify existence later.
                        $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                    }
                }

                // Match gallery shortcodes [gallery ids="1,2,3"]
                if ( preg_match_all( '/ids=\"([^\"]+)\"/', $content, $matches ) ) {
                    foreach ( $matches[1] as $ids_str ) {
                        $ids = explode( ',', $ids_str );
                        foreach ( $ids as $id ) {
                            $id = intval( trim( $id ) );
                            if ( $id > 0 ) {
                                $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                            }
                        }
                    }
                }
            }

            $offset += $limit;

            // Safety break for very large sites to prevent timeout
            if ( $offset > 10000 ) {
                break;
            }
        }

        // WooCommerce: count gallery and variation thumbnail usages
        if ( class_exists( 'WooCommerce' ) ) {
            // Product galleries (_product_image_gallery)
            $wc_gallery_values = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_product_image_gallery'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IS NOT NULL
                 AND pm.meta_value != ''"
            );
            if ( ! empty( $wc_gallery_values ) ) {
                foreach ( $wc_gallery_values as $val ) {
                    $ids = array_filter( array_map( 'intval', array_map( 'trim', explode( ',', (string) $val ) ) ) );
                    foreach ( $ids as $id ) {
                        if ( $id > 0 ) {
                            $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                        }
                    }
                }
            }

            // Variation featured images (_thumbnail_id on product_variation)
            $wc_variation_thumbs = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = 'product_variation'
                 AND pm.meta_key = '_thumbnail_id'
                 AND pm.meta_value IS NOT NULL
                 AND pm.meta_value NOT IN ('', '0')"
            );
            if ( ! empty( $wc_variation_thumbs ) ) {
                foreach ( $wc_variation_thumbs as $val ) {
                    $id = intval( $val );
                    if ( $id > 0 ) {
                        $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                    }
                }
            }
        }

        // Process gathered counts
        if ( ! empty( $id_counts ) ) {
            // Get details for these attachments to verify they exist and get titles
            $found_ids = array_keys( $id_counts );

            // Chunk the ID lookup
            $id_chunks = array_chunk( $found_ids, 500 );

            foreach ( $id_chunks as $chunk ) {
                $ids_placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
                $sql = "SELECT ID, post_title, post_mime_type
                        FROM {$wpdb->posts}
                        WHERE ID IN ($ids_placeholders)
                        AND post_type = 'attachment'";

                $attachments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders.
                        $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL.
                        $chunk
                    )
                );

                foreach ( $attachments as $att ) {
                    $id = $att->ID;
                    $count = isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0;

                    if ( ! isset( $media_tracker_all_usage[ $id ] ) ) {
                        $media_tracker_all_usage[ $id ] = array(
                            'ID'             => $id,
                            'post_title'     => $att->post_title,
                            'post_mime_type' => $att->post_mime_type,
                            'usage_count'    => 0,
                        );
                    }
                    $media_tracker_all_usage[ $id ]['usage_count'] += $count;
                }
            }
        }

        // Convert to object array and sort by usage.
        $media_tracker_most_used = array();
        foreach ( $media_tracker_all_usage as $media_tracker_usage ) {
            $media_tracker_obj                 = new \stdClass();
            $media_tracker_obj->ID             = $media_tracker_usage['ID'];
            $media_tracker_obj->post_title     = $media_tracker_usage['post_title'];
            $media_tracker_obj->post_mime_type = $media_tracker_usage['post_mime_type'];
            $media_tracker_obj->usage_count    = $media_tracker_usage['usage_count'];
            $media_tracker_most_used[]         = $media_tracker_obj;
        }

        // Sort by usage count descending.
        usort(
            $media_tracker_most_used,
            function( $a, $b ) {
                return $b->usage_count - $a->usage_count;
            }
        );

        // Get top 5.
        return array_slice( $media_tracker_most_used, 0, 5 );
    }

    /**
     * Get media type statistics for dashboard.
     *
     * @return array Array of objects with post_mime_type and count.
     */
    public static function get_mime_type_stats() {
        global $wpdb;

        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT post_mime_type, COUNT(*) as count
                FROM {$wpdb->posts}
                WHERE post_type = %s
                AND post_status = %s
                AND post_mime_type != ''
                GROUP BY post_mime_type
                ORDER BY count DESC
                LIMIT 5",
                'attachment',
                'inherit'
            )
        );
    }

    /**
     * Get all used media statistics (count and total size).
     * This returns data for all media that has usage count > 0.
     * Results are cached for 60 minutes.
     *
     * @return object Object with properties: used_count, used_size.
     */
    public static function get_used_media_stats() {
        // Check cache first
        $cache_key = 'media_tracker_used_media_stats';
        $cached_stats = get_transient( $cache_key );

        if ( false !== $cached_stats ) {
            return $cached_stats;
        }

        global $wpdb;

        $unused_ids = get_option( 'media_tracker_unused_ids_snapshot', array() );

        if ( ! empty( $unused_ids ) && is_array( $unused_ids ) ) {
            $all_attachment_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
            );

            $used_count = 0;
            $used_size  = 0;

            if ( ! empty( $all_attachment_ids ) ) {
                $unused_map = array();
                foreach ( $unused_ids as $unused_id ) {
                    $unused_id = intval( $unused_id );
                    if ( $unused_id > 0 ) {
                        $unused_map[ $unused_id ] = true;
                    }
                }

                foreach ( $all_attachment_ids as $attachment_id ) {
                    $attachment_id = intval( $attachment_id );
                    if ( $attachment_id <= 0 ) {
                        continue;
                    }
                    if ( isset( $unused_map[ $attachment_id ] ) ) {
                        continue;
                    }

                    $used_count++;

                    $file_path = get_attached_file( $attachment_id );
                    if ( $file_path && file_exists( $file_path ) ) {
                        $used_size += filesize( $file_path );
                    }
                }
            }

            $stats = (object) array(
                'used_count' => $used_count,
                'used_size'  => $used_size,
            );

            set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

            return $stats;
        }

        // Get all attachments that have usage
        $all_usage = array();

        // Source 1: Featured images (_thumbnail_id).
        $featured_media = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT p.ID, p.post_title, p.post_mime_type, COUNT(pm.meta_value) as usage_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.meta_value
            WHERE pm.meta_key = '_thumbnail_id'
            AND p.post_type = 'attachment'
            AND p.post_status = 'inherit'
            GROUP BY p.ID"
        );

        foreach ( $featured_media as $item ) {
            $id = $item->ID;
            if ( ! isset( $all_usage[ $id ] ) ) {
                $all_usage[ $id ] = 0;
            }
            $all_usage[ $id ] += $item->usage_count;
        }

        // Source 2: Content usage - Scan posts for image usage
        $limit      = 500;
        $offset     = 0;
        $id_counts  = array();

        $post_types = array( 'post', 'page' );
        if ( class_exists( 'WooCommerce' ) ) {
            $post_types[] = 'product';
        }
        $post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        while ( true ) {
            $query_args = array_merge( $post_types, array( $limit, $offset ) );
            $sql = "SELECT ID, post_content FROM {$wpdb->posts}
                    WHERE post_type IN ($post_types_placeholders)
                    AND post_status = 'publish'
                    LIMIT %d OFFSET %d";

            $posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders.
                    $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL.
                    $query_args
                )
            );

            if ( empty( $posts ) ) {
                break;
            }

            foreach ( $posts as $post ) {
                $content = $post->post_content;
                if ( empty( $content ) ) {
                    continue;
                }

                // Match wp-image-ID class
                if ( preg_match_all( '/wp-image-(\d+)/', $content, $matches ) ) {
                    foreach ( $matches[1] as $id ) {
                        $id = intval( $id );
                        $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                    }
                }

                // Match Gutenberg blocks with "id":ID
                if ( preg_match_all( '/"id":(\d+)/', $content, $matches ) ) {
                    foreach ( $matches[1] as $id ) {
                        $id = intval( $id );
                        $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                    }
                }

                // Match gallery shortcodes [gallery ids="1,2,3"]
                if ( preg_match_all( '/ids=\"([^\"]+)\"/', $content, $matches ) ) {
                    foreach ( $matches[1] as $ids_str ) {
                        $ids = explode( ',', $ids_str );
                        foreach ( $ids as $id ) {
                            $id = intval( trim( $id ) );
                            if ( $id > 0 ) {
                                $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                            }
                        }
                    }
                }
            }

            $offset += $limit;

            if ( $offset > 10000 ) {
                break;
            }
        }

        // WooCommerce: count gallery and variation thumbnail usages
        if ( class_exists( 'WooCommerce' ) ) {
            $wc_gallery_values = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_product_image_gallery'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IS NOT NULL
                 AND pm.meta_value != ''"
            );
            if ( ! empty( $wc_gallery_values ) ) {
                foreach ( $wc_gallery_values as $val ) {
                    $ids = array_filter( array_map( 'intval', array_map( 'trim', explode( ',', (string) $val ) ) ) );
                    foreach ( $ids as $id ) {
                        if ( $id > 0 ) {
                            $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                        }
                    }
                }
            }

            $wc_variation_thumbs = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE p.post_type = 'product_variation'
                 AND pm.meta_key = '_thumbnail_id'
                 AND pm.meta_value IS NOT NULL
                 AND pm.meta_value NOT IN ('', '0')"
            );
            if ( ! empty( $wc_variation_thumbs ) ) {
                foreach ( $wc_variation_thumbs as $val ) {
                    $id = intval( $val );
                    if ( $id > 0 ) {
                        $id_counts[ $id ] = ( isset( $id_counts[ $id ] ) ? $id_counts[ $id ] : 0 ) + 1;
                    }
                }
            }
        }

        // Merge content usage counts
        if ( ! empty( $id_counts ) ) {
            foreach ( $id_counts as $id => $count ) {
                if ( ! isset( $all_usage[ $id ] ) ) {
                    $all_usage[ $id ] = 0;
                }
                $all_usage[ $id ] += $count;
            }
        }

        // Calculate stats
        $used_count = count( $all_usage );
        $used_size  = 0;

        if ( $used_count > 0 ) {
            // Get file sizes for used attachments
            $used_ids = array_keys( $all_usage );
            $id_chunks = array_chunk( $used_ids, 500 );

            foreach ( $id_chunks as $chunk ) {
                $ids_placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
                $sql = "SELECT pm.meta_value
                        FROM {$wpdb->postmeta} pm
                        WHERE pm.meta_key = '_wp_attached_file'
                        AND pm.post_id IN ($ids_placeholders)";

                $file_paths = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders.
                        $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic SQL.
                        $chunk
                    )
                );

                // Get upload directory
                $upload_dir = wp_upload_dir();

                foreach ( $file_paths as $file_path ) {
                    if ( ! empty( $file_path ) ) {
                        $full_path = $upload_dir['basedir'] . '/' . $file_path;
                        if ( file_exists( $full_path ) ) {
                            $used_size += filesize( $full_path );
                        }
                    }
                }
            }
        }

        // Create stats object
        $stats = (object) array(
            'used_count' => $used_count,
            'used_size'  => $used_size,
        );

        // Cache for 60 minutes
        set_transient( $cache_key, $stats, HOUR_IN_SECONDS );

        return $stats;
    }

    /**
     * Add a custom meta box to the media file details page
     *
     * @return void
     */
    public function add_media_meta_box() {
        add_meta_box(
            'media-usage',
            __( 'Media Usage', 'media-tracker' ),
            array( $this, 'display_media_callback_func' ),
            'attachment',
            'normal',
            'high'
        );
    }

    /**
     * Add Usages Count column in Media Library list table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_usage_count_column( $columns ) {
        $columns['usage_count'] = __( 'Usages Count', 'media-tracker' );
        return $columns;
    }

    /**
     * Render the Usages Count column content
     *
     * @param string $column_name Column key
     * @param int    $post_id     Attachment ID
     * @return void
     */
    public function render_usage_count_column( $column_name, $post_id ) {
        if ( 'usage_count' !== $column_name ) {
            return;
        }
        $count = $this->get_media_usage_count( $post_id );
        $edit_link = get_edit_post_link( $post_id );
        if ( empty( $edit_link ) ) {
            echo intval( $count );
            return;
        }
        // Compact clickable number to the edit screen
        echo '<a href="' . esc_url( $edit_link ) . '" class="mt-usage-count-link" aria-label="' . esc_attr__( 'Open attachment edit screen', 'media-tracker' ) . '">' . intval( $count ) . '</a>';
    }

    /**
     * Print compact column CSS for Media Library
     */
    public function print_usage_count_column_css() {
        echo '<style>
        .wp-list-table .column-usage_count{width:120px; text-align:center; white-space:nowrap;}
        .wp-list-table td.column-usage_count .mt-usage-count-link{display:inline-block; padding:0 6px; border-radius:4px; text-align:center;}
        @media (max-width:782px){.wp-list-table .column-usage_count{width:60px;}}
        .mt-highlight{animation: mt-pulse 2s ease-in-out 3;}
        @keyframes mt-pulse{0%,100%{background-color:#fff9c4;}50%{background-color:#ffeb3b;}}
        </style>
        <script>
        jQuery(document).ready(function($) {
            const urlParams = new URLSearchParams(window.location.search);
            const highlight = urlParams.get("highlight");
            if(highlight === "usage_count"){
                $("th.column-usage_count, td.column-usage_count").addClass("mt-highlight");
                setTimeout(function(){$(".mt-highlight").removeClass("mt-highlight");},6000);
            }
        });
        </script>';
    }

    /**
     * Make the usage count column sortable
     *
     * @param array $columns Existing sortable columns
     * @return array Modified sortable columns
     */
    public function make_usage_count_column_sortable( $columns ) {
        $columns['usage_count'] = 'usage_count';
        return $columns;
    }

    /**
     * Handle sorting by usage count
     *
     * @param array    $clauses SQL clauses
     * @param WP_Query $query   The WP_Query instance
     * @return array Modified SQL clauses
     */
    public function sort_by_usage_count( $clauses, $query ) {
        // Only apply to media library admin screen
        if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) ) {
            return $clauses;
        }

        // Check if we're sorting by usage_count
        $orderby = $query->get( 'orderby' );
        if ( 'usage_count' === $orderby ) {

            global $wpdb;

            // Get all attachment IDs with their usage counts
            $attachment_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT DISTINCT ID FROM {$wpdb->posts}
                WHERE post_type = 'attachment'
                ORDER BY ID DESC"
            );

            $usage_counts = array();
            foreach ( $attachment_ids as $attachment_id ) {
                $usage_counts[ $attachment_id ] = $this->get_media_usage_count( $attachment_id );
            }

            // Sort by usage count
            $order = $query->get( 'order' );
            if ( 'asc' === strtolower( $order ) ) {
                asort( $usage_counts );
            } else {
                arsort( $usage_counts );
            }

            // Get sorted post IDs
            $sorted_ids = array_keys( $usage_counts );

            // If no posts to show, return early
            if ( empty( $sorted_ids ) ) {
                return $clauses;
            }

            // Modify the WHERE clause to only include the sorted posts
            $sorted_ids_str = implode( ',', array_map( 'intval', $sorted_ids ) );
            $clauses['where'] .= " AND {$wpdb->posts}.ID IN ($sorted_ids_str)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

            // Sort by the custom order using FIELD() - $sorted_ids_str is safely constructed
            $clauses['orderby'] = "FIELD( {$wpdb->posts}.ID, $sorted_ids_str )"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return $clauses;
    }

    /**
     * Display the meta box content
     *
     * @param WP_Post $post The post object.
     * @return void
     */
    public function display_media_callback_func( $post ) {
        $attachment_id = $post->ID;
        $results = $this->get_media_usage( $attachment_id );

        echo '<div class="mediatracker-usage-table">';
        if ( $results ) {
            echo '
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-primary" style="width: 50px;">' . esc_html__( '#', 'media-tracker' ) . '</th>
                        <th scope="col" class="manage-column">' . esc_html__( 'Title', 'media-tracker' ) . '</th>
                        <th scope="col" class="manage-column">' . esc_html__( 'Type', 'media-tracker' ) . '</th>
                        <th scope="col" class="manage-column">' . esc_html__( 'Date Added', 'media-tracker' ) . '</th>
                        <th scope="col" class="manage-column">' . esc_html__( 'Actions', 'media-tracker' ) . '</th>
                    </tr>
                </thead>
                <tbody>';
            $sn = 1;
            foreach ( $results as $result ) {
                // Special handling for site icon
                if ( isset($result->post_type) && $result->post_type === 'site_icon' ) {
                    echo '
                    <tr>
                        <td>' . esc_html( $sn++ ) . '</td>
                        <td>' . esc_html( $result->post_title ) . '</td>
                        <td>' . esc_html( $result->post_type ) . '</td>
                        <td>' . esc_html__('System Setting', 'media-tracker') . '</td>
                        <td>
                            <a href="' . esc_url(admin_url('customize.php?autofocus[section]=title_tagline')) . '" class="button button-primary" target="_blank">' .
                            esc_html__( 'Customize Site Icon', 'media-tracker' ) . '</a>
                        </td>
                    </tr>';
                    continue;
                }

                $date_added = get_the_date( 'Y-m-d H:i:s', $result->ID );
                $date_added_timestamp = get_the_time( 'U', $result->ID );

                // Translators: This is a time difference string
                $date_added = sprintf( esc_html__('%s ago', 'media-tracker'),
                    human_time_diff($date_added_timestamp, current_time('timestamp'))
                );

                $admin_view_url = get_edit_post_link( $result->ID );
                $frontend_view_url = get_permalink( $result->ID );

                // Fallback for empty URLs
                if ( empty($admin_view_url) ) {
                    $admin_view_url = '#';
                }
                if ( empty($frontend_view_url) ) {
                    $frontend_view_url = '#';
                }

                echo '
                <tr>
                    <td>' . esc_html( $sn++ ) . '</td>
                    <td><a href="' . esc_url( $frontend_view_url ) . '" target="_blank">' . esc_html( $result->post_title ) . '</a></td>
                    <td>' . esc_html( $result->post_type ) . '</td>
                    <td>' . esc_html( $date_added ) . '</td>
                    <td>
                        <a href="' . esc_url( $admin_view_url ) . '" class="button button-primary" target="_blank">' . esc_html__( 'Admin View', 'media-tracker' ) . '</a>
                        <a href="' . esc_url( $frontend_view_url ) . '" class="button" target="_blank">' . esc_html__( 'Frontend View', 'media-tracker' ) . '</a>
                    </td>
                </tr>';
            }
            echo '
                </tbody>
            </table>';
        } else {
            echo '<p>' . esc_html__( 'No posts or pages found using this media file.', 'media-tracker' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Compute usage count for an attachment.
     *
     * @param int $attachment_id Attachment ID
     * @return int Count of usages
     */
    private function get_media_usage_count( $attachment_id ) {
        $results = $this->get_media_usage( $attachment_id );
        return is_array( $results ) ? count( $results ) : 0;
    }

    /**
     * Find posts/pages/settings where a media attachment is used.
     * Shared between column and meta box rendering.
     *
     * @param int $attachment_id
     * @return array List of usage entries (objects with at least ID, post_title, post_type)
     */
    private function get_media_usage( $attachment_id ) {
        global $wpdb;

        // Check cache first
        $cache_key = 'media_tracker_usage_' . $attachment_id;
        $cached_results = wp_cache_get( $cache_key, 'media_tracker' );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        $results = array();

        // Check if the image is used as site icon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id == $attachment_id) {
            $site_icon_result = new \stdClass();
            $site_icon_result->ID = 0; // Using 0 as this isn't tied to a specific post
            $site_icon_result->post_title = __('Site Icon (Favicon)', 'media-tracker');
            $site_icon_result->post_type = 'site_icon';
            $results[] = $site_icon_result;
        }

        // Posts using this attachment as featured image
        $thumbnail_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "
                SELECT DISTINCT p.ID, p.post_title, p.post_type, pm.meta_key, pm.meta_value
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_value = %d
                AND pm.meta_key = '_thumbnail_id'
                AND p.post_status = 'publish'
                ",
                $attachment_id
            )
        );
        $results = array_merge($results, $thumbnail_results);

        // ACF usage
        if(class_exists('ACF')) {
            $acf_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "
                    SELECT DISTINCT pm.post_id as ID, p.post_title, p.post_type, pm.meta_key, pm.meta_value
                    FROM {$wpdb->postmeta} pm
                    JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
                    AND pm2.meta_key = concat('_', pm.meta_key)
                    JOIN {$wpdb->posts} acf ON pm2.meta_value = acf.post_name
                    AND acf.post_type = 'acf-field'
                    JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_status = 'publish'
                    AND (
                        pm.meta_value = %d
                        OR (pm.meta_value LIKE %s
                        AND acf.post_content LIKE %s)
                    )
                    ",
                    $attachment_id,
                    '%'.$wpdb->esc_like($attachment_id).'%',
                    '%"type";s:7:"gallery"%'
                )
            );
            $results = array_merge($results, $acf_posts);

            $acf_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    "
                    SELECT DISTINCT pm.post_id as ID, p.post_title, p.post_type, pm.meta_key, pm.meta_value
                    FROM {$wpdb->postmeta} pm
                    JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                    WHERE p.post_status = 'publish'
                    AND (
                        pm.meta_value = %d
                        OR pm.meta_value LIKE %s
                        OR pm.meta_value LIKE %s
                    )
                ",
                $attachment_id,
                '%\"'.$attachment_id.'\"%', // matches s:"390"
                '%i:'.$attachment_id.';%'   // matches i:390;
                )
            );
            $results = array_merge($results, $acf_posts);
        }

        // Elementor usage
        $elementor_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "
                SELECT DISTINCT p.ID, p.post_title, p.post_type
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND p.post_status = %s
            ", '_elementor_data', 'publish')
        );
        foreach ($elementor_posts as $elementor_post) {
            $elementor_data = get_post_meta($elementor_post->ID, '_elementor_data', true);
            if ($elementor_data) {
                $elementor_data = json_decode($elementor_data, true);
                if (is_array($elementor_data)) {
                    $elementor_found = false;
                    array_walk_recursive($elementor_data, function($item, $key) use ($attachment_id, &$results, $elementor_post, &$elementor_found) {
                        if ($elementor_found) { return; }
                        if ($key === 'id' && is_numeric($item) && intval($item) == intval($attachment_id)) {
                            $results[] = $elementor_post;
                            $elementor_found = true; // prevent multiple inserts for the same post
                        }
                    });
                }
            }
        }

        // Gutenberg block usage
        $gutenberg_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "
                SELECT DISTINCT p.ID, p.post_title, p.post_type
                FROM {$wpdb->posts} p
                WHERE p.post_status = 'publish'
                AND p.post_content LIKE %s
            ", '%"id":'.$attachment_id.'%')
        );
        $results = array_merge($results, $gutenberg_posts);

        // Classic/Gutenberg: posts with wp-image-<ID> classes
        $wpimage_like = '%wp-image-' . intval( $attachment_id ) . '%';
        $classic_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                $wpimage_like
            )
        );
        $results = array_merge( $results, $classic_posts );

        // Gallery shortcodes and Gutenberg gallery blocks containing this ID
        $gallery_candidates = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                '%gallery%'
            )
        );
        foreach ( $gallery_candidates as $p ) {
            $found = false;
            // [gallery ids="..."]
            if ( preg_match_all( '/\[gallery[^\]]*ids=\"([^\"]+)\"/', $p->post_content, $m1 ) ) {
                foreach ( $m1[1] as $csv ) {
                    $ids = array_filter( array_map( 'intval', explode( ',', $csv ) ) );
                    if ( in_array( intval( $attachment_id ), $ids, true ) ) { $found = true; break; }
                }
            }
            // Gutenberg gallery block
            if ( ! $found && preg_match_all( '/<!--\s*wp:gallery\s*{[^}]*\"ids\"\s*:\s*\[([^\]]+)\]/', $p->post_content, $m2 ) ) {
                foreach ( $m2[1] as $list ) {
                    $ids = array_filter( array_map( 'intval', preg_split( '/\s*,\s*/', preg_replace( '/[^\d,]/', '', $list ) ) ) );
                    if ( in_array( intval( $attachment_id ), $ids, true ) ) { $found = true; break; }
                }
            }
            if ( $found ) {
                $results[] = (object) array( 'ID' => $p->ID, 'post_title' => $p->post_title, 'post_type' => $p->post_type );
            }
        }

        // Generic: direct uploads URL references mapped to attachment ID
        $url_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                '%wp-content/uploads%'
            )
        );
        foreach ( $url_posts as $p ) {
            if ( preg_match_all( '/https?:\/\/[^"\']+\/wp-content\/uploads\/[^"\']+/i', $p->post_content, $m ) ) {
                foreach ( $m[0] as $url ) {
                    $id = attachment_url_to_postid( $url );
                    if ( $id && intval( $id ) === intval( $attachment_id ) ) {
                        $results[] = (object) array( 'ID' => $p->ID, 'post_title' => $p->post_title, 'post_type' => $p->post_type );
                        break;
                    }
                }
            }
        }

        // WooCommerce specific: product galleries and variation thumbnails
        if ( class_exists( 'WooCommerce' ) ) {
            // Product gallery contains attachment
            $wc_gallery_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare(
                    "SELECT pm.post_id AS ID, p.post_title, p.post_type, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_product_image_gallery' AND p.post_status = 'publish' AND pm.meta_value LIKE %s",
                    '%' . $wpdb->esc_like( (string) $attachment_id ) . '%'
                )
            );
            foreach ( $wc_gallery_rows as $row ) {
                $ids = array_filter( array_map( 'intval', explode( ',', (string) $row->meta_value ) ) );
                if ( in_array( intval( $attachment_id ), $ids, true ) ) {
                    $results[] = (object) array( 'ID' => $row->ID, 'post_title' => $row->post_title, 'post_type' => $row->post_type );
                }
            }
            // Variation featured image
            $wc_var_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $wpdb->prepare(
                    "SELECT p.ID, p.post_title, p.post_type FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'product_variation' AND pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d",
                    $attachment_id
                )
            );
            $results = array_merge( $results, $wc_var_rows );
        }

        // Divi Builder usage: scan posts containing Divi shortcodes and match IDs/URLs
        $divi_posts = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                '%[et_pb_%'
            )
        );
        foreach ( $divi_posts as $p ) {
            $c = $p->post_content;
            $found = false;

            // Direct ID match on common Divi attributes
            $quoted_id = preg_quote( (string) $attachment_id, '/' );
            if ( preg_match( '/\\b(image_id|background_image_id|logo_image_id|featured_image_id)\\s*=\\s*\"' . $quoted_id . '\"/i', $c ) ) {
                $found = true;
            }
            // ID inside gallery ids/galleries
            if ( ! $found && preg_match( '/\\b(ids|gallery_ids)\\s*=\\s*\"[^\"]*\b' . $quoted_id . '\b[^\"]*\"/i', $c ) ) {
                $found = true;
            }
            // URL attributes converted to attachment ID
            if ( ! $found ) {
                if ( preg_match_all( '/\\b(src|url|image_url|background_image)\\s*=\\s*\"([^\"]+)\"/i', $c, $m_urls ) ) {
                    foreach ( $m_urls[2] as $url ) {
                        $id = attachment_url_to_postid( $url );
                        if ( $id && intval( $id ) === intval( $attachment_id ) ) {
                            $found = true;
                            break;
                        }
                    }
                }
            }
            if ( $found ) {
                $results[] = (object) array( 'ID' => $p->ID, 'post_title' => $p->post_title, 'post_type' => $p->post_type );
            }
        }

        // Deduplicate results by post ID (and special site icon key)
        $unique_map = array();
        $unique_results = array();
        foreach ( $results as $r ) {
            $key = ( isset( $r->post_type ) && $r->post_type === 'site_icon' ) ? 'site_icon' : (string) ( isset( $r->ID ) ? $r->ID : '' );
            if ( $key === '' ) { continue; }
            if ( ! isset( $unique_map[ $key ] ) ) {
                $unique_map[ $key ] = true;
                $unique_results[] = (object) array(
                    'ID' => isset( $r->ID ) ? $r->ID : 0,
                    'post_title' => isset( $r->post_title ) ? $r->post_title : '',
                    'post_type' => isset( $r->post_type ) ? $r->post_type : '',
                );
            }
        }

        // Cache the results for 1 hour
        wp_cache_set( $cache_key, $unique_results, 'media_tracker', HOUR_IN_SECONDS );

        return $unique_results;
    }
}
