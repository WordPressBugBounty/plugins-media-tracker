<?php

namespace Media_Tracker\Admin;

defined( 'ABSPATH' ) || exit;

use Exception;

// Define constants for duplicate image processing
if ( ! defined( 'MEDIA_TRACKER_DUPLICATE_BATCH_SIZE' ) ) {
    define( 'MEDIA_TRACKER_DUPLICATE_BATCH_SIZE', 300 ); // Batch size for duplicate image processing
}

class Duplicate_Images {

    /**
     * Constructor to initialize the class and set up hooks.
     */
    public function __construct() {
        // Hooks to add filters and handle processing
        add_action('restrict_manage_posts', array($this, 'add_custom_media_filter'));
        add_action('pre_get_posts', array($this, 'filter_media_library_items'));
        add_action('media_tracker_batch_process', array($this, 'process_image_hashes_batch'));
        add_action('wp_ajax_get_duplicate_images', array($this, 'get_duplicate_images_via_ajax'));
        add_action('wp_ajax_reset_duplicate_hashes', array($this, 'reset_duplicate_hashes_via_ajax'));
        add_action('wp_ajax_mt_process_batch', array($this, 'process_batch_via_ajax'));
        add_action('wp_ajax_mt_delete_duplicate_images', array($this, 'delete_duplicate_images_via_ajax'));
    }

    /**
     * Adds a custom filter dropdown to the Media Library.
     */
    public function add_custom_media_filter() {
        $screen = get_current_screen();

        if ('upload' !== $screen->id) {
            return;
        }

        $filter_value = isset($_GET['media_duplicate_filter']) ? sanitize_text_field(wp_unslash($_GET['media_duplicate_filter'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <label for="media-duplicate-filter" class="media-duplicate-filter-label" style="margin-right:6px; display:inline-flex; align-items:center; gap:4px;">
            <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
            <?php esc_html_e('Duplicates', 'media-tracker'); ?>
        </label>
        <select name="media_duplicate_filter" id="media-duplicate-filter" aria-label="<?php esc_attr_e('Duplicate images filter', 'media-tracker'); ?>" aria-describedby="media-duplicate-filter-help">
            <option value=""><?php esc_html_e('All Media', 'media-tracker'); ?></option>
            <option value="duplicates" <?php selected($filter_value, 'duplicates'); ?>>
                <?php esc_html_e('Show Duplicate Images', 'media-tracker'); ?>
            </option>
        </select>
        <button type="button" id="rescan-duplicates-btn" class="button" style="margin-left:8px;">
            <span class="dashicons dashicons-update-alt" style="vertical-align:middle;"></span>
            <?php esc_html_e('Re-scan', 'media-tracker'); ?>
        </button>
        <span id="rescan-status" style="margin-left:8px; display:none;"></span>
        <?php
    }

    /**
     * Filters Media Library items to show only duplicate images if the custom filter is set.
     *
     * @param WP_Query $query The current WP_Query instance.
     */
    public function filter_media_library_items($query) {
        global $pagenow;

        if ('upload.php' === $pagenow && isset($_GET['media_duplicate_filter']) && 'duplicates' === $_GET['media_duplicate_filter']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            // Security: Verify request comes from admin
            // Nonce verification handled by WordPress's own media library screen processing
            // Additional capability check is performed by WordPress before this hook runs
            // Fast path: use SQL aggregation over stored hashes
            $hashes = $this->get_duplicate_hashes_by_sql();

            // If no duplicates yet and many attachments lack hashes, trigger a quick batch
            // Manual scan only: disable auto-trigger
            /*
            if (empty($hashes) && $this->count_unhashed_attachments() > 0) {
                // Run one batch immediately to bootstrap hashes; then re-check
                do_action('media_tracker_batch_process');
                $hashes = $this->get_duplicate_hashes_by_sql();
            }
            */

            $duplicate_ids = array();
            $ids_with_hashes = array();

            foreach ($hashes as $hash => $ids) {
                if (count($ids) > 1) {
                    $duplicate_ids = array_merge($duplicate_ids, $ids);
                    foreach ($ids as $id) {
                        $ids_with_hashes[$id] = $hash;
                    }
                }
            }

            if (!empty($duplicate_ids)) {
                usort($duplicate_ids, function($a, $b) use ($ids_with_hashes) {
                    return strcmp($ids_with_hashes[$a], $ids_with_hashes[$b]);
                });

                $query->set('post__in', $duplicate_ids);
                $query->set('orderby', 'post__in');
                // Remove posts_per_page = -1 to enable default WordPress pagination
            } else {
                $query->set('post__in', array(0));
            }
        }
    }

    /**
     * Retrieves and caches perceptual image hashes for a batch of images.
     *
     * @param int $offset Offset for batch processing.
     * @param int $limit Number of images to process in each batch.
     * @return array Associative array of duplicate image hashes.
     */
    private function get_image_hashes($offset = 0, $limit = 0) {
        if ( empty( $limit ) ) {
            $limit = MEDIA_TRACKER_DUPLICATE_BATCH_SIZE;
        }
        global $wpdb;

        $attachments = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT ID, guid FROM {$wpdb->posts}
                WHERE post_type = %s AND post_mime_type LIKE %s
                LIMIT %d, %d",
                'attachment',
                'image/%',
                $offset,
                $limit
            )
        );

        $hashes = array();
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);

            if ( $file_path && file_exists($file_path) ) {
                $hash = get_post_meta($attachment->ID, '_media_tracker_hash', true);

                // Generate hash if not already cached
                if (empty($hash)) {
                    try {
                        $hash = $this->generate_image_hash($file_path);
                        update_post_meta($attachment->ID, '_media_tracker_hash', $hash);
                    } catch (Exception $e) {
                        continue;
                    }
                }

                if (!isset($hashes[$hash])) {
                    $hashes[$hash] = array();
                }
                $hashes[$hash][] = $attachment->ID;
            }
        }

        // Only return hashes that have more than one image (duplicates)
        $duplicate_hashes = array_filter($hashes, function($ids) {
            return count($ids) > 1;
        });

        return $duplicate_hashes;
    }

    /**
     * Generate perceptual hash of an image using native PHP.
     *
     * @param string $file_path Path to the image file.
     * @return string The perceptual hash.
     * @throws Exception If the image cannot be processed.
     */
    private function generate_image_hash($file_path, $mime = null) {
        global $wp_filesystem;

        // Determine mime if not provided
        if ($mime === null) {
            $filetype = wp_check_filetype_and_ext($file_path, basename($file_path));
            $mime = isset($filetype['type']) && $filetype['type'] ? $filetype['type'] : null;
            if ($mime === null && function_exists('mime_content_type')) {
                $mime = mime_content_type($file_path);
            }
        }

        // Handle SVG via canonical XML hashing
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($mime === 'image/svg+xml' || $ext === 'svg') {
            return $this->generate_svg_hash($file_path);
        }

        // Handle AVIF - use file hash since GD doesn't support AVIF
        if ($mime === 'image/avif' || $ext === 'avif') {
            return $this->generate_file_hash($file_path);
        }

        // Initialize the WordPress filesystem.
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $file_content = $wp_filesystem->get_contents($file_path);
        if (empty($file_content)) {
            throw new Exception('File content is empty or could not be read.');
        }

        $image = imagecreatefromstring($file_content);
        if ($image === false) {
            throw new Exception('Failed to create image from file content.');
        }

        $resized_image = null;
        $gray_image = null;

        try {
            $resized_image = imagescale($image, 8, 8); // Scale to 8x8
            if ($resized_image === false) {
                throw new Exception('Failed to resize the image.');
            }

            $gray_image = imagecreatetruecolor(8, 8);
            if ($gray_image === false) {
                throw new Exception('Failed to create grayscale image.');
            }

            // Convert to grayscale using proper luminance formula
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $rgb = imagecolorat($resized_image, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Use Rec. 601 luminance formula for accurate grayscale
                    $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
                    $gray_color = imagecolorallocate($gray_image, $gray, $gray, $gray);
                    imagesetpixel($gray_image, $x, $y, $gray_color);
                }
            }

            // Calculate the average grayscale value
            $sum = 0;
            $pixels = array();
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $gray = imagecolorat($gray_image, $x, $y) & 0xFF;
                    $pixels[] = $gray;
                    $sum += $gray;
                }
            }
            $average = $sum / 64;

            // Generate hash based on average value
            $hash = '';
            foreach ($pixels as $pixel) {
                $hash .= ($pixel > $average) ? '1' : '0';
            }

            return $hash;

        } finally {
            // Always clean up image resources, even if exception occurs
            if ($gray_image !== null) {
                imagedestroy($gray_image);
            }
            if ($resized_image !== null) {
                imagedestroy($resized_image);
            }
            imagedestroy($image);
        }
    }

    // SVG hashing using XML canonicalization to ignore whitespace/attribute ordering
    private function generate_svg_hash($file_path) {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $content = $wp_filesystem->get_contents($file_path);
        if (empty($content)) {
            throw new Exception('SVG content is empty or could not be read.');
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $loaded = $dom->loadXML($content, LIBXML_NONET);
        if (!$loaded) {
            // Fallback: normalize whitespace and hash raw
            $normalized = preg_replace('/\s+/', ' ', $content);
            return sha1($normalized);
        }

        // Remove comments
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//comment()') as $comment) {
            $comment->parentNode->removeChild($comment);
        }
        // Remove common non-rendering or metadata attributes that vary across exports
        foreach ($xpath->query('//@*') as $attr) {
            $name = $attr->nodeName;
            if (preg_match('/^(id|data-|inkscape:|sodipodi:|xml:space)$/', $name)) {
                $attr->ownerElement->removeAttributeNode($attr);
            }
        }

        // Canonicalize XML (stable attribute ordering, normalized whitespace)
        $canonical = $dom->C14N(true, true);
        if ($canonical === false) {
            $canonical = $dom->saveXML();
        }

        return sha1($canonical);
    }

    // File hashing for formats not supported by GD (like AVIF, HEIC, WebP, etc.)
    private function generate_file_hash($file_path) {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $content = $wp_filesystem->get_contents($file_path);
        if (empty($content)) {
            throw new Exception('File content is empty or could not be read.');
        }

        // Use SHA-256 hash of the entire file content for accurate duplicate detection
        return hash('sha256', $content);
    }

    /**
     * Batch processes image hashes and stores them in post meta.
     */
    public function process_image_hashes_batch() {
        // Manual scan only: check if scan is active
        if ( ! get_option( 'media_tracker_duplicate_scan_active' ) ) {
            return;
        }

        $limit = MEDIA_TRACKER_DUPLICATE_BATCH_SIZE;
        $offset = get_option('media_tracker_offset', 0);

        // Process image hashes in batches
        $hashes = $this->get_image_hashes($offset, $limit);

        if (empty($hashes)) {
            // If no more images, reset the offset
            delete_option('media_tracker_offset');
            // Scan complete: disable active flag
            delete_option( 'media_tracker_duplicate_scan_active' );

            // Save final duplicate count
            $count = self::count_duplicate_attachments();
            update_option( 'media_tracker_duplicate_count_last_scan', $count );

            // Invalidate dashboard stats cache
            delete_transient( 'media_tracker_dashboard_stats_v8' );
        } else {
            // Update the offset for the next batch
            update_option('media_tracker_offset', $offset + $limit);
        }
    }

    /**
     * AJAX handler to get duplicate images.
     */
    public function get_duplicate_images_via_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'media-tracker' ) ), 403 );
        }

        check_ajax_referer('media_tracker_nonce', 'nonce');

        // Fast path: use SQL aggregation over stored hashes
        $hashes = $this->get_duplicate_hashes_by_sql();
        // If no duplicates yet and many attachments lack hashes, trigger a quick batch
        // Manual scan only: disable auto-trigger
        /*
        if (empty($hashes) && $this->count_unhashed_attachments() > 0) {
            do_action('media_tracker_batch_process');
            $hashes = $this->get_duplicate_hashes_by_sql();
        }
        */
        $duplicate_ids = array();
        $ids_with_hashes = array();

        // Loop through image hashes to find duplicates
        foreach ($hashes as $hash => $ids) {
            if (count($ids) > 1) { // Ensure only duplicates are processed
                $duplicate_ids = array_merge($duplicate_ids, $ids);
                foreach ($ids as $id) {
                    $ids_with_hashes[$id] = $hash;
                }
            }
        }

        if (!empty($duplicate_ids)) {
            // Sort duplicate IDs based on their image hashes
            usort($duplicate_ids, function($a, $b) use ($ids_with_hashes) {
                return strcmp($ids_with_hashes[$a], $ids_with_hashes[$b]);
            });

            // Fetch the actual image posts from the sorted duplicate IDs
            $duplicates = array();
            foreach ($duplicate_ids as $id) {
                $duplicates[] = get_post($id);
            }

            ob_start();
            foreach ($duplicates as $image) {
                echo '<li tabindex="0" role="checkbox" aria-label="' . esc_attr($image->post_title) . '" aria-checked="false" data-id="' . esc_attr($image->ID) . '" class="attachment save-ready">';
                    echo '<div class="attachment-preview js--select-attachment type-image subtype-png landscape">';
                        echo '<a href="' . esc_url(get_edit_post_link($image->ID)) . '">';
                            echo '<div class="thumbnail">';
                                echo '<div class="centered">';
                                    echo '<img src="' . esc_url(wp_get_attachment_thumb_url($image->ID)) . '" alt="' . esc_attr($image->post_title) . '">';
                                echo '</div>';
                            echo '</div>';
                        echo '</a>';
                    echo '</div>';
                echo '</li>';
            }
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html, 'count' => count($duplicates)));
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Get the total count of duplicate images.
     *
     * @return int Total number of duplicate images.
     */
    public static function count_duplicate_attachments() {
        global $wpdb;

        $results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) as count
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s
                AND p.post_type = 'attachment'
                AND p.post_mime_type LIKE %s
                AND pm.meta_value != ''
                GROUP BY pm.meta_value
                HAVING count > 1",
                '_media_tracker_hash',
                'image/%'
            )
        );

        $count = 0;
        if ( $results ) {
            foreach ( $results as $row ) {
                $count += $row->count;
            }
        }

        return $count;
    }

    /**
     * Efficiently fetch duplicate hashes using SQL over postmeta.
     * Requires that attachment hashes are precomputed and stored in post meta.
     *
     * @return array<string, int[]> Map of hash => [attachment IDs]
     */
    private function get_duplicate_hashes_by_sql() {
        global $wpdb;

        // Optimized: avoid GROUP_CONCAT limits and return all pairs via derived table join
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "
                SELECT pm.post_id, pm.meta_value AS hash
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                INNER JOIN (
                    SELECT meta_value
                    FROM {$wpdb->postmeta}
                    WHERE meta_key = %s
                    GROUP BY meta_value
                    HAVING COUNT(*) > 1
                ) dup ON dup.meta_value = pm.meta_value
                WHERE pm.meta_key = %s
                  AND p.post_type = 'attachment'
                  AND p.post_mime_type LIKE %s
                ORDER BY pm.meta_value, pm.post_id
                ",
                '_media_tracker_hash',
                '_media_tracker_hash',
                'image/%'
            )
        );

        $hashes = array();
        foreach ($rows as $row) {
            if (!isset($row->hash) || !isset($row->post_id)) {
                continue;
            }
            $hash = (string) $row->hash;
            // Skip empty hashes
            if (empty($hash)) {
                continue;
            }
            $id = (int) $row->post_id;
            if (!isset($hashes[$hash])) {
                $hashes[$hash] = array();
            }
            $hashes[$hash][] = $id;
        }

        return $hashes;
    }

    /**
     * Count attachments that still have no stored hash.
     * Helps decide whether to bootstrap hashing immediately.
     */
    private function count_unhashed_attachments() {
        global $wpdb;

        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "
                SELECT COUNT(p.ID)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID AND pm.meta_key = %s
                WHERE p.post_type = 'attachment'
                  AND p.post_mime_type LIKE %s
                  AND pm.post_id IS NULL
                ",
                '_media_tracker_hash',
                'image/%'
            )
        );

        return $count;
    }
    // Add a new helper that aggregates hashes across the entire library
    private function get_all_duplicate_hashes($batch_size = 300) {
        // Strictly use existing database hashes.
        // Never generate new hashes here. Hash generation is now exclusively handled
        // by the manual scan process (reset_duplicate_hashes_via_ajax -> batch process).
        return $this->get_duplicate_hashes_by_sql();
    }

    /**
     * AJAX handler to reset all duplicate image hashes and trigger re-scan.
     * Useful after updating hashing logic (e.g., adding AVIF support).
     */
    public function reset_duplicate_hashes_via_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'media-tracker')), 403);
        }

        check_ajax_referer('media_tracker_nonce', 'nonce');

        global $wpdb;

        // Delete all image hashes
        $deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_media_tracker_hash'
            )
        );

        // Reset the offset
        delete_option('media_tracker_offset');

        // Activate manual scan flag
        update_option( 'media_tracker_duplicate_scan_active', true );

        // Mark that a scan has been initiated/performed
        update_option( 'media_tracker_duplicates_scanned', true );

        // Clear dashboard stats cache so overview updates
        delete_transient( 'media_tracker_dashboard_stats_v8' );

        // Save total to scan for progress bar
        $total_to_scan = $this->count_unhashed_attachments();
        update_option('media_tracker_total_to_scan', $total_to_scan);

        // Trigger immediate batch processing
        do_action('media_tracker_batch_process');

        // Count remaining images to process
        $remaining = $this->count_unhashed_attachments();

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: 1: number of hashes reset, 2: number of images being rescanned */
                __('Reset %1$d hashes. Re-scanning %2$d images...', 'media-tracker'),
                $deleted,
                $total_to_scan
            ),
            'deleted' => $deleted,
            'remaining' => $remaining,
            'total' => $total_to_scan
        ));
    }

    /**
     * AJAX handler to process a batch of images for duplicate scanning.
     */
    public function process_batch_via_ajax() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'media-tracker')), 403);
        }

        check_ajax_referer('media_tracker_nonce', 'nonce');

        // Run the batch
        $this->process_image_hashes_batch();

        // Check status
        $active = get_option('media_tracker_duplicate_scan_active');
        $offset = get_option('media_tracker_offset', 0);
        $total = get_option('media_tracker_total_to_scan', 0);

        if (!$active) {
            // Completed
            wp_send_json_success(array(
                'completed' => true,
                'percentage' => 100,
                'processed' => $total,
                'total' => $total
            ));
        } else {
            $percentage = ($total > 0) ? min(99, round(($offset / $total) * 100)) : 0;
            wp_send_json_success(array(
                'completed' => false,
                'percentage' => $percentage,
                'processed' => $offset,
                'total' => $total
            ));
        }
    }

    public function delete_duplicate_images_via_ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'media-tracker' ) ), 403 );
        }

        check_ajax_referer( 'media_tracker_nonce', 'nonce' );

        if ( empty( $_POST['attachment_ids'] ) || ! is_array( $_POST['attachment_ids'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No images selected.', 'media-tracker' ) ) );
        }

        $ids = array_map( 'intval', $_POST['attachment_ids'] );
        $deleted = 0;

        foreach ( $ids as $id ) {
            if ( $id <= 0 ) {
                continue;
            }
            if ( ! current_user_can( 'delete_post', $id ) ) {
                continue;
            }
            $result = wp_delete_attachment( $id, true );
            if ( $result ) {
                $deleted++;
            }
        }

        if ( $deleted > 0 ) {
            // Clear dashboard stats cache so overview updates
            delete_transient( 'media_tracker_dashboard_stats_v8' );

            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %d: number of deleted images */
                    __( 'Deleted %d duplicate images.', 'media-tracker' ),
                    $deleted
                ),
                'deleted' => $deleted,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'No images were deleted.', 'media-tracker' ) ) );
        }
    }

    /**
     * Check if any media tracker hash exists in the database.
     *
     * @return bool True if hash exists, false otherwise.
     */
    public static function has_generated_hashes() {
        global $wpdb;
        $hash_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
                '_media_tracker_hash'
            )
        );
        return ! empty( $hash_exists );
    }

    /**
     * Render pagination for duplicate images tab.
     *
     * @param int $current_page Current page number.
     * @param int $total_pages  Total number of pages.
     * @param int $total_items  Total number of items.
     */
    public static function render_pagination( $current_page, $total_pages, $total_items ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        $base_url = add_query_arg( 'tab', 'duplicates', remove_query_arg( array( 'mt_dup_page' ) ) );

        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo '<span class="displaying-num">' . esc_html( $total_items ) . ' ' . esc_html__( 'items', 'media-tracker' ) . '</span>';

        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( 'mt_dup_page', $current_page - 1, $base_url );
            echo '<a class="prev-page button" href="' . esc_url( $prev_url ) . '">&laquo;</a>';
        } else {
            echo '<span class="tablenav-pages-navspan">&laquo;</span>';
        }

        echo '<span class="paging-input">' . esc_html( $current_page ) . ' / ' . esc_html( $total_pages ) . '</span>';

        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( 'mt_dup_page', $current_page + 1, $base_url );
            echo '<a class="next-page button" href="' . esc_url( $next_url ) . '">&raquo;</a>';
        } else {
            echo '<span class="tablenav-pages-navspan">&raquo;</span>';
        }

        echo '</div></div>';
    }
}
