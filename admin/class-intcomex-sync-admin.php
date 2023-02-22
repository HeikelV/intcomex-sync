<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Intcomex_Sync
 * @subpackage Intcomex_Sync/admin
 * @author     Heikel Villar <heikelvillar@gmail.com>
 */

require_once plugin_dir_path( __DIR__ ) . 'includes/helper.php';

if ( defined( 'DOING_CRON' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
}

class Intcomex_Sync_Admin {

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
	 * @var false|mixed|null
	 */
	private $cloud_url;
	/**
	 * @var false|mixed|null
	 */
	private $api_key;
	/**
	 * @var false|mixed|null
	 */
	private $access_key;
	/**
	 * @var false|mixed|null
	 */
	private $mode;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version The version of this plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct( string $plugin_name, string $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'admin_menu', array( $this, 'add_intcomex_sync__admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_and_build_fields' ) );
		add_filter( 'manage_edit-product_columns', array( $this, 'agregar_columnas_productos' ), 1 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'mostrar_datos_columnas_productos' ), 10, 2 );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'intcomex_product_custom_fields' ) );
		add_action( 'wp_ajax_download_csv', array( $this, 'download_csv' ) );
		add_action( 'wp_ajax_get_api_json', array( $this, 'get_api_json' ) );
		add_action( 'wp_ajax_importar', array( $this, 'importar' ) );
		add_action( 'wp_ajax_intcomex_update_product_stock', array( $this, 'intcomex_update_product_stock' ), 11 );
		add_action( 'wp_ajax_nopriv_download_csv', array( $this, 'download_csv' ) );
		add_filter( 'plugin_action_links_intcomex-sync/intcomex-sync.php', array( $this, 'intcomex_sync_settings_link' ) );
		add_filter( 'cron_schedules', function ( $schedules ) {
			$schedules['every_3_hours'] = array(
				'interval' => 10800,
				'display'  => __( 'Every 3 Hours' )
			);

			return $schedules;
		} );
		add_action( 'add_meta_boxes', array( $this, 'intcomex_meta_boxes' ) );

		$this->cloud_url  = get_option( 'intcomex_cloud_url' );
		$this->api_key    = get_option( 'intcomex_api_key' );
		$this->access_key = get_option( 'intcomex_access_key' );
		$this->mode       = get_option( 'intcomex_integration' );

		if ( ! function_exists( 'plugin_log' ) ) {
			function plugin_log( $entry, $mode = 'a', $file = 'intcomex' ) {
				// Get WordPress uploads directory.
				$upload_dir = wp_upload_dir();
				$upload_dir = $upload_dir['basedir'];
				// If the entry is array, json_encode.
				if ( is_array( $entry ) ) {
					$entry = json_encode( $entry );
				}
				// Write the log file.
				$file  = $upload_dir . '/' . $file . '.log';
				$file  = fopen( $file, $mode );
				$bytes = fwrite( $file, current_time( 'mysql' ) . '::' . $entry . "\n" );
				fclose( $file );

				return $bytes;
			}
		}


	}

	public function intcomex_update_stock_on_schedule() {

		$skus = $this->getAllProductsSkus();
		foreach ( $skus as $sku ) {
			$this->intcomex_update_product_stock( $sku );

		}
	}

	/**
	 * @throws WC_Data_Exception
	 */
	public function intcomex_check_sync_on_schedule() {
		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) == 'auto' ) {

			$source = get_option( 'intcomex_sync_data_source' );

			if ( $source === 'csv' ) {
				plugin_log( 'Download CSV start' );
			}
			if ( ! $this->download_csv() ) {
				plugin_log( 'Ocurrió un error descargando el archivo .csv' );

				return;
			}
			plugin_log( 'Download JSON start' );

			if ( ! $this->get_api_json() ) {
				plugin_log( 'Ocurrió un error obteniendo el JSON' );

				return;
			}
//			$this->get_api_json();

			plugin_log( 'Inicio de la importación' );
			$this->importar();
		}

	}

	public function intcomex_meta_boxes() {

		add_meta_box( 'woocommerce-product-intcomex', __( 'INTCOMEX' ), array( $this, 'product_meta_box_intcomex' ), 'product', 'side', 'high' );

	}

	public function product_meta_box_intcomex( $product ) {
//		echo '<pre>';
//		print_r( $product );
//		echo '</pre>';
		echo '<b>Última modificación:</b> <br>' . date( 'Y/m/d h:i:s a', strtotime( $product->post_modified ) ) . '<br>';
		echo '<br>';
		$producto = wc_get_product( $product->ID );
		$producto->get_stock_status() == 'instock' ? $this->intcomex_show_button_sync( $product->ID, $producto ) : '';

	}

	public function intcomex_sync_settings_link( array $links ): array {
		$url           = get_admin_url() . 'admin.php?page=' . $this->plugin_name . '-settings';
		$settings_link = '<a href="' . $url . '">Configuración</a>';
		$links[]       = $settings_link;

		return $links;
	}

	/**
	 * @return bool|null
	 */
	public function download_csv(): ?bool {
		$file_url     = $this->cloud_url;
		$file_content = @file_get_contents( $file_url );
		if ( ! $file_content ) {
			return $this->send_error_response();
		}
		$file_path = plugin_dir_path( __DIR__ ) . 'data/products.csv';
		file_put_contents( $file_path, $file_content );

		return $this->send_success_response();

	}

	/**
	 *
	 * @return false|void
	 */
	private function send_error_response() {
		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) === 'manual' ) {
			wp_send_json( array(
				'success' => false,
				'message' => 'No se pudo conectar a la URL especificada'
			) );
		}
		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) === 'auto' ) {
			plugin_log( 'No se pudo conectar a la URL especificada' );

			return false;
		}
	}

	/**
	 *
	 * @return true|void
	 */
	private function send_success_response() {
		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) === 'manual' ) {
			wp_send_json( array(
				'success' => true,
				'message' => 'Archivo descargado con éxito'
			) );
		}
		plugin_log( 'Archivo descargado con éxito' );
		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) === 'auto' ) {
			return true;
		}
	}


	public function datos_csv() {
		// Obtener información del archivo

		$file_path = plugin_dir_path( __DIR__ ) . 'data/products.csv';

		echo '<h2>Último archivo CSV descargado:</h2>';
		echo '<div class="file-info">';

		if ( file_exists( $file_path ) ) {
			$file_name = basename( $file_path );
			$file_date = date( "d/m/y h:i a", filectime( $file_path ) );
			$file_rows = count( file( $file_path ) ) - 1;

			$file_url = plugins_url( '\data\\' . $file_name, __DIR__ );

			// Mostrar información del archivo en un div

			echo '<h3>' . $file_name . '</h3>';
			echo '<p>Fecha de creación: <b>' . $file_date . '</b></p>';
			echo '<p>Número de elementos: <b>' . $file_rows . '</b></p>';
			echo sprintf( "<a href=\"%s\" download><i class=\"fas fa-download\"></i> Descargar</a>", $file_url );

		}

		if ( $this->cloud_url !== false && $this->cloud_url !== null && $this->cloud_url !== '' ) {
			echo "<button class='button-primary' id='down_csv' data-file-url= '$this->cloud_url'><i class=\"fas fa-cloud-download-alt\"></i> Obtener nuevo CSV</button>";
		}


		echo '</div>';
		echo '<div id="loader" style="display:none; text-align: center; margin-top: 10px;">
		                <img src="images/loading.gif" alt="loading"/>Descargando...
		                </div>';

		echo '<div id="progress_bar" style="display:none; ">';
		echo '<div id="progress"></div>';
		echo '</div>';
	}

	public function datos_json() {
		// Obtener información del archivo

		$file_path = plugin_dir_path( __DIR__ ) . 'data/api_json.json';

		echo '<h2>Último archivo JSON descargado:</h2>';
		echo '<div class="file-info">';

		if ( file_exists( $file_path ) ) {
			$json     = file_get_contents( $file_path );
			$obj      = json_decode( $json );
			$cantidad = count( $obj->data );


			$file_name = basename( $file_path );
			$file_date = date( "d/m/y h:i a", filectime( $file_path ) );

			$file_url = plugins_url( '\data\\' . $file_name, __DIR__ );

			// Mostrar información del archivo en un div

			echo '<h3>' . $file_name . '</h3>';
			echo '<p>Fecha de creación: <b>' . $file_date . '</b></p>';
			echo '<p>Número de elementos: <b>' . $cantidad . '</b></p>';
			echo sprintf( "<a href=\"%s\" download><i class=\"fas fa-download\"></i> Descargar</a>", $file_url );

		}

		if ( $this->cloud_url !== false && $this->cloud_url !== null && $this->cloud_url !== '' ) {
			echo "<button class='button-primary' id='down_json'><i class=\"fas fa-cloud-download-alt\"></i> Obtener nuevo JSON</button>";
		}

		echo '</div>';
		echo '<div id="loader3" style="display:none; text-align: center; margin-top: 10px;">
		                <img src="images/loading.gif" alt="loading"/>Descargando...
		                </div>';

	}

	public function get_data_to_import() {
		$source = get_option( 'intcomex_sync_data_source' );
		$datos  = false;

		if ( $source === 'bd' ) {
			$conn = mysqli_connect( 'localhost', 'root', 'root', 'intcomex' );

			if ( ! $conn ) {
				die( "Conexión fallida: " . mysqli_connect_error() );
			}

			$sql    = "SELECT * FROM products";
			$result = mysqli_query( $conn, $sql );

			if ( mysqli_num_rows( $result ) > 0 ) {
				while ( $row = mysqli_fetch_row( $result ) ) {
					$datos[] = $row;
				}
			}

			mysqli_close( $conn );
		} elseif ( $source === 'csv' ) {
			$file_path = plugin_dir_path( __DIR__ ) . 'data/products.csv';
			if ( file_exists( $file_path ) ) {
//				$datos = array_map( 'str_getcsv', file( $file_path ) );
				$datos = array_map( function ( $line ) {
					return str_getcsv( $line, ",", '"', "'\'" );
				}, file( $file_path ) );
				array_shift( $datos );
			}
		}

		return $datos;
	}

	/**
	 * @throws WC_Data_Exception
	 */
	public function importar() {

		$products_data = $this->get_data_to_import();
		if ( ! $products_data ) {
			return;
		}

		$total_a_importar = count( $products_data );
		plugin_log( 'Total: ' . $total_a_importar . ' productos' );

		$file_path     = plugin_dir_path( __DIR__ ) . 'data/api_json.json';
		$json          = file_get_contents( $file_path );
		$products_json = json_decode( $json );
		$skus          = $this->getAllProductsSkus();

		//Partiendo de los datos de los productos a importar:
		foreach ( $products_data as $line ) {
			//Si no tiene imágenes, saltar el producto
			if ( $line[8] === '""' ) {
				continue;
			}

			//tomar todos los skus que se van importando
			$skus_import[] = $line[4];
			$product       = null;
			//Buscar si están en el json de la API
			foreach ( $products_json->data as $p ) {
				if ( $p->sku == $line[4] ) {
					$product = $p;
					break;
				}
			}
			$id = in_array( $line[4], $skus );
			if ( $product ) { //¿Está en el json?: SI!
				if ( $id === false ) {
					//¡está en el json pero no en la tienda! Creamos producto nuevo con el stock y precio del json
					$this->crear_nuevo_producto( $line, $product->precio, $product->stock );
				} else { //¿Está en la tienda?:
					//¡está en el json y en la tienda! Actualizamos Precio y stock
					$this->actualizar_precio_y_stock_por_sku( $line[4], $product->precio, $product->stock );
				}
			} else { //¿Está en el json?: NO!
				if ( $id === false ) { //¡No está en el json ni en la tienda! Creamos producto nuevo con stock 0 porque no lo conocemos
					$this->crear_nuevo_producto( $line, floatval( $line[6] ), 0 );
				}
			}
		}
		if ( ! empty( $skus_import ) ) {
			//buscamos todos los productos que están la tienda que no están en el csv y le ponemos stock 0;
			$old_products = array_diff( $skus, $skus_import );
			foreach ( $old_products as $sku ) {
				$product_id = wc_get_product_id_by_sku( $sku );
				if ( $product_id ) {
					$product = wc_get_product( $product_id );
					$product->set_stock_quantity( 0 );
					$product->set_stock_status( 'outofstock' );
					$product->save();
				}
			}
		}
		plugin_log( 'Importacion terminada' );
	}

	/**
	 * @param $line
	 * @param $precio
	 * @param $stock
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 */
	public function crear_nuevo_producto( $line, $precio, $stock ) {

		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_intcomex_products';
		$wpdb->query("TRUNCATE TABLE " . $table_name);
		$data_sql   = array(
			'categoria'         => $line[0],
			'subcategoria'      => $line[1],
			'nombre'            => $line[2],
			'brand'             => $line[3],
			'sku'               => $line[4],
			'part_number'       => $line[5],
			'precio'            => $precio,
			'atributos'         => $line[7],
			'thumbs'            => $line[8],
			'descripcion_larga' => $line[9],
			'documentos'        => $line[10],
			'especificaciones'  => $line[11],
			'logo'              => $line[12],
			'pais'              => 'Chile'
		);

		$wpdb->insert( $table_name, $data_sql );

		$this->insertCategoryIfNotExits( $line[0], $line[1] );

		$image_urls     = json_decode( $line[8], true );
		$shortDescrip   = json_decode( $line[7], true );
		$specifications = json_decode( $line[11], true );
		$docs           = json_decode( $line[10], true );
		$description    = $line[9];

		if ( ! empty( $docs ) ) {
			$description .= ' < br><h3 > Documentos:</h3 ><br > ';
			foreach ( $docs as $doc ) {
				$description .= "<a href=" . $doc['link'] . " target='_blank'><b>" . $doc['nombre'] . "</b></a><br>";
			}
		}

		$attrs = '';
		if ( ! empty( $shortDescrip['atributos'] ) ) {
			foreach ( $shortDescrip['atributos'] as $item ) {
				$attrs .= "<li>$item</li>";
			}
		}
		$cat_id      = [];
		$category    = get_term_by( 'name', $line[0], 'product_cat' );
		$subcategory = get_term_by( 'name', $line[1], 'product_cat' );
		$cat_id[]    = $category->term_id;
		$cat_id[]    = $subcategory->term_id;
		$product     = new WC_Product_Simple();
		$product->set_name( $line[2] ); // product title
		$product->set_slug( $this->slug( $line[2] ) );
		$product->set_sku( $line[4] );
		$product->set_manage_stock( true );

		$stock == 0 ? $product->set_stock_status( 'outofstock' ) : $product->set_stock_quantity( $stock );

		$product->set_regular_price( $this->convertir_precio( $precio ) ); // in current shop currency
		$product->set_short_description( $attrs );
		$product->set_description( $description );
		$product->set_category_ids( $cat_id );
		$product->add_meta_data( 'marca', $line[3] );
		$product->add_meta_data( 'numero_parte', $line[5] );
		if ( ! empty( $line[12] ) ) {
			$product->add_meta_data( 'logo_marca', $line[12] );
		}
		$raws = [];
		$i    = - 1;
		if ( is_array( $specifications ) || is_object( $specifications ) ) {
			foreach ( $specifications as $key => $value ) {
				$i ++;

				$attribute = new WC_Product_Attribute();

				$attribute_id = wc_attribute_taxonomy_id_by_name( $key );
				if ( ! $attribute_id ) {
					// Register the taxonomy
					$taxonomy_slug = sanitize_title( $key );
					if ( strlen( $taxonomy_slug ) > 32 ) {
						$taxonomy_slug = substr( $taxonomy_slug, 0, 32 );
					}
					register_taxonomy( sanitize_key( $this->slug( $taxonomy_slug ) ), array( 'product' ), array(
						'label'             => ucfirst( $key ),
						'public'            => true,
						'show_ui'           => true,
						'show_in_menu'      => true,
						'show_in_nav_menus' => true,
						'query_var'         => true,
						'rewrite'           => array( 'slug' => $this->slug( $key ) ),
						'capabilities'      => array(
							'manage_terms' => 'manage_product_terms',
							'edit_terms'   => 'edit_product_terms',
							'delete_terms' => 'delete_product_terms',
							'assign_terms' => 'assign_product_terms',
						),
					) );
					$attribute_id = wc_attribute_taxonomy_id_by_name( $key );
				}

				$attribute->set_id( $attribute_id );
				$attribute->set_name( $key );
				$attribute->set_options( array(
					$value
				) );
				$attribute->set_position( $i );
				$attribute->set_visible( 1 );
				$attribute->set_variation( 0 );
				$raws[] = $attribute;
			}
		}
		$product->set_attributes( $raws );
		$product->save();

		$this->setProductImages( $product->get_id(), $image_urls );


		//return $line;
	}

	public function actualizar_precio_y_stock_por_sku( $sku, $precio, $stock ): bool {

        //actualizar en la BD
		global $wpdb;
		$table_name = $wpdb->prefix . 'woo_intcomex_products';
		$row = $wpdb->get_row("SELECT * FROM $table_name WHERE sku =".$sku);
		$row->precio = $precio;
		$wpdb->update($table_name, (array)$row, array('sku' => $sku));

		$_product_id = wc_get_product_id_by_sku( $sku );
		$producto    = wc_get_product( $_product_id );

		// Verifica si el producto existe
		if ( $producto ) {
			$producto->set_regular_price( $this->convertir_precio( $precio ) );
			$producto->set_manage_stock( true );
			$stock == 0 ? $producto->set_stock_status( 'outofstock' ) : $producto->set_stock_quantity( $stock );
			$producto->save();

			return true;
		} else {
			return false;
		}
	}

	public function get_api_json() {
		$catalog          = $this->getCatalog();
		$catalogExtend    = $this->DownloadExtendedCatalog();
		$catalogPrice     = $this->GetPriceList();
		$catalogInventory = $this->GetInventory();
		$wooArray         = [];

		foreach ( $catalog as $valueCatalog ) {

			foreach ( $catalogExtend as $valueCatalogExtend ) {

				if ( $valueCatalog->Sku == $valueCatalogExtend->localSku ) {

					foreach ( $catalogPrice as $valueCatalogPrice ) {

						if ( $valueCatalogPrice->Sku == $valueCatalog->Sku ) {

							foreach ( $catalogInventory as $valueCatalogInventory ) {

								if ( $valueCatalogInventory->Sku == $valueCatalog->Sku ) {

									$titulo = explode( "-", $valueCatalog->Description );

									$Checkstok = ( $valueCatalogInventory->InStock == 0 ) ? 0 : $valueCatalogInventory->InStock;

									$Checkprecio = ( $valueCatalogPrice->Price->UnitPrice == 0 ) ? 0 : $valueCatalogPrice->Price->UnitPrice;

									$wooArray[] = [
										'sku'                => $valueCatalog->Sku,
										'Mpn'                => $valueCatalog->Mpn,
										'titulo'             => $titulo[0],
										'description'        => $valueCatalog->Description,
										'precio'             => $Checkprecio,
										'stock'              => $Checkstok,
										'categoriaInt'       => $valueCatalogExtend->CategoriaCompleta,
										'imagen'             => $valueCatalogExtend->Imagenes[2]->url,
										"DescripcionFabrica" => $valueCatalogExtend->DescripcionFabrica,
										"DescripcionMarca"   => $valueCatalogExtend->DescripcionMarca
									];

								}

							}

						}

					}
				}

			}
		}//main forecah

		$jsonintcomex = json_encode( array( 'data' => $wooArray ) );

		$file_path = plugin_dir_path( __DIR__ ) . 'data / api_json . json';
		file_put_contents( $file_path, $jsonintcomex );

		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) === 'manual' ) {
			wp_send_json( $jsonintcomex );
		}
		if ( get_option( 'intcomex_sync_forma_sincronizacion' ) === 'auto' ) {
			return true;
		}


	}

	public function intcomex_product_custom_fields() {
		echo ' <div class=" product_custom_field " > ';
		woocommerce_wp_text_input( array(
			'id'                => 'marca',
			'label'             => __( 'Marca', 'woocommerce' ),
			'placeholder'       => 'Marca',
			'desc_tip'          => 'true',
			'custom_attributes' => array( 'readonly' => 'readonly' )
		) );
		woocommerce_wp_text_input( array(
			'id'                => 'numero_parte',
			'label'             => __( 'Número de Parte', 'woocommerce' ),
			'placeholder'       => 'Número de Parte',
			'desc_tip'          => 'true',
			'custom_attributes' => array( 'readonly' => 'readonly' )
		) );
		echo ' </div > ';
	}

	/**
	 * @param $columns
	 *
	 * @return array
	 */
	public function agregar_columnas_productos( $columns ): array {
		unset( $columns['product_tag'] );
		$columns['edited_date']  = __( 'Edited date', 'woocommerce' );
		$columns['numero_parte'] = 'Número de parte';
		$columns['force']        = 'Forzar <br> actualización';

		return ( $columns );
	}

	public function mostrar_datos_columnas_productos( $column, $postid ) {
		global $post;
		// Get product object
		$product = wc_get_product( $postid );
//		if ( $column == 'marca' ) {
//			echo get_post_meta( $post->ID, 'marca', true );
//		}
		if ( $column == 'edited_date' ) {
			// Get product date modified
			$date_modified = $product->get_date_modified();
			// Echo output
			echo 'Modified' . ' < br><span title = "' . date( 'Y/m/d h:i:s a', strtotime( $date_modified ) ) . '" > ' . date( 'Y / m / d', strtotime( $date_modified ) ) . ' at ' . date( 'h:i a', strtotime( $date_modified ) ) . ' </span > ';
		}
		if ( $column == 'numero_parte' ) {
			echo get_post_meta( $post->ID, 'numero_parte', true );
		}
		if ( $column == 'force' ) {

			if ( $product->get_stock_status() === 'instock' ) {
				$this->intcomex_show_button_sync( $postid, $product );
			}


		}
	}

	/**
	 * @param $string
	 *
	 * @return string
	 */
	public function slug( $string ): string {
		setlocale( LC_ALL, "en_US.utf8" );
		$string = iconv( "utf-8", "ascii//TRANSLIT", $string );
		$string = strtolower( $string );
		$string = preg_replace( ' / [^a-z0-9-]+/', '', $string );
		$string = str_replace( ' ', '-', $string );

		return trim( $string, '-' );
	}

	/**
	 * @param $cat
	 * @param $subcat
	 *
	 * @return void
	 */
	public function insertCategoryIfNotExits( $cat, $subcat ): void {
		//insert Category if not exits
		if ( ! term_exists( $cat ) ) {
			wp_insert_term( $cat, 'product_cat', array(
				'description' => '',
				'slug'        => $this->slug( $cat )
			) );
		}

		$parent_category    = term_exists( $cat, 'product_cat' ); // array is returned if taxonomy is given
		$parent_category_id = $parent_category['term_id']; // get numeric term id

		//insert SubCategory if not exits
		if ( ! term_exists( $subcat ) ) {
			wp_insert_term( $subcat, 'product_cat', array(
				'description' => '',
				'slug'        => $this->slug( $cat ),
				'parent'      => $parent_category_id
			) );
		}
	}

	//TODO: refactorizar esta función
	public function setProductImages( $product_id, $image_urls ) {

		$gallery_images = array();

		foreach ( $image_urls as $key => $image_url ) {

			if ( strpos( $image_url, 'store . intcomex . com' ) !== false ) {
				$image_url = $this->changeURL( $image_url );
			}

			// Get the image data from the URL
			$args       = array(
				'timeout'     => '30',
				'redirection' => '5',
				'sslverify'   => false // for localhost
			);
			$response   = wp_remote_get( $image_url, $args );
			$image_data = wp_remote_retrieve_body( $response );

			// Set variables for storage
			preg_match( ' / [^\?]+ \.( jpg | JPG | jpe | JPE | jpeg | JPEG | gif | GIF | png | PNG ) / ', $image_url, $matches );
			$file_array['name']     = basename( $matches[0] );
			$file_array['tmp_name'] = wp_tempnam( $file_array['name'] );

			// Save the image data to the file
			file_put_contents( $file_array['tmp_name'], $image_data );

			// Upload the image as an attachment
			$id = media_handle_sideload( $file_array, $product_id );

			if ( $key == 0 ) {
				// set the first image as the featured image for the product
				set_post_thumbnail( $product_id, $id );
			} else {
				// add the other images to the gallery
				$gallery_images[] = $id;
			}
		}

		if ( sizeof( $gallery_images ) > 0 ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_images ) ); //set the images id's left over after the array shift as the gallery images
		}
	}

	/**
	 * Intcomex Sync menu inside Woocommerce menu
	 *
	 * @return void
	 * @since    1.0.0
	 */
	public function add_intcomex_sync__admin_menu(): void {

		add_menu_page( "Intcomex Sync", "Intcomex Sync", 'manage_options', $this->plugin_name . '-general', array( $this, 'display_intcomex_sync_admin_general' ), 'dashicons-update', '55' );

		add_submenu_page( $this->plugin_name . '-general', 'Configuración', 'Configuración', 'manage_options', $this->plugin_name . '-settings', array( $this, 'display_intcomex_sync_admin_settings' ), 50 );
	}

	/**
	 * Display general
	 *
	 * @return void
	 * @throws WC_Data_Exception
	 * @since    1.0.0
	 */
	public function display_intcomex_sync_admin_general(): void {
		require_once 'partials/' . $this->plugin_name . '-admin-display.php';
		$this->datos_csv();
		echo '<hr><br>';
		$this->datos_json();
		echo '<hr><br>';

		echo '<div style="text-align: center">';
		echo "<button class='button-primary' id='import_button'><i class=\"fas fa-cloud-rain\"></i> IMPORTAR</button>";

		echo '</div>';
		echo '<div id="loader2" style="display:none; text-align: center; margin-top: 10px;">
		                <img src="images/loading.gif" alt="loading"/>Importando...
		                </div>';
	}

	/**
	 * Display settings
	 *
	 * @return void
	 * @since    1.0.0
	 */
	public function display_intcomex_sync_admin_settings(): void {
		require_once 'partials/' . $this->plugin_name . '-admin-settings.php';
	}

	/**
	 * Declare sections Fields
	 *
	 * @return void
	 * @since    1.0.0
	 */
	public function register_and_build_fields(): void {
		add_settings_section( 'intcomex_sync_general_section', 'General', array( $this, 'intcomex_sync_display_general_account' ), 'intcomex_sync_general_settings' );
		add_settings_section( 'intcomex_sync_price_section', 'Formula para precio', array( $this, 'intcomex_sync_display_general_account' ), 'intcomex_sync_general_settings' );

		unset( $args );

		$args = array(
			array(
				'title'    => 'Modo de sincronización: ',
				'type'     => 'select',
				'name'     => 'intcomex_sync_forma_sincronizacion',
				'id'       => 'intcomex_sync_forma_sincronizacion',
				'required' => true,
				'options'  => array(
					'manual' => 'MANUAL',
					'auto'   => 'AUTOMÁTICO',
				),
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_general_section',
			),
			array(
				'title'    => 'Fuente de Datos: ',
				'type'     => 'select',
				'name'     => 'intcomex_sync_data_source',
				'id'       => 'intcomex_sync_data_source',
				'required' => true,
				'options'  => array(
					'csv' => 'CSV',
					'bd'  => 'BD',
				),
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_general_section',
			),
			array(
				'title'    => 'URL para obtener CSV: ',
				'type'     => 'input',
				'id'       => 'intcomex_cloud_url',
				'name'     => 'intcomex_cloud_url',
				'required' => false,
				'class'    => 'regular-text',
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_general_section',
			),
			array(
				'title'    => 'INTCOMEX API KEY: ',
				'type'     => 'input',
				'id'       => 'intcomex_api_key',
				'name'     => 'intcomex_api_key',
				'required' => true,
				'class'    => 'regular-text',
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_general_section',
			),
			array(
				'title'    => 'INTCOMEX ACCESS KEY: ',
				'type'     => 'input',
				'id'       => 'intcomex_access_key',
				'name'     => 'intcomex_access_key',
				'required' => true,
				'class'    => 'regular-text',
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_general_section',
			),
			array(
				'title'    => 'INTCOMEX Modo de integracion: ',
				'type'     => 'select',
				'name'     => 'intcomex_integration',
				'id'       => 'intcomex_integration',
				'required' => true,
				'options'  => array(
					'test' => 'TEST',
					'prod' => 'PROD',
				),
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_general_section',
			),
			array(
				'title'    => 'FM ',
				'type'     => 'number',
				'name'     => 'intcomex_fm',
				'id'       => 'intcomex_fm',
				'required' => true,
				'min'      => 0,
				'max'      => 100,
				'step'     => 0.0001,
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_price_section',
			),
			array(
				'title'    => 'Comisión',
				'type'     => 'number',
				'name'     => 'intcomex_commission',
				'id'       => 'intcomex_commission',
				'required' => true,
				'min'      => 0,
				'max'      => 100,
				'step'     => 0.0001,
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_price_section',
			),
			array(
				'title'    => 'Dollar',
				'type'     => 'number',
				'name'     => 'intcomex_dolar',
				'id'       => 'intcomex_dolar',
				'required' => true,
				'min'      => 0,
				'max'      => 10000,
				'step'     => 0.01,
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_price_section',
			),
			array(
				'title'    => 'Tratamiento de decimales: ',
				'type'     => 'select',
				'name'     => 'intcomex_decimals',
				'id'       => 'intcomex_decimals',
				'required' => true,
				'options'  => array(
					'red'   => 'REDONDEO',
					'trunc' => 'TRUNCAR',
				),
				'group'    => 'intcomex_sync_general_settings',
				'section'  => 'intcomex_sync_price_section',
			),
		);
		foreach ( $args as $arg ) {
			add_settings_field( $arg['id'], $arg['title'], array( $this, 'intcomex_sync_render_settings_field' ), $arg['group'], $arg['section'], $arg );
			register_setting( $arg['group'], $arg['id'] );
		}
	}

	/**
	 * General settings Header
	 *
	 * @return void
	 * @since    1.0.0
	 */
	public function intcomex_sync_display_general_account(): void {
		?>
        <!--        <h4>Testing</h4>-->
        <!--         <hr>-->
		<?php
	}

	/**
	 * Render html settings fields
	 *
	 * @param array $args Array or args.
	 *
	 * @return void
	 * @since    1.0.0
	 */
	public function intcomex_sync_render_settings_field( array $args ): void {
		$required_attr    = $args['required'] ? 'required' : '';
		$pattern_attr     = isset( $args['pattern'] ) ? 'pattern=' . $args['pattern'] : '';
		$placeholder_attr = isset( $args['placeholder'] ) ? 'placeholder=' . $args['placeholder'] : '';
		$default_value    = $args['default'] ?? '';

		switch ( $args['type'] ) {
			case 'input':
				printf( '<input type="' . $args['type'] . '" id="' . $args['id'] . '" name="' . $args['name'] . '"class="' . $args['class'] . '"' . $required_attr . ' ' . $placeholder_attr . ' ' . $pattern_attr . '  value="%s" />', get_option( $args['id'] ) ? esc_attr( get_option( $args['id'] ) ) : esc_attr( $default_value ) );
				break;
			case 'number':
				printf( '<input type="' . $args['type'] . '" id="' . $args['id'] . '" name="' . $args['name'] . '" min="' . $args['min'] . '" max="' . $args['max'] . '" step="' . $args['step'] . '" ' . $required_attr . ' value="%s"/>', get_option( $args['id'] ) ? esc_attr( get_option( $args['id'] ) ) : '' );
				break;
			case 'select':
				$option = get_option( $args['id'] );
				$items  = $args['options'];
				echo sprintf( '<select id="%s" name="%s">', esc_attr( $args['id'] ), esc_attr( $args['id'] ) );
				foreach ( $items as $key => $item ) {
					$selected = ( $option === $key ) ? 'selected="selected"' : '';
					echo sprintf( "<option value='%s' %s>%s</option>", esc_attr( $key ), esc_attr( $selected ), esc_attr( $item ) );
				}
				echo '</select>';
				break;
			default:
				break;
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/intcomex-sync-admin.css', array(), $this->version );
		wp_enqueue_style( 'fontawesome', '//cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css' );


	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/intcomex-sync-admin.js', array( 'jquery' ), $this->version );
		wp_localize_script( $this->plugin_name, 'intcomex', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

	}

	/**
	 * @param $image_url
	 *
	 * @return array|string|string[]
	 */
	public function changeURL( $image_url ) {
		return str_replace( 'https', 'http', $image_url );
	}

	public function getCatalog() {
		$Cla            = new CurlRequest();
		$Cla->apiKey    = $this->api_key;
		$Cla->accessKey = $this->access_key;
		$Cla->urlQuery  = 'https://intcomex-' . $this->mode . '.apigee.net/v1/getcatalog';

		return $Cla->getRequest();
	}

	public function DownloadExtendedCatalog() {
		$Cla            = new CurlRequest();
		$Cla->apiKey    = $this->api_key;
		$Cla->accessKey = $this->access_key;
		$Cla->urlQuery  = 'https://intcomex-' . $this->mode . '.apigee.net/v1/downloadextendedcatalog';

		return $Cla->getRequest();
	}

	public function GetPriceList() {
		$Cla            = new CurlRequest();
		$Cla->apiKey    = $this->api_key;
		$Cla->accessKey = $this->access_key;
		$Cla->urlQuery  = 'https://intcomex-' . $this->mode . '.apigee.net/v1/getpricelist';

		return $Cla->getRequest();
	}


	public function GetProductStock( $sku ) {
		$Cla            = new CurlRequest();
		$Cla->apiKey    = $this->api_key;
		$Cla->accessKey = $this->access_key;
		// https://intcomex-test.apigee.net/v1/getproduct?locale=es&sku=AT216HEW30
		$Cla->urlQuery = 'https://intcomex-' . $this->mode . '.apigee.net/v1/getproduct?sku=' . $sku;

		return $Cla->getRequest();
	}

	public function GetInventory() {
		$Cla            = new CurlRequest();
		$Cla->apiKey    = $this->api_key;
		$Cla->accessKey = $this->access_key;
		$Cla->urlQuery  = 'https://intcomex-' . $this->mode . '.apigee.net/v1/getinventory';

		return $Cla->getRequest();
	}


	private function convertir_precio( $precio ): float {
		$fm         = floatval( get_option( 'intcomex_fm' ) );
		$commission = floatval( get_option( 'intcomex_commission' ) );
		$dollar     = floatval( get_option( 'intcomex_dolar' ) );

		$trat_decimal = get_option( 'intcomex_decimals' );

		if ( $trat_decimal == 'red' ) {
			return round( ( $precio * $dollar ) * $fm * $commission );
		} else {
			return round( ( $precio * $dollar ) * $fm * $commission, 0, PHP_ROUND_HALF_DOWN );
		}
	}

	/**
	 * @return array
	 */
	public function getAllProductsSkus(): array {
		//Obtener una lista de todos los skus de los productos en la tienda:
		$productos = get_posts( array(
			'post_type'      => 'product',
			'posts_per_page' => - 1,
			'meta_key'       => '_sku',
			'fields'         => 'ids'
		) );

		return array_map( function ( $id ) {
			return get_post_meta( $id, '_sku', true );
		}, $productos );
	}

	/**
	 * @param $sku
	 *
	 * @return void
	 */
	public function intcomex_update_product_stock( $sku ): void {
		$skun        = $_POST['prod_sku'] ?? $sku;
		$_product_id = wc_get_product_id_by_sku( $skun );
		$producto    = wc_get_product( $_product_id );

		$data = $this->getProductStock( $skun );
		$producto->set_regular_price( $this->convertir_precio( $data->Price->UnitPrice ) );
		if ( $data->InStock == 0 ) {
			$producto->set_stock_quantity( 0 );
			$producto->set_stock_status( 'outofstock' );
		} else {
			$producto->set_stock_status();
			$producto->set_stock_quantity( $data->InStock );

		}

		$producto->save();
	}

	/**
	 * @param $postid
	 * @param $product
	 *
	 * @return void
	 */
	public function intcomex_show_button_sync( $postid, $product ): void {
		echo sprintf( '<button id="%s" prd_sku="%s" class="button-primary up_prd_button">Sync</button> ', esc_attr( $postid ), esc_attr( $product->get_sku() ) );
		echo sprintf( '<div id="syncLoading%s" style="display:none;">
                        <img src="images/loading.gif" alt="loading"/>
                    </div>', esc_attr( $postid ) );
	}

}
