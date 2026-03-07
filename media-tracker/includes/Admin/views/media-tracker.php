<?php defined('ABSPATH') || exit; ?>

<div class="media-tracker-layout">
    <aside>
        <div class="logo">
            <img src="<?php echo esc_url(MEDIA_TRACKER_ASSETS); ?>/dist/images/logo.svg" alt="MediaTracker">
            <?php esc_html_e('Media Tracker', 'media-tracker'); ?>
        </div>
        <nav>
            <?php media_tracker_render_menu(); ?>
        </nav>
        <div class="version">
            <?php
            /* translators: %s: Plugin version number. */
            echo esc_html(sprintf(__('Version %s', 'media-tracker'), MEDIA_TRACKER_VERSION));
            ?>
        </div>
    </aside>

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
