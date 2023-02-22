<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/admin/partials
 */
?>

<div class="wrap">
    <h1>Configuración</h1>
    <hr>

    <?php settings_errors(); ?>

    <form method="POST" action="options.php">
        <?php
        do_settings_sections( 'intcomex_sync_general_settings' );
        do_settings_sections( 'intcomex_sync_price_settings' );
        settings_fields( 'intcomex_sync_general_settings' );
        ?>
        <?php submit_button(); ?>
    </form>
</div>
