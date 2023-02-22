<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/public
 * @author     Heikel Villar <heikelvillar@gmail.com>
 */
class Intcomex_Sync_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $plugin_name The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string $version The current version of this plugin.
	 */
	private string $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( string $plugin_name, string $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'woocommerce_product_meta_start', array( $this, 'woocommerce_custom_fields_display' ) );
		add_filter( 'woocommerce_product_query_meta_query', array($this, 'filter_products_with_custom_field'), 10, 2 );
	}

	public function filter_products_with_custom_field( $meta_query, $query ) {
		$meta_key = 'marca'; // <= Here define the meta key

		if ( ! is_admin() && isset( $_GET[ $meta_key ] ) && ! empty( $_GET[ $meta_key ] ) ) {
			$meta_query[] = array(
				'key'   => $meta_key,
				'value' => esc_attr( $_GET[ $meta_key ] ),
			);
		}

		return $meta_query;
	}

	public function woocommerce_custom_fields_display() {
		global $post;
		$product                               = wc_get_product( $post->ID );
		$custom_fields_woocommerce_logo        = $product->get_meta( 'logo_marca' );
		$custom_fields_woocommerce_marca       = $product->get_meta( 'marca' );
		$filter_link                           = get_permalink( wc_get_page_id( 'shop' ) ) . '?marca=' . $custom_fields_woocommerce_marca;
		$custom_fields_woocommerce_part_number = $product->get_meta( 'numero_parte' );
		if ( $custom_fields_woocommerce_marca ) {
			if ( $custom_fields_woocommerce_logo ) {
				printf( '<a href="%s"><img class="" alt="%s" src="%s"></a>', $filter_link, esc_html( $custom_fields_woocommerce_marca ), esc_html( $custom_fields_woocommerce_logo ) );
			} else {
				printf( '<a href="%s">%s</a>', $filter_link, esc_html( $custom_fields_woocommerce_marca ) );

			}
		}
		if ( $custom_fields_woocommerce_part_number ) {
			printf( '<span class="sku_wrapper">Número de Parte: <span class="sku">%s</span></span>', esc_html( $custom_fields_woocommerce_part_number ) );
		}
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/intcomex-sync-public.css', array(), $this->version );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/intcomex-sync-public.js', array( 'jquery' ), $this->version );

	}

}
