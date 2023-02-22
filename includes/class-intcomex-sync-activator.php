<?php

/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/includes
 * @author     Heikel Villar <heikelvillar@gmail.com>
 */
class Intcomex_Sync_Activator
{

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate(): void
	{
		self::intcomex_prd_table();

		if (!wp_next_scheduled('intcomex_daily_sync_event')) {
			wp_schedule_event(strtotime("3:00"), 'daily', 'intcomex_daily_sync_event');
		}
		if (!wp_next_scheduled('intcomex_hourly_update_event')) {
			wp_schedule_event(time(), 'every_3_hours', 'intcomex_hourly_update_event');
		}
	}

	public static function intcomex_prd_table()
	{
		global $wpdb;
		$table_name_productos = $wpdb->prefix . "woo_intcomex_products";
		$charset_collate      = $wpdb->get_charset_collate();
		$wpdb->query("DROP TABLE IF EXISTS " . $table_name_productos);

		$sql = "CREATE TABLE " . $table_name_productos . " (
		`categoria` varchar(255) NOT NULL,
		`subcategoria` varchar(255) NOT NULL,
		`nombre` varchar(255) NOT NULL,
		`brand` varchar(255) NOT NULL,
		`sku` varchar(255) NOT NULL,
		`part_number` varchar(255) NOT NULL,
		`precio` float NOT NULL,
		`atributos` text NOT NULL,
		`thumbs` json NOT NULL,
		`descripcion_larga` text NOT NULL,
		`documentos` json NOT NULL,
		`especificaciones` json NOT NULL,
		`logo` varchar(255) NOT NULL,
		`pais` varchar(255) NOT NULL,
		PRIMARY KEY (`sku`))" . $charset_collate . " ENGINE = InnoDB;
		";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}
}
