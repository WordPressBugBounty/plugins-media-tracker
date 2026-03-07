<?php
/**
 * Tab: Unused Media
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-unused-media.php
 * - yourtheme/media-tracker/tabs/tab-unused-media.php
 * - media-tracker-pro/templates/tabs/tab-unused-media.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'remove_all_actions' ) ) {
    remove_all_actions( 'admin_notices' );
    remove_all_actions( 'all_admin_notices' );
    remove_all_actions( 'user_admin_notices' );
}

// Get current per page setting
$media_tracker_current_per_page = get_user_meta( get_current_user_id(), 'unused_media_cleaner_per_page', true );
if ( empty( $media_tracker_current_per_page ) || $media_tracker_current_per_page < 1 ) {
    $media_tracker_current_per_page = 10;
}

$media_tracker_search    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$media_tracker_author_id = isset( $_GET['author'] ) ? intval( $_GET['author'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( null !== $media_tracker_author_id && $media_tracker_author_id > 0 ) {
    $media_tracker_author_user = get_userdata( $media_tracker_author_id );
    if ( false === $media_tracker_author_user ) {
        $media_tracker_author_id = null;
    }
} else {
    $media_tracker_author_id = null;
}

if ( class_exists( '\Media_Tracker\Admin\Unused_Media_List' ) ) {
    $media_tracker_unused_media_list = new \Media_Tracker\Admin\Unused_Media_List( $media_tracker_search, $media_tracker_author_id );

    // Hack: Force tab parameter into Request URI for WP_List_Table link generation
    // This ensures pagination and sorting links include 'tab=unused-media'
    $media_tracker_original_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    if ( ! isset( $_GET['tab'] ) || 'unused-media' !== $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $_SERVER['REQUEST_URI'] = add_query_arg( 'tab', 'unused-media', $media_tracker_original_uri );
    }

    $media_tracker_unused_media_list->prepare_items();
    ?>

    <div id="screen-meta-links" style="display:none;">
        <div id="screen-options-link-wrap" class="hide-if-no-js screen-meta-toggle">
            <button type="button" id="show-settings-link" class="button show-settings" aria-expanded="false">
                <?php esc_html_e( 'Screen Options', 'media-tracker' ); ?>
            </button>
        </div>
    </div>

    <div id="screen-options" class="metabox-prefs hidden">
        <div class="screen-options-content">
            <form id="unused-media-screen-options-form" method="post">
                <?php wp_nonce_field( 'unused_media_screen_options', 'unused_media_screen_options_nonce' ); ?>
                <input type="hidden" name="action" value="unused_media_save_screen_options">

                <div class="screen-options-per-page">
                    <label for="unused_media_per_page">
                        <?php esc_html_e( 'Number of items per page:', 'media-tracker' ); ?>
                    </label>
                    <input type="number" name="unused_media_per_page" id="unused_media_per_page"
                           value="<?php echo esc_attr( $media_tracker_current_per_page ); ?>"
                           min="1" max="999" step="1" class="screen-per-page">
                    <span class="description"><?php esc_html_e( '(1-999)', 'media-tracker' ); ?></span>
                </div>

                <p class="submit">
                    <button type="submit" name="unused_media_screen_options_submit"
                            class="button button-primary">
                        <?php esc_html_e( 'Apply', 'media-tracker' ); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <?php
    include __DIR__ . '/../unused-media-list.php';

    // Restore original URI
    $_SERVER['REQUEST_URI'] = $media_tracker_original_uri;
} else {
    echo '<p>' . esc_html__( 'Unused media list class not found.', 'media-tracker' ) . '</p>';
}
