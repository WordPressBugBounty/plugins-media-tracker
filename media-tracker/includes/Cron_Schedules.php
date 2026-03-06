<?php

namespace Media_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * The cron schedules class
 */
class Cron_Schedules {

    /**
     * Initialize the class
     */
    function __construct() {
        // Register custom cron schedules early so they are available during activation
        add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
    }

    /**
     * Register custom cron schedules
     *
     * @param array $schedules Existing cron schedules
     * @return array Modified cron schedules
     */
    public function register_cron_schedules($schedules) {
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = array(
                'interval' => 5 * 60,
                'display'  => __('Every 5 Minutes', 'media-tracker'),
            );
        }
        return $schedules;
    }
}
