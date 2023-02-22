<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/includes
 * @author     Heikel Villar <heikelvillar@gmail.com>
 */
class Intcomex_Sync_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'intcomex_daily_sync_event' );
		wp_clear_scheduled_hook( 'intcomex_hourly_update_event' );


	}

}
