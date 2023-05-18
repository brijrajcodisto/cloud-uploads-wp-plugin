<?php

class S3_Uploads_Admin {
  private $api;

  public function __construct() {
    add_action('admin_menu',  [ &$this, 'setup_menu' ]);
  }

  	/**
	 *
	 * @return S3_Uploads_Admin
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new S3_Uploads_Admin();
		}

		return self::$instance;
	}

  function setup_menu(){
    $page = add_menu_page( 'S3 Uploads', 'S3 Uploads', 'manage_options', 's3-uploads',  [ &$this, 'settings_page' ] );
    add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );
  }

  function admin_scripts() {
		wp_enqueue_script( 's3up-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), [ 'jquery' ], S3_UPLOADS_VERSION );
		wp_enqueue_script( 's3up-chartjs', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), [], S3_UPLOADS_VERSION );
		wp_enqueue_script( 's3up-js', plugins_url( 'assets/js/infinite-uploads.js', __FILE__ ), [ 'wp-color-picker' ], S3_UPLOADS_VERSION );

		$data            = [];
		$data['strings'] = [
			'leave_confirm'      => esc_html__( 'Are you sure you want to leave this tab? The current bulk action will be canceled and you will need to continue where it left off later.', 's3-uploads' ),
			'ajax_error'         => esc_html__( 'Too many server errors. Please try again.', 'infinite-uploads' ),
			'leave_confirmation' => esc_html__( 'If you leave this page the sync will be interrupted and you will have to continue where you left off later.', 's3-uploads' ),
		];

		$data['local_types'] = $this->iup_instance->get_filetypes( true );

		$api_data = $this->api->get_site_data();
		if ( $this->api->has_token() && $api_data ) {
			$data['cloud_types'] = $this->iup_instance->get_filetypes( true, $api_data->stats->site->types );
		}

		$data['nonce'] = [
			'scan'     => wp_create_nonce( 's3up_scan' ),
			'sync'     => wp_create_nonce( 's3up_sync' ),
			'delete'   => wp_create_nonce( 's3up_delete' ),
			'download' => wp_create_nonce( 's3up_download' ),
			'toggle'   => wp_create_nonce( 's3up_toggle' ),
			'video'    => wp_create_nonce( 's3up_video' ),
		];

		wp_localize_script( 's3up-js', 's3up_data', $data );
	}

  	/**
	 *
	 */
	function admin_styles() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 's3up-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, S3_UPLOADS_VERSION );
		wp_enqueue_style( 's3up-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), [ 'iup-bootstrap' ], S3_UPLOADS_VERSION );

		//hide all admin notices from another source on these pages
		//remove_all_actions( 'admin_notices' );
		//remove_all_actions( 'network_admin_notices' );
		//remove_all_actions( 'all_admin_notices' );
	}


  function settings_page() {
    global $wpdb;
    require_once( dirname( __FILE__ ) . '../templates/welcome.php' );
    $region_labels = [
			'US' => esc_html__( 'United States', 'infinite-uploads' ),
			'EU' => esc_html__( 'Europe', 'infinite-uploads' ),
		];
    <div id="iup-settings-page" class="wrap iup-background">

    </div>
  }
}
 
