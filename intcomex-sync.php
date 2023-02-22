<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://example.com
 * @since             1.0.0
 * @package           Intcomex_Sync
 *
 * @wordpress-plugin
 * Plugin Name:       Intcomex Sync
 * Plugin URI:        https://ideasdigitales.cl
 * Description:       Sincronizador/Importador de productos de Intcomex
 * Version:           1.0.0
 * Author:            Ideas Digitales
 * Author URI:        https://ideasdigitales.cl
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       intcomex-sync
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Test to see if WooCommerce is active (including network activated).
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
    add_action(
        'admin_notices',
        function () {
            printf(
                '<div class=\"notice notice-error is-dismissible\">
	        				<p>Intcomex Sync está <strong>Activado</strong> pero necesita <a href=\"https://wordpress.org/plugins/woocommerce/\" target=\"_blank\">WooCommerce</a> para funcionar. Por favor instala <a href=\"https://wordpress.org/plugins/woocommerce/\" target=\"_blank\">WooCommerce</a> antes de continuar.
    				</div>'
            );

        }
    );

    return;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'INTCOMEX_SYNC_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-intcomex-sync-activator.php
 */
function activate_intcomex_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-intcomex-sync-activator.php';
	Intcomex_Sync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-intcomex-sync-deactivator.php
 */
function deactivate_intcomex_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-intcomex-sync-deactivator.php';
	Intcomex_Sync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_intcomex_sync' );
register_deactivation_hook( __FILE__, 'deactivate_intcomex_sync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-intcomex-sync.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_intcomex_sync() {

	$plugin = new Intcomex_Sync();
	$plugin->run();

}
run_intcomex_sync();
