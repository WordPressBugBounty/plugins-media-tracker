<?php defined( 'ABSPATH' ) || exit; ?>

<div class="media-tracker-layout">
    <aside>
        <div class="logo">
            <img src="<?php echo esc_url( MEDIA_TRACKER_ASSETS ); ?>/dist/images/logo.svg" alt="MediaTracker">
            <?php esc_html_e('MediaTracker', 'media-tracker'); ?>
        </div>
        <nav>
            <?php media_tracker_render_menu(); ?>
        </nav>
        <div class="version">
            <?php
            /* translators: %s: Plugin version number. */
            echo esc_html( sprintf( __( 'Version %s', 'media-tracker' ), MEDIA_TRACKER_VERSION ) );
            ?>
        </div>
    </aside>

    <main>
        <?php
        $media_tracker_current_tab = media_tracker_get_current_tab();

        // Only load the current tab
        $media_tracker_active_class = ' active';

        echo '<div id="tab-' . esc_attr( $media_tracker_current_tab ) . '" class="tab-content' . esc_attr( $media_tracker_active_class ) . '">';

        // Use template file based on tab name
        if ( 'duplicates-static' === $media_tracker_current_tab ) {
            media_tracker_get_template( 'tabs/tab-duplicates.php' );
        } else {
            media_tracker_get_template( 'tabs/tab-' . $media_tracker_current_tab . '.php' );
        }

        echo '</div>';
        ?>


        <div id="connection-modal" class="modal-backdrop">
            <div class="modal">
                <div class="modal-header">
                    <div class="modal-title">
                        <i data-lucide="plug-2" style="width: 18px; height: 18px;"></i>
                        <?php esc_html_e( 'Add New Connection', 'media-tracker' ); ?>
                    </div>
                    <button class="modal-close" type="button" id="connection-modal-close"><?php esc_html_e( '&times;', 'media-tracker' ); ?></button>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="connection-name"><?php esc_html_e( 'Connection Name', 'media-tracker' ); ?></label>
                        <input id="connection-name" type="text" placeholder="<?php esc_attr_e( 'My S3 Backup', 'media-tracker' ); ?>" />
                    </div>
                    <div>
                        <label for="connection-provider"><?php esc_html_e( 'Provider', 'media-tracker' ); ?></label>
                        <select id="connection-provider">
                            <option value="drive"><?php esc_html_e( 'Google Drive', 'media-tracker' ); ?></option>
                            <option value="s3"><?php esc_html_e( 'Amazon S3', 'media-tracker' ); ?></option>
                            <option value="dropbox"><?php esc_html_e( 'Dropbox', 'media-tracker' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label for="connection-path"><?php esc_html_e( 'Root Folder / Bucket', 'media-tracker' ); ?></label>
                        <input id="connection-path" type="text" placeholder="<?php esc_attr_e( '/MediaTrackerPro/backup or media-tracker-pro', 'media-tracker' ); ?>" />
                    </div>
                    <div>
                        <label for="connection-region"><?php esc_html_e( 'Region / Location', 'media-tracker' ); ?></label>
                        <input id="connection-region" type="text" placeholder="<?php esc_attr_e( 'us-east-1, europe-west1 etc.', 'media-tracker' ); ?>" />
                    </div>
                    <p class="modal-hint"><?php esc_html_e( 'Production credentials (Access Key, Secret Key) are handled via the WordPress settings page. This modal only previews UI and flow.', 'media-tracker' ); ?></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" type="button" id="connection-modal-cancel"><?php esc_html_e( 'Cancel', 'media-tracker' ); ?></button>
                    <button class="btn btn-outline" type="button" id="connection-modal-test"><?php esc_html_e( 'Test Connection', 'media-tracker' ); ?></button>
                    <button class="btn btn-primary" type="button" id="connection-modal-save"><?php esc_html_e( 'Save Connection', 'media-tracker' ); ?></button>
                </div>
            </div>
        </div>
    </main>
</div>

