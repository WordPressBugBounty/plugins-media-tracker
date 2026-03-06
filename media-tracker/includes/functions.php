<?php
/**
 * Media Tracker Helper Functions
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get menu items array with filter support
 *
 * Allows Media Tracker Pro and other plugins to add/modify menu items
 * via the 'media_tracker_menu_items' filter.
 *
 * @since 1.3.0
 * @return array
 */
function media_tracker_get_menu_items() {
    // Get current active tab
    $current_tab = media_tracker_get_current_tab();

    $menu_items = array(
        'overview' => array(
            'label'   => __( 'Dashboard', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-admin-home',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-overview' ),
            'active'  => false,
        ),
        'unused-media' => array(
            'label'   => __( 'Unused Media', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-format-image',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-unused-media' ),
            'active'  => false,
        ),
        'duplicates' => array(
            'label'   => __( 'Duplicate Media', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-images-alt',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-duplicates' ),
            'active'  => false,
        ),
        'external-storage' => array(
            'label'   => __( 'External Storage', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-cloud-upload',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-external-storage' ),
            'active'  => false,
        ),
        'optimization' => array(
            'label'   => __( 'Optimization', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-performance',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-optimization' ),
            'active'  => false,
        ),
        'security' => array(
            'label'   => __( 'Security & Logs', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-lock',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-security' ),
            'active'  => false,
        ),
        'multisite' => array(
            'label'   => __( 'Multi-site', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-admin-multisite',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-multisite' ),
            'active'  => false,
            'class'   => 'multisite'
        ),
        'documents' => array(
            'label'   => __( 'Documents', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-media-document',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-documents' ),
            'active'  => false,
        ),
        'go-pro' => array(
            'label'   => __( 'Go Pro', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-star-filled',
            'badge'   => '<i class="dashicons dashicons-arrow-right-alt"></i>',
            'is_link' => true,
            'url'     => 'http://mediatracker.thebitcraft.com',
            'target'  => '_blank',
            'active'  => false,
        ),
    );

    // Add license menu item if Pro is active
    if ( media_tracker_is_pro_active() ) {
        $menu_items['license'] = array(
            'label'   => __( 'License', 'media-tracker' ),
            'icon'    => 'dashicons dashicons-admin-network',
            'badge'   => '',
            'is_link' => true,
            'url'     => admin_url( 'upload.php?page=media-tracker-license' ),
            'active'  => false,
        );
    }

    // Set active state for current tab
    if ( isset( $menu_items[ $current_tab ] ) ) {
        $menu_items[ $current_tab ]['active'] = true;
    }

    /**
     * Filter menu items
     *
     * @since 1.3.0
     * @param array $menu_items Array of menu items
     */
    return apply_filters( 'media_tracker_menu_items', $menu_items );
}

/**
 * Get current active tab from URL parameter or page parameter
 *
 * @since 1.3.0
 * @return string Current tab slug, defaults to 'overview'
 */
function media_tracker_get_current_tab() {
    $tab = '';

    // First check URL parameter 'tab' (for backward compatibility)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required for determining the current tab.
    if ( isset( $_GET['tab'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required for determining the current tab.
        $tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
    }

    // If no tab parameter, check page parameter (for new page-based navigation)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required for determining the current tab.
    if ( empty( $tab ) && isset( $_GET['page'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required for determining the current tab.
        $page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

        // Extract tab from page parameter (e.g., 'media-tracker-duplicates' -> 'duplicates')
        if ( strpos( $page, 'media-tracker-' ) === 0 ) {
            $tab = str_replace( 'media-tracker-', '', $page );
        } elseif ( $page === 'media-tracker' ) {
            // Main page defaults to overview
            $tab = 'overview';
        }
    }

    // If still empty, check if tab name exists in URL query string (backward compatibility)
    if ( empty( $tab ) ) {
        $known_tabs = array( 'overview', 'unused-media', 'duplicates', 'external-storage', 'optimization', 'security', 'multisite', 'license' );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required for reading the query string.
        $query_string = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';

        foreach ( $known_tabs as $known_tab ) {
            if ( strpos( $query_string, $known_tab ) !== false ) {
                $tab = $known_tab;
                break;
            }
        }
    }

    // Default to overview if no valid tab found
    if ( empty( $tab ) ) {
        $tab = 'overview';
    }

    return $tab;
}

/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 * 1. yourtheme/media-tracker-pro/$template_name
 * 2. yourtheme/media-tracker/$template_name
 * 3. media-tracker-pro/templates/$template_name
 * 4. media-tracker/includes/Admin/views/$template_name
 *
 * @since 1.3.0
 * @param string $template_name Template name.
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 * @return string
 */
function media_tracker_locate_template( $template_name, $template_path = '', $default_path = '' ) {
    // Set default template path if not provided
    if ( ! $template_path ) {
        $template_path = 'media-tracker/';
    }

    // Set default path to plugin views directory
    if ( ! $default_path ) {
        $default_path = MEDIA_TRACKER_PATH . '/includes/Admin/views/';
    }

    // Look within passed path within the theme - this is priority.
    $template = locate_template(
        array(
            'media-tracker-pro/' . $template_name,
            trailingslashit( $template_path ) . $template_name,
        )
    );

    // Check Media Tracker Pro plugin templates
    if ( ! $template && defined( 'MEDIA_TRACKER_PRO_PATH' ) ) {
        $pro_template = MEDIA_TRACKER_PRO_PATH . '/templates/' . $template_name;
        if ( file_exists( $pro_template ) ) {
            $template = $pro_template;
        }
    }

    // Get default template from base plugin
    if ( ! $template ) {
        $template = $default_path . $template_name;
    }

    /**
     * Filter located template path
     *
     * @since 1.3.0
     * @param string $template      Full path to template.
     * @param string $template_name Template name.
     * @param string $template_path Template path.
     */
    return apply_filters( 'media_tracker_locate_template', $template, $template_name, $template_path );
}

/**
 * Get a template.
 *
 * @since 1.3.0
 * @param string $template_name Template name.
 * @param array  $args          Arguments to pass to template. (default: array()).
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 */
function media_tracker_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
    $template = media_tracker_locate_template( $template_name, $template_path, $default_path );

    // Allow 3rd party plugin filter template file from their plugin.
    $template = apply_filters( 'media_tracker_get_template', $template, $template_name, $args, $template_path, $default_path );

    if ( ! file_exists( $template ) ) {
        // translators: %s template file path
        _doing_it_wrong( __FUNCTION__, sprintf( esc_html__( '%s does not exist.', 'media-tracker' ), '<code>' . esc_html( $template ) . '</code>' ), esc_html( MEDIA_TRACKER_VERSION ) );
        return;
    }

    /**
     * Action before template is included
     *
     * @since 1.3.0
     * @param string $template_name Template name.
     * @param string $template_path Template path.
     * @param string $template      Full template path.
     * @param array  $args          Template arguments.
     */
    do_action( 'media_tracker_before_template', $template_name, $template_path, $template, $args );

    // Extract args to make them available in template
    if ( ! empty( $args ) && is_array( $args ) ) {
        extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    }

    include $template;

    /**
     * Action after template is included
     *
     * @since 1.3.0
     * @param string $template_name Template name.
     * @param string $template_path Template path.
     * @param string $template      Full template path.
     * @param array  $args          Template arguments.
     */
    do_action( 'media_tracker_after_template', $template_name, $template_path, $template, $args );
}

/**
 * Get template part.
 *
 * @since 1.3.0
 * @param string $slug Template slug.
 * @param string $name Template name (optional).
 * @param array  $args Arguments to pass to template (optional).
 */
function media_tracker_get_template_part( $slug, $name = '', $args = array() ) {
    $template = '';

    // Look in yourtheme/media-tracker-pro/slug-name.php first
    if ( $name ) {
        $template = media_tracker_locate_template( "{$slug}-{$name}.php" );
    }

    // If not found, look in yourtheme/media-tracker-pro/slug.php
    if ( ! $template ) {
        $template = media_tracker_locate_template( "{$slug}.php" );
    }

    // Allow 3rd party plugins to filter template file
    $template = apply_filters( 'media_tracker_get_template_part', $template, $slug, $name, $args );

    if ( $template ) {
        // Extract args to make them available in template
        if ( ! empty( $args ) && is_array( $args ) ) {
            extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        }

        include $template;
    }
}

/**
 * Render a single menu item
 *
 * @since 1.3.0
 * @param string $tab_id   Tab ID.
 * @param array  $item     Menu item data.
 */
function media_tracker_render_menu_item( $tab_id, $item ) {
    $badge_html   = ! empty( $item['badge'] ) ? ' <small>' . $item['badge'] . '</small>' : '';

    if ( ! empty( $item['is_link'] ) && ! empty( $item['url'] ) ) {
        // For links (internal pages or external)
        $classes = array();

        // Add active class if needed
        if ( ! empty( $item['active'] ) ) {
            $classes[] = 'active';
        }

        // Add custom class if exists
        if ( ! empty( $item['class'] ) ) {
            $classes[] = $item['class'];
        }

        // Build class attribute
        $custom_class = ! empty( $classes ) ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';
        $target_attr  = ! empty( $item['target'] ) ? ' target="' . esc_attr( $item['target'] ) . '"' : '';

        printf(
            '<li%s><a href="%s"%s%s><i class="%s"></i> %s%s</a></li>',
            $custom_class, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Class attribute is constructed with esc_attr.
            esc_url( $item['url'] ),
            $custom_class, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Class attribute is constructed with esc_attr.
            $target_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Target attribute is constructed with esc_attr.
            esc_attr( $item['icon'] ),
            esc_html( $item['label'] ),
            wp_kses_post( $badge_html )
        );
    } else {
        // For regular tabs (non-link items, if any)
        $classes = array();

        // Add active class if needed
        if ( ! empty( $item['active'] ) ) {
            $classes[] = 'active';
        }

        // Add custom class if exists
        if ( ! empty( $item['class'] ) ) {
            $classes[] = $item['class'];
        }

        // Build class attribute
        $class_attr = ! empty( $classes ) ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';

        printf(
            '<li%s data-tab="%s"><i class="%s"></i>%s%s</li>',
            $class_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Class attribute is constructed with esc_attr.
            esc_attr( $tab_id ),
            esc_attr( $item['icon'] ),
            esc_html( $item['label'] ),
            wp_kses_post( $badge_html )
        );
    }
}


/**
 * Render menu items loop
 *
 * @since 1.3.0
 */
function media_tracker_render_menu() {
    $menu_items = media_tracker_get_menu_items();

    echo '<ul>';
    foreach ( $menu_items as $tab_id => $item ) {
        media_tracker_render_menu_item( $tab_id, $item );
    }
    echo '</ul>';
}

/**
 * Check if Media Tracker Pro is active
 *
 * @since 1.3.0
 * @return bool
 */
function media_tracker_is_pro_active() {
    return defined( 'MEDIA_TRACKER_PRO_VERSION' ) && MEDIA_TRACKER_PRO_VERSION;
}

/**
 * Render Pro feature lock overlay start wrapper
 *
 * Wraps premium tab content with blur effect and displays upgrade modal.
 * Similar to WPForms' entries page lock.
 *
 * @since 1.3.0
 * @param string $feature_title   Feature title for the modal heading.
 * @param string $feature_desc    Feature description.
 * @param array  $feature_list    Array of feature bullet points.
 * @param string $upgrade_url     Upgrade URL (optional).
 */
function media_tracker_pro_feature_lock_start( $feature_title, $feature_desc, $feature_list = array(), $upgrade_url = '' ) {
    // Skip if Pro is active
    if ( media_tracker_is_pro_active() ) {
        return;
    }

    if ( empty( $upgrade_url ) ) {
        $upgrade_url = 'http://mediatracker.thebitcraft.com/';
    }

    /**
     * Filter the upgrade URL
     *
     * @since 1.3.0
     * @param string $upgrade_url Upgrade URL.
     */
    $upgrade_url = apply_filters( 'media_tracker_upgrade_url', $upgrade_url );
    ?>
    <div class="mt-pro-lock-wrapper">
        <div class="mt-pro-lock-overlay">
            <div class="mt-pro-lock-modal">
                <div class="mt-pro-lock-header">
                    <span class="mt-pro-lock-icon">
                        <i class="dashicons dashicons-lock"></i>
                    </span>
                    <span class="mt-pro-lock-notice">
                        <?php esc_html_e( 'This feature is available in Media Tracker Pro.', 'media-tracker' ); ?>
                    </span>
                </div>
                <div class="mt-pro-lock-body">
                    <h2><?php echo esc_html( $feature_title ); ?></h2>
                    <p><?php echo esc_html( $feature_desc ); ?></p>

                    <?php if ( ! empty( $feature_list ) && is_array( $feature_list ) ) : ?>
                        <ul class="mt-pro-lock-features">
                            <?php foreach ( $feature_list as $feature ) : ?>
                                <li>
                                    <i class="dashicons dashicons-yes"></i>
                                    <?php echo esc_html( $feature ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <a href="<?php echo esc_url( $upgrade_url ); ?>" class="mt-pro-lock-button" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e( 'Upgrade to Media Tracker Pro', 'media-tracker' ); ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="mt-pro-lock-content">
    <?php
}

/**
 * Render Pro feature lock overlay end wrapper
 *
 * @since 1.3.0
 */
function media_tracker_pro_feature_lock_end() {
    // Skip if Pro is active
    if ( media_tracker_is_pro_active() ) {
        return;
    }
    ?>
        </div>
    </div>
    <?php
}
