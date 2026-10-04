<?php defined('ABSPATH') || exit; ?>

<div class="media-tracker-layout">
    <header class="mt-settings-header">
        <div class="mt-header-left">
            <div class="mt-admin-header">
                <img src="<?php echo esc_url( MEDIA_TRACKER_URL . '/assets/src/images/main-logo.svg' ); ?>" alt="">
            </div>
        </div>

        <div class="mt-header-right">
            <span>
                <?php
                    // translators: %s: plugin version number
                    $media_tracker_version_text = esc_html__( 'Current Version: %s', 'media-tracker' );
                    echo wp_kses_post( sprintf( $media_tracker_version_text, '<strong>' . esc_html( MEDIA_TRACKER_VERSION ) . '</strong>' ) );
                ?>
            </span>
        </div>
    </header>

    <main>
        <?php
        $media_tracker_current_tab = media_tracker_get_current_tab();

        // Only load the current tab
        $media_tracker_active_class = ' active';

        echo '<div id="tab-' . esc_attr($media_tracker_current_tab) . '" class="tab-content' . esc_attr($media_tracker_active_class) . '">';

        // Use template file based on tab name
        if ('duplicates-static' === $media_tracker_current_tab) {
            media_tracker_get_template('tabs/tab-duplicates.php');
        } else {
            media_tracker_get_template('tabs/tab-' . $media_tracker_current_tab . '.php');
        }

        echo '</div>';
        ?>
    </main>
</div>
