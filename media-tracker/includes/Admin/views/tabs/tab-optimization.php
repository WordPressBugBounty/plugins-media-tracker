<?php
/**
 * Tab: Optimization
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-optimization.php
 * - yourtheme/media-tracker/tabs/tab-optimization.php
 * - media-tracker-pro/templates/tabs/tab-optimization.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

// Premium Feature Lock
media_tracker_pro_feature_lock_start(
    __( 'Advanced Image Optimization', 'media-tracker' ),
    __( 'Upgrade to Media Tracker Pro to access powerful optimization tools including compression, WebP conversion, and lazy loading.', 'media-tracker' ),
    array(
        __( 'Lossless image compression', 'media-tracker' ),
        __( 'Automatic WebP conversion', 'media-tracker' ),
        __( 'Smart lazy loading', 'media-tracker' ),
        __( 'Bulk optimization queue', 'media-tracker' ),
        __( 'Fallback for old browsers', 'media-tracker' ),
        __( 'Quality control settings', 'media-tracker' ),
    )
);
?>

<img src="<?php echo esc_url( MEDIA_TRACKER_ASSETS ); ?>/dist/images/mt-pro-1.webp" alt="mt-pro-1">

<?php media_tracker_pro_feature_lock_end(); ?>
