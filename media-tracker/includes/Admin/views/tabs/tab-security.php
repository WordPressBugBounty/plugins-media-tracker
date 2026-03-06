<?php
/**
 * Tab: Security & Logs
 *
 * This template can be overridden by copying it to:
 * - yourtheme/media-tracker-pro/tabs/tab-security.php
 * - yourtheme/media-tracker/tabs/tab-security.php
 * - media-tracker-pro/templates/tabs/tab-security.php
 *
 * @package Media_Tracker
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

// Premium Feature Lock
media_tracker_pro_feature_lock_start(
    __( 'Security & Activity Logs', 'media-tracker' ),
    __( 'Upgrade to Media Tracker Pro for role-based permissions, activity logging, and complete audit trails for all sensitive actions.', 'media-tracker' ),
    array(
        __( 'Role-based access control', 'media-tracker' ),
        __( 'Full activity audit logs', 'media-tracker' ),
        __( 'Bulk delete confirmation', 'media-tracker' ),
        __( 'File whitelist protection', 'media-tracker' ),
        __( 'Export logs to CSV', 'media-tracker' ),
        __( 'User action tracking', 'media-tracker' ),
    )
);
?>

<img src="<?php echo esc_url( MEDIA_TRACKER_ASSETS ); ?>/dist/images/mt-pro-2.webp" alt="mt-pro-2">

<?php media_tracker_pro_feature_lock_end(); ?>
