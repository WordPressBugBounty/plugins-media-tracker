<?php
/**
 * Tab: External Storage
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-external-storage.php
 * - yourtheme/media-tracker/tabs/tab-external-storage.php
 * - media-tracker-pro/templates/tabs/tab-external-storage.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

// Premium Feature Lock
media_tracker_pro_feature_lock_start(
    __( 'Cloud Storage & Automatic Offloading', 'media-tracker' ),
    __( 'Upgrade to Media Tracker Pro to automatically move and archive unused files to S3, Google Drive, or Dropbox.', 'media-tracker' ),
    array(
        __( 'Connect Google Drive, S3, Dropbox', 'media-tracker' ),
        __( 'Auto-offload unused media', 'media-tracker' ),
        __( 'Archive old media automatically', 'media-tracker' ),
        __( 'Backup before permanent delete', 'media-tracker' ),
        __( 'Storage limit alerts', 'media-tracker' ),
        __( 'Restore from cloud anytime', 'media-tracker' ),
    )
);
?>

<img src="<?php echo esc_url( MEDIA_TRACKER_ASSETS ); ?>/dist/images/mt-pro-5.webp" alt="mt-pro-5">

<?php media_tracker_pro_feature_lock_end(); ?>
