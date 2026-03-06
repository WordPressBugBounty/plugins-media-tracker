<?php
/**
 * Plugin Name: Media Tracker
 * Description: Media Tracker is a WordPress plugin to find and remove unused media files, manage duplicates, and optimize your media library for better performance.
 * Author: TheBitCraft
 * Author URI: https://thebitcraft.com/
 * Version: 1.3.4
 * Requires PHP: 7.4
 * Requires at least: 5.9
 * Tested up to: 6.9
 * Text Domain: media-tracker
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/**
 * The main plugin class
 */
final class Media_Tracker {

    /**
     * Plugin version
     *
     * @var string
     */
    const version = '1.3.4';

    /**
     * Class constructor
     */
    private function __construct() {
        $this->define_constants();
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( '\Media_Tracker\Installer', 'clear_cron_jobs' ) );
        add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
        add_action( 'wp_ajax_mt_save_feedback', array( '\Media_Tracker\Installer', 'save_feedback' ) );
        add_action( 'current_screen', function( $screen ) {
            if ( $screen && ( $screen->id === 'plugins' || $screen->id === 'plugins-network' ) ) {
                \Media_Tracker\Installer::deactivate();
            }
        } );
    }

    /**
     * Initialize a singleton instance
     * @return \Media_Tracker
     */
    public static function init() {
        static $instance = false;

        if ( ! $instance ) {
            $instance = new self();
        }

        return $instance;
    }

    /**
     * Define the required plugin constants
     *
     * @return void
     */
    public function define_constants() {
        define( 'MEDIA_TRACKER_VERSION', self::version );
        define( 'MEDIA_TRACKER_FILE', __FILE__ );
        define( 'MEDIA_TRACKER_PATH', __DIR__ );
        define( 'MEDIA_TRACKER_URL', plugins_url( '', MEDIA_TRACKER_FILE ) );
        define( 'MEDIA_TRACKER_ASSETS', MEDIA_TRACKER_URL . '/assets' );
        define( 'MEDIA_TRACKER_BASENAME', plugin_basename(__FILE__) );
    }

    /**
     * Do stuff upon plugin activation
     *
     * @return void
     */
    public function activate() {
        $installer = new Media_Tracker\Installer();
        $installer->run();
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function init_plugin() {
        new Media_Tracker\Media_Tracker_i18n();
        new Media_Tracker\Assets();
        new Media_Tracker\Cron_Schedules();
        // Load Admin class in admin dashboard OR during cron execution
        if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            new Media_Tracker\Admin();
        }
    }
}

/**
 * Initialize the main plugin
 */
function media_tracker_list() {
    return Media_Tracker::init();
}

// Kick-off the plugin
media_tracker_list();
