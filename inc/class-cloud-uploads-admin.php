<?php

class Cloud_Uploads_Admin {
  private $api;
  private static $instance;
	private $capability;
	public $ajax_timelimit = 20;
	private $local_sync;
	private $auth_error;
	private $remote_files;

  public function __construct() {
    $this->api = new Cloud_Uploads_Api_Handler();
		$this->capability = apply_filters( 'cloud_uploads_settings_capability', ( is_multisite() ? 'manage_network_options' : 'manage_options' ) );

		if ( is_multisite() ) {
			//multisite
			add_action('admin_menu',  [ &$this, 'setup_menu' ]);
			add_action( 'load-settings_page_cloud_uploads', [ &$this, 'intercept_auth' ] );
		} else {
			//single site
			add_action('admin_menu',  [ &$this, 'setup_menu' ]);
			add_action( 'load-toplevel_page_cloud_uploads', [ &$this, 'intercept_auth' ] );
		}
    
    if ( is_main_site() ) {
			add_action( 'wp_ajax_cloud-uploads-filelist', [ &$this, 'ajax_filelist' ] );
			add_action( 'wp_ajax_cloud-uploads-remote-filelist', [ &$this, 'ajax_remote_filelist' ] );
			add_action( 'wp_ajax_cloud-uploads-sync', [ &$this, 'ajax_sync' ] );
			add_action( 'wp_ajax_cloud-uploads-sync-errors', [ &$this, 'ajax_sync_errors' ] );
			add_action( 'wp_ajax_cloud-uploads-reset-errors', [ &$this, 'ajax_reset_errors' ] );
			add_action( 'wp_ajax_cloud-uploads-delete', [ &$this, 'ajax_delete' ] );
			add_action( 'wp_ajax_cloud-uploads-download', [ &$this, 'ajax_download' ] );
			add_action( 'wp_ajax_cloud-uploads-toggle', [ &$this, 'ajax_toggle' ] );
			add_action( 'wp_ajax_cloud-uploads-status', [ &$this, 'ajax_status' ] );
		}
  }

  	/**
	 *
	 * @return Cloud_Uploads_Admin
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Cloud_Uploads_Admin();
		}

		return self::$instance;
	}

  /**
	 * Get the settings url with optional url args.
	 *
	 * @param array $args Optional. Same as for add_query_arg()
	 *
	 * @return string Unescaped url to settings page.
	 */
	function settings_url( $args = [] ) {
		if ( is_multisite() ) {
			$base = network_admin_url( 'admin.php?page=cloud_uploads' );
		} else {
			$base = admin_url( 'admin.php?page=cloud_uploads' );
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Get a url to the public Cloud Uploads site.
	 *
	 * @param string $path Optional path on the site.
	 *
	 * @return Cloud_Uploads_Api_Handler|string
	 */
	function api_url( $path = '' ) {
		$url = trailingslashit( $this->api->server_root );

		if ( $path && is_string( $path ) ) {
			$url .= ltrim( $path, '/' );
		}

		return $url;
	}

  function setup_menu(){
    $page = add_menu_page( 'Cloud Uploads', 'Cloud Uploads', 'manage_options', 'cloud_uploads',  [ &$this, 'settings_page' ], plugins_url( 'assets/img/logo-menu.png', __FILE__ ) );
    add_action( 'admin_print_scripts-' . $page, [ &$this, 'admin_scripts' ] );
		add_action( 'admin_print_styles-' . $page, [ &$this, 'admin_styles' ] );
  }

  function admin_scripts() {
		wp_enqueue_script( 'cup-bootstrap', plugins_url( 'assets/bootstrap/js/bootstrap.bundle.min.js', __FILE__ ), [ 'jquery' ], CLOUD_UPLOADS_VERSION );
		wp_enqueue_script( 'cup-chartjs', plugins_url( 'assets/js/Chart.min.js', __FILE__ ), [], CLOUD_UPLOADS_VERSION );
		wp_enqueue_script( 'cup-js', plugins_url( 'assets/js/cloud-uploads.js', __FILE__ ), [ 'wp-color-picker' ], CLOUD_UPLOADS_VERSION );

    $data = [];
		$data['strings'] = [
			'leave_confirm'      => esc_html__( 'Are you sure you want to leave this tab? The current bulk action will be canceled and you will need to continue where it left off later.', 'cloud-uploads' ),
			'ajax_error'         => esc_html__( 'Too many server errors. Please try again.', 'cloud-uploads' ),
			'leave_confirmation' => esc_html__( 'If you leave this page the sync will be interrupted and you will have to continue where you left off later.', 'cloud-uploads' ),
		];

		$data['local_types'] = $this->get_filetypes( true );

		$api_data = $this->api->get_site_data();
		if ( $this->api->has_token() && $api_data ) {
			$data['cloud_types'] = $this->get_filetypes( true, $api_data->stats->site->types );
		}

		$data['nonce'] = [
			'scan'     => wp_create_nonce( 'cup_scan' ),
			'sync'     => wp_create_nonce( 'cup_sync' ),
			'delete'   => wp_create_nonce( 'cup_delete' ),
			'download' => wp_create_nonce( 'cup_download' ),
			'toggle'   => wp_create_nonce( 'cup_toggle' ),
			'video'    => wp_create_nonce( 'cup_video' ),
		];

		wp_localize_script( 'cup-js', 'cup_data', $data );
	}

	/**
	 * Identical to WP core size_format() function except it returns "0 GB" instead of false on failure.
	 *
	 * @param int|string $bytes    Number of bytes. Note max integer size for integers.
	 * @param int        $decimals Optional. Precision of number of decimal places. Default 0.
	 *
	 * @return string Number string on success.
	 */
	function size_format_zero( $bytes, $decimals = 0 ) {
		if ( $bytes > 0 ) {
			return size_format( $bytes, $decimals );
		} else {
			return '0 GB';
		}
	}

	public function set_rewrite_url() {
		$api_data = $this->api->get_site_data();
		if ( ( ! defined( 'CLOUD_UPLOADS_DISABLE_REPLACE_UPLOAD_URL' ) || ! CLOUD_UPLOADS_DISABLE_REPLACE_UPLOAD_URL ) && $api_data->site->cdn_enabled ) {
			//makes this work with pre 3.5 MU ms_files rewriting (ie domain.com/files/filename.jpg)
			$original_root_dirs = $this->get_original_upload_dir_root();
			$replacements       = [ $original_root_dirs['baseurl'] ];
			//if we have a custom domain add original cdn url for replacement
			if ( $this->get_s3_url() !== 'https://' . $api_data->site->cname ) {
				$replacements[] = 'https://' . $api_data->site->cname;
			}

			//makes this work with pre 3.5 MU ms_files rewriting (ie domain.com/files/filename.jpg)
			if ( is_multisite() && substr_compare( $original_root_dirs['baseurl'], '/files', - strlen( '/files' ) ) === 0 ) {
				$new_dirs = wp_get_upload_dir();
				$cdn_url  = str_replace( 'iu://' . untrailingslashit( $this->bucket ), $api_data->site->cname, $new_dirs['basedir'] );
			} else {
				$cdn_url = $this->get_s3_url();
			}
			new Cloud_Uploads_Rewriter( $original_root_dirs['baseurl'], $replacements, $cdn_url );
		}
	}

	public function get_filetypes( $is_chart = false, $cloud_types = false ) {
		global $wpdb;

		if ( false !== $cloud_types ) {
			if ( empty( $cloud_types ) ) { //estimate if sync was fresh
				$types = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1 GROUP BY type ORDER BY size DESC" );
			} else {
				$types = $cloud_types;
			}
		} else {
			$types = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE deleted = 0 GROUP BY type ORDER BY size DESC" );
		}

		$data = [];
		foreach ( $types as $type ) {
			$data[ $type->type ] = (object) [
				'color' => $this->get_file_type_format( $type->type, 'color' ),
				'label' => $this->get_file_type_format( $type->type, 'label' ),
				'size'  => $type->size,
				'files' => $type->files,
			];
		}

		$chart = [];
		if ( $is_chart ) {
			foreach ( $data as $item ) {
				$chart['datasets'][0]['data'][]            = $item->size;
				$chart['datasets'][0]['backgroundColor'][] = $item->color;
				$chart['labels'][]                         = $item->label . ": " . sprintf( _n( '%s file totalling %s', '%s files totalling %s', $item->files, 'cloud-uploads' ), number_format_i18n( $item->files ), size_format( $item->size, 1 ) );
			}

			$total_size     = array_sum( wp_list_pluck( $data, 'size' ) );
			$total_files    = array_sum( wp_list_pluck( $data, 'files' ) );
			$chart['total'] = sprintf( _n( '%s / %s File', '%s / %s Files', $total_files, 'cloud-uploads' ), size_format( $total_size, 2 ), number_format_i18n( $total_files ) );

			return $chart;
		}

		return $data;
	}

	public function get_file_type_format( $type, $key ) {
		$labels = [
			'image'    => [ 'color' => '#26A9E0', 'label' => esc_html__( 'Images', 'cloud-uploads' ) ],
			'audio'    => [ 'color' => '#00A167', 'label' => esc_html__( 'Audio', 'cloud-uploads' ) ],
			'video'    => [ 'color' => '#C035E2', 'label' => esc_html__( 'Video', 'cloud-uploads' ) ],
			'document' => [ 'color' => '#EE7C1E', 'label' => esc_html__( 'Documents', 'cloud-uploads' ) ],
			'archive'  => [ 'color' => '#EC008C', 'label' => esc_html__( 'Archives', 'cloud-uploads' ) ],
			'code'     => [ 'color' => '#EFED27', 'label' => esc_html__( 'Code', 'cloud-uploads' ) ],
			'other'    => [ 'color' => '#F1F1F1', 'label' => esc_html__( 'Other', 'cloud-uploads' ) ],
		];

		if ( isset( $labels[ $type ] ) ) {
			return $labels[ $type ][ $key ];
		} else {
			return $labels['other'][ $key ];
		}
	}

  	/**
	 *
	 */
	function admin_styles() {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'cup-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, CLOUD_UPLOADS_VERSION );
		wp_enqueue_style( 'cup-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), [ 'cup-bootstrap' ], CLOUD_UPLOADS_VERSION );
	}

		/**
	 * Checks for temp_token in url and processes auth if present.
	 */
	function intercept_auth() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_die( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'cloud-uploads' ) );
		}

		if ( ! empty( $_GET['temp_token'] ) ) {
			$result = $this->api->authorize( $_GET['temp_token'] );
			if ( ! $result ) {
				$this->auth_error = $this->api->api_error;
			} else {
				wp_safe_redirect( $this->settings_url() );
			}
		}

		if ( isset( $_GET['clear'] ) ) {
			delete_site_option( 'cup_files_scanned' );
			wp_safe_redirect( $this->settings_url() );
		}

		if ( isset( $_GET['refresh'] ) ) {
			$this->api->get_site_data( true );
			wp_safe_redirect( $this->settings_url() );
		}

		if ( isset( $_GET['reinstall'] ) ) {
			//cloud_uploads_install();
			wp_safe_redirect( $this->settings_url() );
		}
	}

  function settings_page() {
    global $wpdb;
    
    $region_labels = [
			'US' => esc_html__( 'United States', 'cloud-uploads' ),
			'EU' => esc_html__( 'Europe', 'cloud-uploads' ),
		];

		$stats    = $this->get_sync_stats();
		$api_data = $this->api->get_site_data();
    ?>
    <div id="cup-settings-page" class="wrap cup-background">
      <?php
			// if ( $this->api->has_token() && $api_data ) {
      //   if ( ! $api_data->stats->site->files ) {
			// 		$synced           = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1" );
			// 		$cloud_size       = $synced->size;
			// 		$cloud_files      = $synced->files;
			// 		$cloud_total_size = $api_data->stats->cloud->storage + $synced->size;
			// 	} else {
			// 		$cloud_size       = $api_data->stats->site->storage;
			// 		$cloud_files      = $api_data->stats->site->files;
			// 		$cloud_total_size = $api_data->stats->cloud->storage;
			// 	}

      //   require_once( dirname( __FILE__ ) . '/templates/header-columns.php' );

			// 	if ( ! get_site_option( 'cup_enabled' ) ) {
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
				?>
				<?php if ( $this->auth_error ) { ?>
				<div class="alert alert-danger mt-1 alert-dismissible fade show" role="alert">
					<?php echo esc_html( $this->auth_error ); ?>
					<button type="button" class="close" data-dismiss="alert" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<?php } ?>
				<div id="cup-error" class="alert alert-danger mt-1" role="alert"></div>
			<?php
				if ($this->api->has_token() && $api_data ) {
					if ( ! $api_data->stats->site->files ) {
						$synced           = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1" );
						$cloud_size       = $synced->size;
						$cloud_files      = $synced->files;
						$cloud_total_size = $api_data->stats->cloud->storage + $synced->size;
					} else {
						$cloud_size       = $api_data->stats->site->storage;
						$cloud_files      = $api_data->stats->site->files;
						$cloud_total_size = $api_data->stats->cloud->storage;
					}
					require_once( dirname( __FILE__ ) . '/templates/account.php' );
					if ( isset( $api_data->site ) && $api_data->site->upload_writeable ) {
						require_once( dirname( __FILE__ ) . '/templates/modal-upload.php' );
						require_once( dirname( __FILE__ ) . '/templates/modal-enable.php' );
					}
				} else {
					if ( ! empty( $stats['files_finished'] ) && $stats['files_finished'] >= ( time() - DAY_IN_SECONDS ) ) {
						require_once( dirname( __FILE__ ) . '/templates/local-file-overview.php' );
					} else {
						require_once( dirname( __FILE__ ) . '/templates/welcome.php' );
					}
				}
				require_once( dirname( __FILE__ ) . '/templates/modal-remote-scan.php' );
				require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
				require_once( dirname( __FILE__ ) . '/templates/modal-delete.php' );
				require_once( dirname( __FILE__ ) . '/templates/modal-download.php' );
				require_once( dirname( __FILE__ ) . '/templates/footer.php' );
				?>

    </div>
    <?php
  }

		/**
	 * Logs a debugging line.
	 */
	function sync_debug_log( $message ) {
		if ( defined( 'CLOUD_UPLOADS_API_DEBUG' ) && CLOUD_UPLOADS_API_DEBUG ) {
			$log = '[CLOUD_UPLOADS Sync Debug] %s %s';

			$msg = sprintf(
				$log,
				CLOUD_UPLOADS_VERSION,
				$message
			);
			error_log( $msg );
		}
	}

		/**
	 * Enable or disable cloud stream wrapper and url rewriting.
	 *
	 * @param bool $enabled
	 */
	public function toggle_cloud( $enabled ) {
		if ( is_multisite() ) {
			update_site_option( 'cup_enabled', $enabled );
		} else {
			update_option( 'cup_enabled', $enabled, true );
		}
		if ( $enabled ) {

			//ping the API to let them know we've enabled the site
			// $this->api->call( "site/" . $this->api->get_site_id() . "/enable", [], 'POST', [
			// 	'timeout'  => 0.01,
			// 	'blocking' => false,
			// ] );
			//add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
			//not ideal but such a dramatic change of replacing upload dirs and urls can break some plugins/themes
			wp_cache_flush();

			//Hummingbird plugin
			do_action( 'wphb_clear_page_cache' );

			//WP rocket plugin
			if ( function_exists( 'rocket_clean_domain' ) ) {
				rocket_clean_domain();
			}
		}
	}

	public function filter_upload_dir( $dirs ) {
		$api_data = $this->api->get_site_data();
    $dirs['url'] = $api_data->site->cdn_url . $dirs['path'];
		return $dirs;
	}

	public function get_sync_stats() {
		global $wpdb;

		$total     = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE 1" );
		$local     = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE deleted = 0" );
		$synced    = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1" );
		$synced_or_error    = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1 AND errors >=3" );
		$deletable = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1 AND deleted = 0" );
		$deleted   = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1 AND deleted = 1" );

		$progress = (array) get_site_option( 'cup_files_scanned' );

		return array_merge( $progress, [
			'is_data'         => (bool) $total->files,
			'total_files'     => number_format_i18n( (int) $total->files ),
			'total_size'      => size_format( (int) $total->size, 2 ),
			'local_files'     => number_format_i18n( (int) $local->files ),
			'local_size'      => size_format( (int) $local->size, 2 ),
			'cloud_files'     => number_format_i18n( (int) $synced->files ),
			'synced_or_error'     => number_format_i18n( (int) $synced_or_error->files ),
			'cloud_size'      => size_format( (int) $synced->size, 2 ),
			'deletable_files' => number_format_i18n( (int) $deletable->files ),
			'deletable_size'  => size_format( (int) $deletable->size, 2 ),
			'deleted_files'   => number_format_i18n( (int) $deleted->files ),
			'deleted_size'    => size_format( (int) $deleted->size, 2 ),
			'remaining_files' => number_format_i18n( max( $total->files - $synced->files, 0 ) ),
			'remaining_size'  => size_format( max( $total->size - $total->transferred, 0 ), 2 ),
			'pcnt_complete'   => ( $local->size ? min( 100, round( ( $total->transferred / $total->size ) * 100, 2 ) ) : 0 ),
			'pcnt_downloaded' => ( $synced->size ? min( 100, round( 100 - ( ( $deleted->size / $synced->size ) * 100 ), 2 ) ) : 0 ),
		] );
	}

		/**
	 * Get root upload dir for multisite. Based on _wp_upload_dir().
	 *
	 * @return array See wp_upload_dir()
	 */
	public function get_original_upload_dir_root() {
		$siteurl     = get_option( 'siteurl' );
		$upload_path = trim( get_option( 'upload_path' ) );

		if ( empty( $upload_path ) || 'wp-content/uploads' === $upload_path ) {
			$dir = WP_CONTENT_DIR . '/uploads';
		} elseif ( 0 !== strpos( $upload_path, ABSPATH ) ) {
			// $dir is absolute, $upload_path is (maybe) relative to ABSPATH.
			$dir = path_join( ABSPATH, $upload_path );
		} else {
			$dir = $upload_path;
		}

		$url = get_option( 'upload_url_path' );
		if ( ! $url ) {
			if ( empty( $upload_path ) || ( 'wp-content/uploads' === $upload_path ) || ( $upload_path == $dir ) ) {
				$url = WP_CONTENT_URL . '/uploads';
			} else {
				$url = trailingslashit( $siteurl ) . $upload_path;
			}
		}

		/*
		 * Honor the value of UPLOADS. This happens as long as ms-files rewriting is disabled.
		 * We also sometimes obey UPLOADS when rewriting is enabled -- see the next block.
		 */
		if ( defined( 'UPLOADS' ) && ! ( is_multisite() && get_site_option( 'ms_files_rewriting' ) ) ) {
			$dir = ABSPATH . UPLOADS;
			$url = trailingslashit( $siteurl ) . UPLOADS;
		}

		// If multisite (and if not the main site in a post-MU network).
		if ( is_multisite() && ! ( is_main_network() && is_main_site() && defined( 'MULTISITE' ) ) ) {

			if ( get_site_option( 'ms_files_rewriting' ) && defined( 'UPLOADS' ) && ! ms_is_switched() ) {
				/*
				 * Handle the old-form ms-files.php rewriting if the network still has that enabled.
				 * When ms-files rewriting is enabled, then we only listen to UPLOADS when:
				 * 1) We are not on the main site in a post-MU network, as wp-content/uploads is used
				 *    there, and
				 * 2) We are not switched, as ms_upload_constants() hardcodes these constants to reflect
				 *    the original blog ID.
				 *
				 * Rather than UPLOADS, we actually use BLOGUPLOADDIR if it is set, as it is absolute.
				 * (And it will be set, see ms_upload_constants().) Otherwise, UPLOADS can be used, as
				 * as it is relative to ABSPATH. For the final piece: when UPLOADS is used with ms-files
				 * rewriting in multisite, the resulting URL is /files. (#WP22702 for background.)
				 */

				$dir = ABSPATH . untrailingslashit( UPLOADBLOGSDIR );
				$url = trailingslashit( $siteurl ) . 'files';
			}
		}

		$basedir = $dir;
		$baseurl = $url;

		return array(
			'basedir' => $basedir,
			'baseurl' => $baseurl,
		);
	}

  function ajax_filelist() {
		global $wpdb;
		// check caps
		if ( ! current_user_can( $this->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'cup_scan' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'cloud-uploads' ) );
		}

		$path = $this->get_original_upload_dir_root();
		$path = $path['basedir'];

		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );
		
		$filelist = new Cloud_Uploads_Filelist( $path, $this->ajax_timelimit );
		$filelist->start();
		$this_file_count = count( $filelist->file_list );
		$remaining_dirs  = $filelist->paths_left;
		$is_done         = $filelist->is_done;
		$nonce           = wp_create_nonce( 'cup_scan' );

		// sync filelist with cloud
		$break = false;
		while(!$break) {
			$is_done = false;
			$path1 = $this->get_original_upload_dir_root();
			$local_unsynced_files = $wpdb->get_results( $wpdb->prepare( "SELECT file, size, type FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 0 AND errors < 3 AND transfer_status IS NULL ORDER BY errors ASC, file ASC LIMIT %d", 50 ) );		
			if(sizeof($local_unsynced_files) !== 0) {
				for($i=0; $i<sizeof($local_unsynced_files); $i++) {
					$local_unsynced_files[$i]->url = $path1['baseurl'] . $local_unsynced_files[$i]->file;
					$local_unsynced_files[$i]->file = substr($local_unsynced_files[$i]->file, 1);
				}
				$files_synced = $this->api->call('files', $local_unsynced_files, 'POST');
				for($i=0; $i<sizeof($local_unsynced_files); $i++) {
					$local_unsynced_files[$i]->file = '/'.$local_unsynced_files[$i]->file;
					$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}cloud_uploads_files` SET transfer_status = 'tranferring' WHERE file = %s", $local_unsynced_files[$i]->file ) );
				}
			} else {
				$is_done = true;
				$break = true;
			}
		}
		// end sync filelist with cloud
		$data  = compact( 'this_file_count', 'is_done', 'remaining_dirs', 'nonce' );
		$stats = $this->get_sync_stats();
		if ( $stats ) {
			$data = array_merge( $data, $stats );
		}

		// Force the abortMultipartUpload pool to complete synchronously just in case it hasn't finished
		if ( isset( $promise ) ) {
			$promise->wait();
		}
		wp_send_json_success( $data );
  }

  function ajax_remote_filelist() {
		global $wpdb;

		if ( ! current_user_can( $this->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'cup_scan' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'cloud-uploads' ) );
		}
		//this loop has a parallel status check, so we make the timeout 2/3 of max execution time.
		$this->ajax_timelimit = max( 20, floor( ini_get( 'max_execution_time' ) * .6666 ) );
		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );

		try {
			$files = $this->api->get_site_cloud_files();
			wp_send_json_success( $files );
		}catch(error) {
			wp_send_json_error(esc_html__( 'Error while getting remote filelist.', 'cloud-uploads' ));
		}
  }
	function cloud_files_synced_filter($file)
  {
		if( $file->synced || $file->syncerror) {
			return true;
		} else {
			return false;
		}
  }

  function ajax_sync() {
		global $wpdb;
		$stats = $this->get_sync_stats();
		if ( ! current_user_can( $this->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'cup_sync' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'cloud-uploads' ) );
		}

		$progress = get_site_option( 'cup_files_scanned' );
		if ( ! $progress['sync_started'] ) {
			$progress['sync_started'] = time();
			update_site_option( 'cup_files_scanned', $progress );
		}

		//this loop has a parallel status check, so we make the timeout 2/3 of max execution time.
		$this->ajax_timelimit = max( 20, floor( ini_get( 'max_execution_time' ) * .6666 ) );
		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );
		
		try {
			$break = false;
			$is_done = false;
			$uploaded = 0;
			$errors = [];
			$path = $this->get_original_upload_dir_root();
			if($stats['synced_or_error'] == $stats['total_files']) {
				//$this->api->call('sync', [], 'GET');
			} else {
				$this->api->call('sync', [], 'GET');
			}
			while ( ! $break ) {
				$site_files_response = $this->api->call('file', [], 'GET');
				$remote_files = $site_files_response->cloud_files;
				$cloud_files_synced_or_error = array_filter($site_files_response->cloud_files,  [ &$this, 'cloud_files_synced_filter' ]);
				//$st = $stats['total_files'];
				//wp_send_json_success( array_merge( compact( 'st' ),[] ) );
				//$is_done = true;
				$uploaded = sizeof($cloud_files_synced_or_error);
				if(sizeof($cloud_files_synced_or_error) == $stats['total_files']) {
					$is_done = true;
				} else {
					for($i=0; $i<sizeof($remote_files); $i++) {
						$remote_files[$i]->name = '/'.$remote_files[$i]->name;
						if($remote_files[$i]->synced) {
							$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}cloud_uploads_files` SET synced = 1, transferred = %d WHERE file = %s", $remote_files[$i]->size, $remote_files[$i]->name ) );
						} else {
							$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}cloud_uploads_files` SET errors = (errors + 1) WHERE file = %s", $remote_files[$i]->name ) );
						}
					}
				}
				if ( $is_done || timer_stop() >= $this->ajax_timelimit ) {
					$break = true;
					$permanent_errors = false;
					if ( $is_done ) {
						$permanent_errors          = (int) $wpdb->get_var( "SELECT count(*) FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 0 AND errors >= 3" );
						$progress                  = get_site_option( 'cup_files_scanned' );
						$progress['sync_finished'] = time();
						update_site_option( 'cup_files_scanned', $progress );
					}
					$nonce = wp_create_nonce( 'cup_sync' );
				}
			}
			wp_send_json_success( array_merge( compact( 'uploaded', 'is_done', 'errors', 'permanent_errors', 'nonce' ), $this->get_sync_stats() ) );
			
			// if(sizeof($local_unsynced_files) !== 0) {
				
			// 	for($i=0; $i<sizeof($local_unsynced_files); $i++) {
			// 		$local_unsynced_files[$i]->url = $path['baseurl'] . $local_unsynced_files[$i]->file;
			// 		$local_unsynced_files[$i]->file = substr($local_unsynced_files[$i]->file, 1);
			// 	}
			// 	$files_synced = $this->api->call('files', $local_unsynced_files, 'POST');
			// 	for($i=0; $i<sizeof($files_synced); $i++) {
			// 		if($files_synced[$i]->status === 1) {
			// 			$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}cloud_uploads_files` SET synced = 1 WHERE file = %s", $files_synced[$i]->file ) );
			// 		} else {
			// 			$matching = $wpdb->get_results( $wpdb->prepare( "SELECT file, errors FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE file = %s ORDER BY errors ASC, file ASC LIMIT 1", $files_synced[$i]->file ) );
			// 			$errors = $matching[0]->errors + 1;
			// 			$wpdb->query( $wpdb->prepare( "UPDATE `{$wpdb->base_prefix}cloud_uploads_files` SET errors = %d WHERE file = %s", $errors, $files_synced[$i]->file ) );
			// 		}
			// 	}
			// 	$is_done = true;
			// } else {
			// 	$is_done = false;
			// 	$files_synced = $wpdb->get_results( $wpdb->prepare( "SELECT file, size, type FROM `{$wpdb->base_prefix}cloud_uploads_files` WHERE synced = 1 ORDER BY errors ASC, file ASC LIMIT %d", CLOUD_UPLOADS_SYNC_PER_LOOP ) );
			// }
		
		}catch(Exception $e) {
			wp_send_json_error(esc_html__( `Error while synching with cloud.`, 'cloud-uploads' ));
		}
  }

	function ajax_sync_part2() {

	}
  function ajax_sync_errors() {

  }

  function ajax_reset_errors() {

  }

  function ajax_delete() {

  }

  function ajax_download() {

  }

	/**
	 * Enable or disable url rewriting
	 */
	public function ajax_toggle() {
		if ( ! current_user_can( $this->capability ) || ! wp_verify_nonce( $_POST['nonce'], 'cup_toggle' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'cloud-uploads' ) );
		}

		$enabled = (bool) $_REQUEST['enabled'];
		$this->toggle_cloud( $enabled );

		wp_send_json_success();
	}

  function ajax_status() {

  }
}
 
