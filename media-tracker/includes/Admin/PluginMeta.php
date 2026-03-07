<?php

namespace Media_Tracker\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Meta Links Handler
 * Adds action links to the plugin list page
 */
class PluginMeta
{
    /**
     * Plugin basename
     */
    private $plugin_basename;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->plugin_basename = plugin_basename(MEDIA_TRACKER_FILE);

        // Add action links (Settings, etc.)
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'add_action_links']);

        // Remove admin notices on plugin pages
        add_action('in_admin_header', [$this, 'remove_admin_notices_on_plugin_pages'], PHP_INT_MAX);
    }

    /**
     * Remove admin notices on Media Tracker plugin pages
     *
     * @return void
     */
    public function remove_admin_notices_on_plugin_pages()
    {
        $current_screen = get_current_screen();
        if ( $current_screen && strpos( $current_screen->id, 'media-tracker' ) !== false ) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            remove_all_actions('user_admin_notices');
            remove_all_actions('network_admin_notices');
        }
    }

    /**
     * Add action links to plugin list page
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('upload.php?page=media-tracker'),
            __('Settings', 'media-tracker')
        );

        // Add settings link at the beginning
        array_unshift($links, $settings_link);

        return $links;
    }
}
