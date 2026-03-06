<?php

namespace Media_Tracker\Admin;

defined( 'ABSPATH' ) || exit;

use WP_List_Table;

/**
 * Custom WP_List_Table implementation for managing unused media attachments.
 */
class Unused_Media_List extends WP_List_Table {

    /**
     * The search term used to filter media items.
     *
     * @var string
     */
    public $search;

    /**
     * Author ID for filtering unused media attachments.
     *
     * @var int
     */
    public $author_id;

    /**
     * Constructor method to initialize the list table.
     *
     * @param string   $search    The search term for filtering media.
     * @param int|null $author_id Optional. Author ID to filter media by author.
     */
    public function __construct( $search, $author_id = null ) {
        parent::__construct( array(
            'singular' => 'media',
            'plural'   => 'media',
            'ajax'     => false,
         ) );

        $this->search    = $search;
        $this->author_id = $author_id;
        $this->process_bulk_action();
    }

    /**
     * Define the columns that should be displayed in the table.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'post_title'  => __( 'File', 'media-tracker' ),
            'post_author' => __( 'Author', 'media-tracker' ),
            'size'        => __( 'Size', 'media-tracker' ),
            'post_date'   => __( 'Date', 'media-tracker' ),
        );
    }

    /**
     * Render default column output.
     *
     * @param object $item        The current item being displayed.
     * @param string $column_name The name of the column.
     * @return string HTML output for the column.
     */
    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'post_title':
                $edit_link     = get_edit_post_link( $item->ID );
                $delete_link   = get_delete_post_link( $item->ID, '', true );
                $view_link     = wp_get_attachment_url( $item->ID );
                $thumbnail     = wp_get_attachment_image( $item->ID, [ 60, 60 ], true );
                $file_path     = get_attached_file( $item->ID );
                $file_name     = $item->post_title;
                $file_extension = $file_path ? pathinfo( $file_path, PATHINFO_EXTENSION ) : '';
                $full_file_name = $file_name . '.' . $file_extension;

                // Define row actions
                $actions = [
                    'edit'    => '<span class="edit"><a href="' . esc_url( $edit_link ) . '">' . __( 'Edit', 'media-tracker' ) . '</a></span>',
                    'view'    => '<span class="view"><a href="' . esc_url( $view_link ) . '">' . __( 'View', 'media-tracker' ) . '</a></span>',
                    'delete'  => '<span class="delete"><a href="' . esc_url( $delete_link ) . '" class="submitdelete">' . __( 'Delete Permanently', 'media-tracker' ) . '</a></span>'
                ];

                /* translators: %s: post title */
                $aria_label = sprintf( __( '"%s" (Edit)', 'media-tracker' ), $item->post_title );

                $output = '<strong class="has-media-icon">
                    <a href="' . esc_url( $edit_link ) . '" aria-label="' . esc_attr( $aria_label ) . '">
                        <span class="media-icon image-icon">' . $thumbnail . '</span>
                        ' . esc_html( $file_name ) . '
                    </a>
                </strong>
                <p class="filename">
                    <span class="screen-reader-text">' . __( 'File name:', 'media-tracker' ) . '</span>
                    ' . esc_html( $full_file_name ) . '
                </p>';

                // Append row actions
                $output .= $this->row_actions( $actions );
                return $output;
            case 'post_author':
                $author_name = get_the_author_meta( 'display_name', $item->post_author );
                $author_url  = add_query_arg(
                    [
                        'page'   => 'media-tracker',
                        'author' => $item->post_author,
                    ],
                    admin_url( 'upload.php' )
                );
                return '<a href="' . esc_url( $author_url ) . '">' . esc_html( $author_name ) . '</a>';
            case 'size':
                $file_path = get_attached_file( $item->ID );
                if ( $file_path && file_exists( $file_path ) ) {
                    return size_format( filesize( $file_path ) );
                } else {
                    return '-';
                }
            case 'post_date':
                return date_i18n( 'Y/m/d', strtotime( $item->post_date ) );
            default:
                return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
        }
    }

    protected function get_sortable_columns() {
        return array(
            'post_title'  => array( 'post_title', false ),
            'post_author' => array( 'post_author', false ),
            'post_date'   => array( 'post_date', false ),
            'size'        => array( 'size', false ),
        );
    }

    protected function get_items_per_page( $option, $default = 10 ) {
        $per_page = (int) get_user_meta( get_current_user_id(), $option, true );
        return empty( $per_page ) || $per_page < 1 ? $default : $per_page;
    }

    private function get_used_media_ids() {
        global $wpdb;

        $used_image_ids = [];

        // Check if we have a cached progress
        $progress_key = 'media_scan_progress_' . get_current_user_id();
        $progress = get_transient( $progress_key );

        // Ensure progress structure exists and is valid
        if ( ! is_array( $progress ) ) {
            $progress = array(
                'step' => 0,
                'total_steps' => 6,
                'current_step' => 'Starting scan...',
                'used_ids' => array()
            );
        } else {
            $progress['step'] = isset( $progress['step'] ) ? (int) $progress['step'] : 0;
            $progress['total_steps'] = isset( $progress['total_steps'] ) ? (int) $progress['total_steps'] : 6;
            $progress['current_step'] = isset( $progress['current_step'] ) ? (string) $progress['current_step'] : 'Starting scan...';
            if ( ! isset( $progress['used_ids'] ) || ! is_array( $progress['used_ids'] ) ) {
                $progress['used_ids'] = array();
            }
        }

        wp_raise_memory_limit( 'admin' );
        if ( function_exists( 'set_time_limit' ) ) {
            // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Scanning can take a long time.
            set_time_limit( 300 );
        }

        // Step 1: Featured Images
        if ( $progress['step'] < 1 ) {
            $progress['current_step'] = 'Scanning featured images...';
            set_transient( $progress_key, $progress, 300 );

            $featured_images = $this->get_cached_db_result("
                SELECT DISTINCT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_thumbnail_id'
                AND meta_value != '0'
                AND meta_value != ''
            ");
            if ( $featured_images ) {
                $progress['used_ids'] = array_merge( $progress['used_ids'], array_map( 'intval', $featured_images ) );
            }
            $progress['step'] = 1;
        }

        // Step 2: WooCommerce Images
        if ( $progress['step'] < 2 && class_exists( 'WooCommerce' ) ) {
            $progress['current_step'] = 'Scanning WooCommerce images...';
            set_transient( $progress_key, $progress, 300 );

            $gallery_images = $this->get_cached_db_result("
                SELECT DISTINCT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_product_image_gallery'
                AND meta_value != ''
                AND meta_value IS NOT NULL
            ");

            foreach ( $gallery_images as $gallery_string ) {
                if ( ! empty( $gallery_string ) ) {
                    $gallery_ids = explode( ',', $gallery_string );
                    $progress['used_ids'] = array_merge( $progress['used_ids'], array_map( 'intval', array_filter( $gallery_ids ) ) );
                }
            }

            $variation_images = $this->get_cached_db_result("
                SELECT DISTINCT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_thumbnail_id'
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = 'product_variation'
                )
                AND meta_value != '0'
                AND meta_value != ''
            ");
            if ( $variation_images ) {
                $progress['used_ids'] = array_merge( $progress['used_ids'], array_map( 'intval', $variation_images ) );
            }
            $progress['step'] = 2;
        }

        $used_image_ids = $progress['used_ids'];

        // Ensure featured images are always included
        $featured_images = $this->get_cached_db_result("
            SELECT DISTINCT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_thumbnail_id'
            AND meta_value != '0'
            AND meta_value != ''
        ");
        if ( $featured_images ) {
            $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $featured_images ) );
        }

        if ( class_exists( 'WooCommerce' ) ) {
            $gallery_images = $this->get_cached_db_result("
                SELECT DISTINCT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_product_image_gallery'
                AND meta_value != ''
                AND meta_value IS NOT NULL
            ");

            foreach ( $gallery_images as $gallery_string ) {
                if ( ! empty( $gallery_string ) ) {
                    $gallery_ids = explode( ',', $gallery_string );
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', array_filter( $gallery_ids ) ) );
                }
            }

            $variation_images = $this->get_cached_db_result("
                SELECT DISTINCT meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_thumbnail_id'
                AND post_id IN (
                    SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = 'product_variation'
                )
                AND meta_value != '0'
                AND meta_value != ''
            ");
            if ( $variation_images ) {
                $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $variation_images ) );
            }
        }

        if ( class_exists( 'ACF' ) ) {
            $acf_meta_values = $this->get_cached_db_result("
                SELECT DISTINCT pm.meta_value
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_status = 'publish'
                AND pm.meta_key NOT LIKE '_%'
                AND (
                    pm.meta_value REGEXP '^[0-9]+$'
                    OR pm.meta_value LIKE '%\"[0-9]+\"%'
                    OR pm.meta_value LIKE '%i:[0-9]+;%'
                    OR pm.meta_value LIKE '%,[0-9]+,%'
                )
            ");

            foreach ( $acf_meta_values as $meta_value ) {
                if ( empty( $meta_value ) ) {
                    continue;
                }

                if ( is_numeric( $meta_value ) && $meta_value > 0 ) {
                    $used_image_ids[] = intval( $meta_value );
                    continue;
                }

                if ( is_serialized( $meta_value ) ) {
                    $unserialized = @unserialize( $meta_value );
                    if ( is_array( $unserialized ) ) {
                        array_walk_recursive( $unserialized, function( $item, $key ) use ( &$used_image_ids ) {
                            // Skip common non-ID numeric keys found in attachment metadata and other places
                            if ( in_array( $key, ['width', 'height', 'file_size', 'filesize', 'size', 'length', 'bitrate', 'duration', 'uploaded', 'year', 'month', 'day', 'percent', 'bytes', 'time', 'lossy', 'keep_exif', 'api_version', 'size_before', 'size_after'], true ) ) {
                                return;
                            }

                            if ( is_numeric( $item ) && $item > 0 && $item < 999999999 ) {
                                $used_image_ids[] = intval( $item );
                            }
                        });
                    }
                    continue;
                }

                if ( strpos( $meta_value, '"' ) !== false ) {
                    $json_decoded = json_decode( $meta_value, true );
                    if ( is_array( $json_decoded ) ) {
                        array_walk_recursive( $json_decoded, function( $item, $key ) use ( &$used_image_ids ) {
                            // Skip common non-ID numeric keys found in attachment metadata and other places
                            if ( in_array( $key, ['width', 'height', 'file_size', 'filesize', 'size', 'length', 'bitrate', 'duration', 'uploaded', 'year', 'month', 'day'], true ) ) {
                                return;
                            }

                            if ( is_numeric( $item ) && $item > 0 && $item < 999999999 ) {
                                $used_image_ids[] = intval( $item );
                            }
                        });
                        continue;
                    }
                }

                if ( strpos( $meta_value, ',' ) !== false ) {
                    $comma_values = explode( ',', $meta_value );
                    foreach ( $comma_values as $value ) {
                        $value = trim( $value );
                        if ( is_numeric( $value ) && $value > 0 ) {
                            $used_image_ids[] = intval( $value );
                        }
                    }
                }

                if ( preg_match_all( '/i:(\d+);/', $meta_value, $matches ) ) {
                    foreach ( $matches[1] as $id ) {
                        if ( $id > 0 ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }

                if ( preg_match_all( '/"(\d+)"/', $meta_value, $matches ) ) {
                    foreach ( $matches[1] as $id ) {
                        if ( $id > 0 && strlen( $id ) <= 10 ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }
            }

            $acf_specific_query = $this->get_cached_db_result("
                SELECT DISTINCT pm.meta_value
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
                AND pm2.meta_key = CONCAT('_', pm.meta_key)
                JOIN {$wpdb->posts} acf ON pm2.meta_value = acf.post_name
                AND acf.post_type = 'acf-field'
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_status = 'publish'
                AND (
                    acf.post_content LIKE '%\"type\";s:5:\"image\"%'
                    OR acf.post_content LIKE '%\"type\";s:7:\"gallery\"%'
                    OR acf.post_content LIKE '%\"type\";s:4:\"file\"%'
                )
            ");

            foreach ( $acf_specific_query as $acf_value ) {
                if ( empty( $acf_value ) ) {
                    continue;
                }

                if ( is_numeric( $acf_value ) && $acf_value > 0 ) {
                    $used_image_ids[] = intval( $acf_value );
                } else {
                    if ( is_serialized( $acf_value ) ) {
                        $unserialized = @unserialize( $acf_value );
                        if ( is_array( $unserialized ) ) {
                            array_walk_recursive( $unserialized, function( $item, $key ) use ( &$used_image_ids ) {
                                // Skip common non-ID numeric keys found in attachment metadata and other places
                                if ( in_array( $key, ['width', 'height', 'file_size', 'filesize', 'size', 'length', 'bitrate', 'duration', 'uploaded', 'year', 'month', 'day'], true ) ) {
                                    return;
                                }

                                if ( is_numeric( $item ) && $item > 0 && $item < 999999999 ) {
                                    $used_image_ids[] = intval( $item );
                                }
                            });
                        }
                    }
                }
            }
        }

        // Check Elementor Data
        $elementor_meta_values = $this->get_cached_db_result("
            SELECT meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_elementor_data'
            AND meta_value != ''
        ");

        if ( $elementor_meta_values ) {
            foreach ( $elementor_meta_values as $meta_value ) {
                $data = json_decode( $meta_value, true );
                if ( is_array( $data ) ) {
                    array_walk_recursive( $data, function( $item, $key ) use ( &$used_image_ids ) {
                        if ( $key === 'id' && is_numeric( $item ) && $item > 0 ) {
                            $used_image_ids[] = intval( $item );
                        }
                    });
                }
            }
        }

        $batch_size = 100;
        $offset = 0;

        while ( true ) {
            $sql = $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = 'publish' LIMIT %d OFFSET %d",
                '%wp-image-%',
                $batch_size,
                $offset
            );
            $posts_with_content = $this->get_cached_db_result( $sql, 'results' );

            if ( empty( $posts_with_content ) ) {
                break;
            }

            foreach ( $posts_with_content as $post ) {
                preg_match_all( '/wp-image-(\d+)/', $post->post_content, $matches );
                if ( ! empty( $matches[1] ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
            }

            $offset += $batch_size;
        }

        // Scan Gutenberg image blocks that may not include the wp-image- class
        $offset_blocks = 0;
        while ( true ) {
            $sql_blocks = $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = 'publish' LIMIT %d OFFSET %d",
                '%wp:image%',
                $batch_size,
                $offset_blocks
            );
            $posts_with_blocks = $this->get_cached_db_result( $sql_blocks, 'results' );

            if ( empty( $posts_with_blocks ) ) {
                break;
            }

            foreach ( $posts_with_blocks as $post ) {
                if ( preg_match_all( '/<!--\s*wp:image\s*{[^}]*\"id\"\s*:\s*(\d+)/', $post->post_content, $matches ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
            }

            $offset_blocks += $batch_size;
        }

        // Scan other Gutenberg media blocks (Cover, Media & Text, Video, Audio, File)
        $offset_blocks_ext = 0;
        while ( true ) {
            $sql_blocks_ext = $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE (
                    post_content LIKE %s OR
                    post_content LIKE %s OR
                    post_content LIKE %s OR
                    post_content LIKE %s OR
                    post_content LIKE %s
                ) AND post_status = 'publish' LIMIT %d OFFSET %d",
                '%wp:cover%',
                '%wp:media-text%',
                '%wp:video%',
                '%wp:audio%',
                '%wp:file%',
                $batch_size,
                $offset_blocks_ext
            );
            $posts_with_blocks_ext = $this->get_cached_db_result( $sql_blocks_ext, 'results' );

            if ( empty( $posts_with_blocks_ext ) ) {
                break;
            }

            foreach ( $posts_with_blocks_ext as $post ) {
                // Cover block
                if ( preg_match_all( '/<!--\s*wp:cover\s*{[^}]*\"id\"\s*:\s*(\d+)/', $post->post_content, $matches ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
                // Media & Text block (uses mediaId)
                if ( preg_match_all( '/<!--\s*wp:media-text\s*{[^}]*\"mediaId\"\s*:\s*(\d+)/', $post->post_content, $matches ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
                // Video block
                if ( preg_match_all( '/<!--\s*wp:video\s*{[^}]*\"id\"\s*:\s*(\d+)/', $post->post_content, $matches ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
                // Audio block
                if ( preg_match_all( '/<!--\s*wp:audio\s*{[^}]*\"id\"\s*:\s*(\d+)/', $post->post_content, $matches ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
                // File block
                if ( preg_match_all( '/<!--\s*wp:file\s*{[^}]*\"id\"\s*:\s*(\d+)/', $post->post_content, $matches ) ) {
                    $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                }
            }

            $offset_blocks_ext += $batch_size;
        }

        // Scan gallery shortcodes and Gutenberg gallery blocks
        $offset_gallery = 0;
        while ( true ) {
            $sql_gallery = $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = 'publish' LIMIT %d OFFSET %d",
                '%gallery%',
                $batch_size,
                $offset_gallery
            );
            $posts_with_gallery = $this->get_cached_db_result( $sql_gallery, 'results' );

            if ( empty( $posts_with_gallery ) ) {
                break;
            }

            foreach ( $posts_with_gallery as $post ) {
                if ( preg_match_all( '/\[gallery[^\]]*ids=\"([^\"]+)\"/', $post->post_content, $matches ) ) {
                    foreach ( $matches[1] as $csv ) {
                        $ids = array_filter( array_map( 'intval', explode( ',', $csv ) ) );
                        if ( $ids ) {
                            $used_image_ids = array_merge( $used_image_ids, $ids );
                        }
                    }
                }
                if ( preg_match_all( '/<!--\s*wp:gallery\s*{[^}]*\"ids\"\s*:\s*\[([^\]]+)\]/', $post->post_content, $matches2 ) ) {
                    foreach ( $matches2[1] as $list ) {
                        $ids = array_filter( array_map( 'intval', preg_split( '/\s*,\s*/', preg_replace( '/[^\d,]/', '', $list ) ) ) );
                        if ( $ids ) {
                            $used_image_ids = array_merge( $used_image_ids, $ids );
                        }
                    }
                }
            }

            $offset_gallery += $batch_size;
        }

        // Elementor: scan all posts with _elementor_data in batches and extract image IDs more accurately
        $el_offset = 0;
        while ( true ) {
            $sql_el = $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' LIMIT %d OFFSET %d",
                $batch_size,
                $el_offset
            );
            $elementor_posts = $this->get_cached_db_result( $sql_el );

            if ( empty( $elementor_posts ) ) {
                break;
            }

            foreach ( $elementor_posts as $post_id ) {
                $raw = get_post_meta( $post_id, '_elementor_data', true );
                if ( empty( $raw ) ) {
                    continue;
                }
                $data = json_decode( $raw, true );
                if ( ! is_array( $data ) ) {
                    continue;
                }

                $extract_ids = function( $node ) use ( &$used_image_ids, &$extract_ids ) {
                    if ( is_array( $node ) ) {
                        // Direct image-like object: { id: 123, url: "..." }
                        if ( isset( $node['id'] ) && is_numeric( $node['id'] ) ) {
                            $has_url = isset( $node['url'] ) && is_string( $node['url'] );
                            $looks_like_image = isset( $node['size'] ) || isset( $node['source'] ) || isset( $node['image'] ) || isset( $node['image_size'] );
                            if ( $has_url || $looks_like_image ) {
                                $used_image_ids[] = intval( $node['id'] );
                            }
                        }

                        // Check for 'ids' key (arrays or comma-separated strings), common in galleries
                        if ( isset( $node['ids'] ) ) {
                            $ids_val = $node['ids'];
                            if ( is_array( $ids_val ) ) {
                                foreach ( $ids_val as $id ) {
                                    if ( is_numeric( $id ) && $id > 0 ) {
                                        $used_image_ids[] = intval( $id );
                                    }
                                }
                            } elseif ( is_string( $ids_val ) ) {
                                $ids = array_filter( array_map( 'intval', explode( ',', $ids_val ) ) );
                                if ( ! empty( $ids ) ) {
                                    $used_image_ids = array_merge( $used_image_ids, $ids );
                                }
                            }
                        }

                        foreach ( $node as $v ) {
                            $extract_ids( $v );
                        }
                    }
                };

                $extract_ids( $data );
            }

            $el_offset += $batch_size;
        }

        // Divi Builder: scan posts using Divi shortcodes and extract image IDs/URLs
        $divi_offset = 0;
        while ( true ) {
            $sql_divi = $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT %d OFFSET %d",
                '%[et_pb_%',
                $batch_size,
                $divi_offset
            );
            $divi_posts = $this->get_cached_db_result( $sql_divi, 'results' );

            if ( empty( $divi_posts ) ) {
                break;
            }

            foreach ( $divi_posts as $post ) {
                $content = $post->post_content;

                // IDs stored directly on modules (e.g., image_id, background_image_id)
                if ( preg_match_all( '/\\b(image_id|background_image_id|logo_image_id|featured_image_id)\\s*=\\s*\"(\\d+)\"/i', $content, $m_ids ) ) {
                    foreach ( $m_ids[2] as $id ) {
                        $id = intval( $id );
                        if ( $id > 0 ) {
                            $used_image_ids[] = $id;
                        }
                    }
                }

                // Gallery IDs in Divi gallery module (ids or gallery_ids)
                if ( preg_match_all( '/\\b(ids|gallery_ids)\\s*=\\s*\"([\\d,\\s]+)\"/i', $content, $m_gal ) ) {
                    foreach ( $m_gal[2] as $csv ) {
                        $ids = array_filter( array_map( 'intval', preg_split( '/\\s*,\\s*/', $csv ) ) );
                        if ( $ids ) {
                            $used_image_ids = array_merge( $used_image_ids, $ids );
                        }
                    }
                }

                // URLs stored on modules (src, url, image_url, background_image)
                if ( preg_match_all( '/\\b(src|url|image_url|background_image)\\s*=\\s*\"([^\"]+)\"/i', $content, $m_urls ) ) {
                    foreach ( $m_urls[2] as $url ) {
                        $id = attachment_url_to_postid( $url );
                        if ( $id ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }
            }

            $divi_offset += $batch_size;
        }

        // Generic: scan direct uploads URLs in post content and map to attachment IDs
        $offset_urls = 0;
        while ( true ) {
            $sql_urls = $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT %d OFFSET %d",
                '%wp-content/uploads%',
                $batch_size,
                $offset_urls
            );
            $posts_with_urls = $this->get_cached_db_result( $sql_urls, 'results' );

            if ( empty( $posts_with_urls ) ) {
                break;
            }

            foreach ( $posts_with_urls as $post ) {
                // Use double-quoted PHP string to avoid premature termination when matching single quotes inside the pattern
                if ( preg_match_all( "/https?:\/\/[^\"']+\/wp-content\/uploads\/[^\"']+/i", $post->post_content, $m_url_all ) ) {
                    foreach ( $m_url_all[0] as $url ) {
                        $id = attachment_url_to_postid( $url );
                        if ( $id ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }
            }

            $offset_urls += $batch_size;
        }

        // Scan post_excerpt (e.g. WooCommerce Short Description)
        $offset_excerpt = 0;
        while ( true ) {
            $sql_excerpt = $wpdb->prepare(
                "SELECT ID, post_excerpt FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_excerpt LIKE %s OR post_excerpt LIKE %s) LIMIT %d OFFSET %d",
                '%wp-content/uploads%',
                '%wp-image-%',
                $batch_size,
                $offset_excerpt
            );
            $posts_with_excerpt = $this->get_cached_db_result( $sql_excerpt, 'results' );

            if ( empty( $posts_with_excerpt ) ) {
                break;
            }

            foreach ( $posts_with_excerpt as $post ) {
                // Check for wp-image- ID
                if ( preg_match_all( '/wp-image-(\d+)/', $post->post_excerpt, $matches ) ) {
                    if ( ! empty( $matches[1] ) ) {
                        $used_image_ids = array_merge( $used_image_ids, array_map( 'intval', $matches[1] ) );
                    }
                }

                // Check for direct URLs
                if ( preg_match_all( "/https?:\/\/[^\"']+\/wp-content\/uploads\/[^\"']+/i", $post->post_excerpt, $m_url_all ) ) {
                    foreach ( $m_url_all[0] as $url ) {
                        $id = attachment_url_to_postid( $url );
                        if ( $id ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }
            }

            $offset_excerpt += $batch_size;
        }

        // Scan postmeta for raw URLs (custom fields, metaboxes)
        $offset_meta = 0;
        while ( true ) {
            $sql_meta = $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s LIMIT %d OFFSET %d",
                '%wp-content/uploads%',
                $batch_size,
                $offset_meta
            );
            $meta_values = $this->get_cached_db_result( $sql_meta, 'col' );

            if ( empty( $meta_values ) ) {
                break;
            }

            foreach ( $meta_values as $val ) {
                // Check for direct URLs
                if ( preg_match_all( "/https?:\/\/[^\"']+\/wp-content\/uploads\/[^\"']+/i", $val, $m_url_all ) ) {
                    foreach ( $m_url_all[0] as $url ) {
                        // Clean up URL (remove query strings, etc if needed, though attachment_url_to_postid handles some)
                        $id = attachment_url_to_postid( $url );
                        if ( $id ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }
            }
            $offset_meta += $batch_size;
        }

        // Scan options for raw URLs (theme settings, custom options)
        $offset_options = 0;
        while ( true ) {
            $sql_options = $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_value LIKE %s LIMIT %d OFFSET %d",
                '%wp-content/uploads%',
                $batch_size,
                $offset_options
            );
            $option_values = $this->get_cached_db_result( $sql_options, 'col' );

            if ( empty( $option_values ) ) {
                break;
            }

            foreach ( $option_values as $val ) {
                // Check for direct URLs
                if ( preg_match_all( "/https?:\/\/[^\"']+\/wp-content\/uploads\/[^\"']+/i", $val, $m_url_all ) ) {
                    foreach ( $m_url_all[0] as $url ) {
                        $id = attachment_url_to_postid( $url );
                        if ( $id ) {
                            $used_image_ids[] = intval( $id );
                        }
                    }
                }
            }
            $offset_options += $batch_size;
        }

        $site_icon_id = get_option( 'site_icon' );
        if ( $site_icon_id ) {
            $used_image_ids[] = intval( $site_icon_id );
        }

        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $used_image_ids[] = intval( $custom_logo_id );
        }

        $header_image_id = get_theme_mod( 'header_image_data' );
        if ( is_array( $header_image_id ) && isset( $header_image_id['attachment_id'] ) ) {
            $used_image_ids[] = intval( $header_image_id['attachment_id'] );
        }

        $background_image_id = get_theme_mod( 'background_image_thumb' );
        if ( $background_image_id ) {
            $used_image_ids[] = intval( $background_image_id );
        }

        $used_image_ids = array_unique( array_filter( array_map( 'intval', $used_image_ids ), function( $id ) {
            return $id > 0 && $id < 999999999;
        }));

        return $used_image_ids;
    }

    public function scan_and_save_snapshot() {
        global $wpdb;

        // Force calculation of used IDs
        $used_image_ids = $this->get_used_media_ids();

        // Get all attachment IDs
        $all_attachments = $this->get_cached_db_result( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'" );

        // Calculate unused IDs
        $unused_ids = array_diff( $all_attachments, $used_image_ids );

        // Filter and sanitize
        $unused_ids = array_unique( array_filter( array_map( 'intval', $unused_ids ) ) );

        // Calculate size
        $total_size = 0;
        foreach ( $unused_ids as $id ) {
            $file_path = get_attached_file( $id );
            if ( $file_path && file_exists( $file_path ) ) {
                $total_size += filesize( $file_path );
            }
        }

        // Save snapshot and stats
        update_option( 'media_tracker_unused_ids_snapshot', $unused_ids, false );
        update_option( 'unused_media_last_cache_time', time() );
        update_option( 'media_tracker_unused_count_last_scan', count( $unused_ids ) );
        update_option( 'media_tracker_unused_size_last_scan', $total_size );

        // Invalidate dashboard stats cache to ensure overview tab is updated
        delete_transient( 'media_tracker_dashboard_stats_v8' );

        return count($unused_ids);
    }

    public function prepare_items() {
        global $wpdb;

        $this->display_delete_message();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Search request is a GET request and safe.
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

        $per_page     = $this->get_items_per_page( 'unused_media_cleaner_per_page', 10 );
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        // Retrieve from snapshot
        $unused_image_ids = get_option( 'media_tracker_unused_ids_snapshot', array() );

        // Handle search if needed (filter snapshot IDs by search term)
        // This requires a query if search is present, but restricted to snapshot IDs.

        $where_conditions = [
            "p.post_type = 'attachment'",
            "p.post_status = 'inherit'"
        ];

        if ( empty( $unused_image_ids ) ) {
            // No unused media found in snapshot (or not scanned yet)
            $this->items = array();
            $total_items = 0;
        } else {
            $args = array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post__in'       => $unused_image_ids,
                'posts_per_page' => $per_page,
                'paged'          => $current_page,
                'orderby'        => $orderby_param,
                'order'          => $order_param,
            );

            if ( $this->author_id ) {
                $args['author'] = $this->author_id;
            }

            if ( $search ) {
                $args['s'] = $search;
            }

            $query = new \WP_Query( $args );
            $this->items = $query->posts;
            $total_items = $query->found_posts;
        }

        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = $this->get_sortable_columns();
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => $per_page > 0 ? ceil( $total_items / $per_page ) : 0,
        ) );
    }

    private function should_invalidate_cache() {
        // Manual mode: only refresh cache when a scan is run.
        // If enabled (default true), do NOT auto invalidate based on site activity.
        $manual_mode = get_option( 'media_tracker_manual_scan', true );
        if ( $manual_mode ) {
            return false;
        }

        global $wpdb;

        $last_cache_time = get_option( 'unused_media_last_cache_time', 0 );

        $recent_media_activity = $this->get_cached_db_result( $wpdb->prepare(
            "SELECT COUNT(*)\n            FROM {$wpdb->posts}\n            WHERE post_type = 'attachment'\n            AND post_modified_gmt > %s",
            gmdate( 'Y-m-d H:i:s', $last_cache_time )
        ), 'var' );

        $recent_post_activity = $this->get_cached_db_result( $wpdb->prepare(
            "SELECT COUNT(*)\n            FROM {$wpdb->posts}\n            WHERE post_type IN ('post', 'page', 'product')\n            AND post_modified_gmt > %s",
            gmdate( 'Y-m-d H:i:s', $last_cache_time )
        ), 'var' );

        return ( $recent_media_activity > 0 || $recent_post_activity > 0 );
    }

    public function force_clear_cache() {
        $this->clear_cache();
        delete_option( 'unused_media_last_cache_time' );
    }

    public function get_total_items( $search = '', $force_fresh = false ) {
        global $wpdb;

        // Retrieve from snapshot
        $unused_image_ids = get_option( 'media_tracker_unused_ids_snapshot', array() );

        if ( empty( $unused_image_ids ) ) {
            return 0;
        }

        $where_conditions = [
            "p.post_type = 'attachment'",
            "p.post_status = 'inherit'"
        ];

        $ids_placeholder = implode( ',', array_map( 'intval', $unused_image_ids ) );
        $where_conditions[] = "p.ID IN ($ids_placeholder)";

        if ( $this->author_id ) {
            $where_conditions[] = $wpdb->prepare( 'p.post_author = %d', $this->author_id );
        }

        if ( $search ) {
            $where_conditions[] = $wpdb->prepare( 'p.post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

        $query = "
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            $where_clause
        ";

        return $this->get_cached_db_result( $query, 'var' );
    }

    public function get_fresh_total_items( $search = '' ) {
        return $this->get_total_items( $search, true );
    }

    /**
     * Get all unused media IDs.
     *
     * @param string $search Optional search term.
     * @return array Array of unused media IDs.
     */
    public function get_unused_media_ids( $search = '' ) {
        global $wpdb;

        // Retrieve from snapshot
        $unused_image_ids = get_option( 'media_tracker_unused_ids_snapshot', array() );

        if ( empty( $unused_image_ids ) ) {
            return array();
        }

        // Build query to get all unused media
        $where_conditions = [
            "p.post_type = 'attachment'",
            "p.post_status = 'inherit'"
        ];

        $ids_placeholder = implode( ',', array_map( 'intval', $unused_image_ids ) );
        $where_conditions[] = "p.ID IN ($ids_placeholder)";

        if ( $this->author_id ) {
            $where_conditions[] = $wpdb->prepare( 'p.post_author = %d', $this->author_id );
        }

        if ( $search ) {
            $where_conditions[] = $wpdb->prepare( 'p.post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );

        $query = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            $where_clause
        ";

        return $this->get_cached_db_result( $query );
    }

    protected function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="media[]" value="%s" />', $item->ID
        );
    }

    protected function get_bulk_actions() {
        return [
            'delete' => __( 'Delete permanently', 'media-tracker' ),
        ];
    }

    protected function process_bulk_action() {
        if ( 'delete' === $this->current_action() ) {
            // Verify nonce for bulk actions
            check_admin_referer( 'bulk-media' );

            $media_ids = isset( $_REQUEST['media'] ) ? array_map( 'absint', (array) $_REQUEST['media'] ) : [];

            if ( ! empty( $media_ids ) ) {
                foreach ( $media_ids as $media_id ) {
                    // Capability check per attachment
                    if ( current_user_can( 'delete_post', $media_id ) ) {
                        wp_delete_attachment( $media_id, true );
                    }
                }

                // Clear cache after deletion
                $this->clear_cache();

                $deleted_count = count( $media_ids );

                /* translators: %d: number of deleted media files */
                set_transient( 'unused_media_delete_message', sprintf( __( '%d media file(s) deleted successfully.', 'media-tracker' ), $deleted_count ), 30 );
            }
        }
    }

    private function clear_cache() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_unused_media_%'" );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_unused_media_%'" );

        delete_option( 'unused_media_last_cache_time' );
    }

    /**
     * Helper to execute DB queries with caching.
     *
     * @param string $query The SQL query.
     * @param string $type  The type of query result ('col', 'var', 'results').
     * @return mixed Query result.
     */
    private function get_cached_db_result( $query, $type = 'col' ) {
        global $wpdb;

        $key = 'mt_db_' . md5( $query );
        $group = 'media_tracker';
        $result = wp_cache_get( $key, $group );

        if ( false === $result ) {
            if ( 'col' === $type ) {
                $result = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
            } elseif ( 'results' === $type ) {
                $result = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
            } elseif ( 'var' === $type ) {
                $result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
            }
            wp_cache_set( $key, $result, $group, 300 );
        }
        return $result;
    }

    public function display_delete_message() {
        if ( $message = get_transient( 'unused_media_delete_message' ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
            delete_transient( 'unused_media_delete_message' );
        }
    }

    public function display() {
        wp_nonce_field( 'bulk-media' );
        parent::display();
    }
}
