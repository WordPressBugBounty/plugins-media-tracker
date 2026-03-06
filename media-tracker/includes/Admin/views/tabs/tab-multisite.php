<?php
/**
 * Tab: Multi-site
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-multisite.php
 * - yourtheme/media-tracker/tabs/tab-multisite.php
 * - media-tracker-pro/templates/tabs/tab-multisite.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

// Premium Feature Lock
media_tracker_pro_feature_lock_start(
    __( 'Multi-site Network Management', 'media-tracker' ),
    __( 'Upgrade to Media Tracker Pro to manage and track media from multiple WordPress sites in one central dashboard.', 'media-tracker' ),
    array(
        __( 'Connect unlimited network sites', 'media-tracker' ),
        __( 'Network-wide media tracking', 'media-tracker' ),
        __( 'Cross-site duplicate detection', 'media-tracker' ),
        __( 'Centralized storage overview', 'media-tracker' ),
        __( 'Per-site cleanup rules', 'media-tracker' ),
        __( 'Network admin controls', 'media-tracker' ),
    )
);
?>

<img src="<?php echo esc_url( MEDIA_TRACKER_ASSETS ); ?>/dist/images/mt-pro-3.webp" alt="mt-pro-3">

<?php media_tracker_pro_feature_lock_end(); ?>
