<?php

class S3_Uploads_Admin {
  private $api;

  public function __construct() {
    $this->api = new S3_Uploads_Api_Handler();
    add_action('admin_menu',  [ &$this, 'setup_menu' ]);
  }

  function setup_menu(){
    $page = add_menu_page( 'S3 Uploads', 'S3 Uploads', 'manage_options', 's3-uploads',  [ &$this, 'settings_page' ] );
    add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );
  }

  function admin_scripts() {
		wp_enqueue_script( 's3up-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), [ 'jquery' ], S3_UPLOADS_VERSION );
		wp_enqueue_script( 's3up-chartjs', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), [], S3_UPLOADS_VERSION );
		wp_enqueue_script( 's3up-js', plugins_url( 'assets/js/s3-uploads.js', __FILE__ ), [ 'wp-color-picker' ], S3_UPLOADS_VERSION );
	}

  	/**
	 *
	 */
	function admin_styles() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 's3up-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, S3_UPLOADS_VERSION );
		wp_enqueue_style( 's3up-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), [ 's3up-bootstrap' ], S3_UPLOADS_VERSION );
	}


  function settings_page() {
    global $wpdb;
    
    $region_labels = [
			'US' => esc_html__( 'United States', 'infinite-uploads' ),
			'EU' => esc_html__( 'Europe', 'infinite-uploads' ),
		];
    ?>
    <div id="iup-settings-page" class="wrap iup-background">
      <?php
			// if ( $this->api->has_token() && $api_data ) {
      //   if ( ! $api_data->stats->site->files ) {
			// 		$synced           = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE synced = 1" );
			// 		$cloud_size       = $synced->size;
			// 		$cloud_files      = $synced->files;
			// 		$cloud_total_size = $api_data->stats->cloud->storage + $synced->size;
			// 	} else {
			// 		$cloud_size       = $api_data->stats->site->storage;
			// 		$cloud_files      = $api_data->stats->site->files;
			// 		$cloud_total_size = $api_data->stats->cloud->storage;
			// 	}

      //   require_once( dirname( __FILE__ ) . '/templates/header-columns.php' );

			// 	if ( ! get_site_option( 's3up_enabled' ) ) {
			// 		require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
			// 		if ( isset( $api_data->site ) && $api_data->site->upload_writeable ) {
			// 			//require_once( dirname( __FILE__ ) . '/templates/modal-upload.php' );
			// 			//require_once( dirname( __FILE__ ) . '/templates/modal-enable.php' );
			// 		}
			// 	}

			// 	require_once( dirname( __FILE__ ) . '/templates/settings.php' );

			// 	//require_once( dirname( __FILE__ ) . '/templates/modal-remote-scan.php' );
			// 	//require_once( dirname( __FILE__ ) . '/templates/modal-delete.php' );
			// 	//require_once( dirname( __FILE__ ) . '/templates/modal-download.php' );

      // } else {

      // }
        // require_once( dirname( __FILE__ ) . '../templates/header-columns.php' );
        if ( $this->api->has_token() && $api_data ) {

        }else {
          
          require_once( dirname( __FILE__ ) . '../templates/welcome.php' );
        }
      ?>
    </div>
    <?php
  }
}
 
