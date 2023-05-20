<?php

class S3_Uploads_Admin {
  private $api;
  private static $instance;
	private $capability;
	public $ajax_timelimit = 20;
	private $local_sync;

  public function __construct() {
    $this->api = new S3_Uploads_Api_Handler();
		$this->capability = apply_filters( 's3_uploads_settings_capability', ( is_multisite() ? 'manage_network_options' : 'manage_options' ) );
    add_action('admin_menu',  [ &$this, 'setup_menu' ]);
    if ( is_main_site() ) {
			add_action( 'wp_ajax_s3-uploads-filelist', [ &$this, 'ajax_filelist' ] );
			add_action( 'wp_ajax_s3-uploads-remote-filelist', [ &$this, 'ajax_remote_filelist' ] );
			add_action( 'wp_ajax_s3-uploads-sync', [ &$this, 'ajax_sync' ] );
			add_action( 'wp_ajax_s3-uploads-sync-errors', [ &$this, 'ajax_sync_errors' ] );
			add_action( 'wp_ajax_s3-uploads-reset-errors', [ &$this, 'ajax_reset_errors' ] );
			add_action( 'wp_ajax_s3-uploads-delete', [ &$this, 'ajax_delete' ] );
			add_action( 'wp_ajax_s3-uploads-download', [ &$this, 'ajax_download' ] );
			add_action( 'wp_ajax_s3-uploads-toggle', [ &$this, 'ajax_toggle' ] );
			add_action( 'wp_ajax_s3-uploads-status', [ &$this, 'ajax_status' ] );
		}
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

  /**
	 * Get the settings url with optional url args.
	 *
	 * @param array $args Optional. Same as for add_query_arg()
	 *
	 * @return string Unescaped url to settings page.
	 */
	function settings_url( $args = [] ) {
		if ( is_multisite() ) {
			$base = network_admin_url( 'admin.php?page=infinite_uploads' );
		} else {
			$base = admin_url( 'admin.php?page=infinite_uploads' );
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Get a url to the public Infinite Uploads site.
	 *
	 * @param string $path Optional path on the site.
	 *
	 * @return Infinite_Uploads_Api_Handler|string
	 */
	function api_url( $path = '' ) {
		$url = trailingslashit( $this->api->server_root );

		if ( $path && is_string( $path ) ) {
			$url .= ltrim( $path, '/' );
		}

		return $url;
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

    $data = [];
		$data['strings'] = [
			'leave_confirm'      => esc_html__( 'Are you sure you want to leave this tab? The current bulk action will be canceled and you will need to continue where it left off later.', 's3-uploads' ),
			'ajax_error'         => esc_html__( 'Too many server errors. Please try again.', 's3-uploads' ),
			'leave_confirmation' => esc_html__( 'If you leave this page the sync will be interrupted and you will have to continue where you left off later.', 's3-uploads' ),
		];

		$data['local_types'] = $this->get_filetypes( true );

		$api_data = $this->api->get_site_data();
		if ( $this->api->has_token() && $api_data ) {
			$data['cloud_types'] = $this->get_filetypes( true, $api_data->stats->site->types );
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


	public function get_filetypes( $is_chart = false, $cloud_types = false ) {
		global $wpdb;

		if ( false !== $cloud_types ) {
			if ( empty( $cloud_types ) ) { //estimate if sync was fresh
				$types = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE synced = 1 GROUP BY type ORDER BY size DESC" );
			} else {
				$types = $cloud_types;
			}
		} else {
			$types = $wpdb->get_results( "SELECT type, count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE deleted = 0 GROUP BY type ORDER BY size DESC" );
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
				$chart['labels'][]                         = $item->label . ": " . sprintf( _n( '%s file totalling %s', '%s files totalling %s', $item->files, 's3-uploads' ), number_format_i18n( $item->files ), size_format( $item->size, 1 ) );
			}

			$total_size     = array_sum( wp_list_pluck( $data, 'size' ) );
			$total_files    = array_sum( wp_list_pluck( $data, 'files' ) );
			$chart['total'] = sprintf( _n( '%s / %s File', '%s / %s Files', $total_files, 's3-uploads' ), size_format( $total_size, 2 ), number_format_i18n( $total_files ) );

			return $chart;
		}

		return $data;
	}

	public function get_file_type_format( $type, $key ) {
		$labels = [
			'image'    => [ 'color' => '#26A9E0', 'label' => esc_html__( 'Images', 's3-uploads' ) ],
			'audio'    => [ 'color' => '#00A167', 'label' => esc_html__( 'Audio', 's3-uploads' ) ],
			'video'    => [ 'color' => '#C035E2', 'label' => esc_html__( 'Video', 's3-uploads' ) ],
			'document' => [ 'color' => '#EE7C1E', 'label' => esc_html__( 'Documents', 's3-uploads' ) ],
			'archive'  => [ 'color' => '#EC008C', 'label' => esc_html__( 'Archives', 's3-uploads' ) ],
			'code'     => [ 'color' => '#EFED27', 'label' => esc_html__( 'Code', 's3-uploads' ) ],
			'other'    => [ 'color' => '#F1F1F1', 'label' => esc_html__( 'Other', 's3-uploads' ) ],
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
		wp_enqueue_style( 's3up-bootstrap', plugins_url( 'assets/bootstrap/css/bootstrap.min.css', __FILE__ ), false, S3_UPLOADS_VERSION );
		wp_enqueue_style( 's3up-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), [ 's3up-bootstrap' ], S3_UPLOADS_VERSION );
	}


  function settings_page() {
    global $wpdb;
    
    $region_labels = [
			'US' => esc_html__( 'United States', 's3-uploads' ),
			'EU' => esc_html__( 'Europe', 's3-uploads' ),
		];

		$stats    = $this->get_sync_stats();
		$api_data = $this->api->get_site_data();
    ?>
    <div id="s3up-settings-page" class="wrap s3up-background">
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
				?>
				<div id="s3up-error" class="alert alert-danger mt-1" role="alert"></div>
			<?php

				require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
				if ( ! empty( $stats['files_finished'] ) && $stats['files_finished'] >= ( time() - DAY_IN_SECONDS ) ) {
					require_once( dirname( __FILE__ ) . '/templates/local-file-overview.php' );
				} else {
					require_once( dirname( __FILE__ ) . '/templates/welcome.php' );
				}
				require_once( dirname( __FILE__ ) . '/templates/footer.php' );
				?>

				<?php
        // if ( $this->api->has_token() && $api_data ) {
        //   require_once( dirname( __FILE__ ) . '/templates/header-columns.php' );
        //   if ( ! infinite_uploads_enabled() ) {
        //     require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
        //   }
        // }else {
        //   if ( ! empty( $stats['files_finished'] ) && $stats['files_finished'] >= ( time() - DAY_IN_SECONDS ) ) {
        //     $to_sync = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE deleted = 0" );
        //     require_once( dirname( __FILE__ ) . '/templates/connect.php' );
        //   } else {
        //     //Make sure table is installed so we can show an error if not.

						
        //     require_once( dirname( __FILE__ ) . '/templates/welcome.php' );
						
        //   }
        //   require_once( dirname( __FILE__ ) . '/templates/modal-scan.php' );
        // }
        // require_once( dirname( __FILE__ ) . '/templates/footer.php' );
      ?>
      
    </div>
    <?php
  }

		/**
	 * Logs a debugging line.
	 */
	function sync_debug_log( $message ) {
		if ( defined( 'S3_UPLOADS_API_DEBUG' ) && S3_UPLOADS_API_DEBUG ) {
			$log = '[S3_UPLOADS Sync Debug] %s %s';

			$msg = sprintf(
				$log,
				S3_UPLOADS_VERSION,
				$message
			);
			error_log( $msg );
		}
	}

	public function get_sync_stats() {
		global $wpdb;

		$total     = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE 1" );
		$local     = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE deleted = 0" );
		$synced    = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE synced = 1" );
		$deletable = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE synced = 1 AND deleted = 0" );
		$deleted   = $wpdb->get_row( "SELECT count(*) AS files, SUM(`size`) as size, SUM(`transferred`) as transferred FROM `{$wpdb->base_prefix}s3_uploads_files` WHERE synced = 1 AND deleted = 1" );

		$progress = (array) get_site_option( 's3up_files_scanned' );

		return array_merge( $progress, [
			'is_data'         => (bool) $total->files,
			'total_files'     => number_format_i18n( (int) $total->files ),
			'total_size'      => size_format( (int) $total->size, 2 ),
			'local_files'     => number_format_i18n( (int) $local->files ),
			'local_size'      => size_format( (int) $local->size, 2 ),
			'cloud_files'     => number_format_i18n( (int) $synced->files ),
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
		if ( ! current_user_can( $this->capability ) || ! wp_verify_nonce( $_POST['nonce'], 's3up_scan' ) ) {
			wp_send_json_error( esc_html__( 'Permissions Error: Please refresh the page and try again.', 'infinite-uploads' ) );
		}

		$path = $this->get_original_upload_dir_root();
		$path = $path['basedir'];

		$this->sync_debug_log( "Ajax time limit: " . $this->ajax_timelimit );
		
		$filelist = new S3_Uploads_Filelist( $path, $this->ajax_timelimit );
		$filelist->start();
		$this_file_count = count( $filelist->file_list );
		$remaining_dirs  = $filelist->paths_left;
		$is_done         = $filelist->is_done;
		$nonce           = wp_create_nonce( 's3up_scan' );

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

		// $path = $this->iup_instance->get_original_upload_dir_root();
		// $path = $path['basedir'];

		// $remaining_dirs = [];
		// //validate path is within uploads dir to prevent path traversal
		// if ( isset( $_POST['remaining_dirs'] ) && is_array( $_POST['remaining_dirs'] ) ) {
		// 	foreach ( $_POST['remaining_dirs'] as $dir ) {
		// 		$realpath = realpath( $path . $dir );
		// 		if ( 0 === strpos( $realpath, $path ) ) { //check that parsed path begins with upload dir
		// 			$remaining_dirs[] = $dir;
		// 		}
		// 	}
		// } elseif ( ! empty( $this->iup_instance->bucket ) ) {
		// 	//If we are starting a new filesync and are logged into cloud storage abort any unfinished multipart uploads
		// 	$to_abort = $wpdb->get_results( "SELECT file, transfer_status as upload_id FROM `{$wpdb->base_prefix}infinite_uploads_files` WHERE transfer_status IS NOT NULL" );
		// 	if ( $to_abort ) {
		// 		$s3       = $this->iup_instance->s3();
		// 		$prefix   = $this->iup_instance->get_s3_prefix();
		// 		$bucket   = $this->iup_instance->get_s3_bucket();
		// 		$commands = [];
		// 		foreach ( $to_abort as $file ) {
		// 			$key = $prefix . $file->file;
		// 			// Abort the multipart upload.
		// 			$commands[] = $s3->getCommand( 'abortMultipartUpload', [
		// 				'Bucket'   => $bucket,
		// 				'Key'      => $key,
		// 				'UploadId' => $file->upload_id,
		// 			] );
		// 			$this->sync_debug_log( "Aborting multipart upload for {$file->file} UploadId {$file->upload_id}" );
		// 		}
		// 		// Create a command pool
		// 		$pool = new CommandPool( $s3, $commands );

		// 		// Begin asynchronous execution of the commands
		// 		$promise = $pool->promise();
		// 	}
		// }

		// $filelist = new Infinite_Uploads_Filelist( $path, $this->ajax_timelimit, $remaining_dirs );
		// $filelist->start();
		// $this_file_count = count( $filelist->file_list );
		// $remaining_dirs  = $filelist->paths_left;
		// $is_done         = $filelist->is_done;
		// $nonce           = wp_create_nonce( 'iup_scan' );

		// $data  = compact( 'this_file_count', 'is_done', 'remaining_dirs', 'nonce' );
		// $stats = $this->iup_instance->get_sync_stats();
		// if ( $stats ) {
		// 	$data = array_merge( $data, $stats );
		// }

		// // Force the abortMultipartUpload pool to complete synchronously just in case it hasn't finished
		// if ( isset( $promise ) ) {
		// 	$promise->wait();
		// }
  }

  function ajax_remote_filelist() {

  }

  function ajax_sync() {

  }

  function ajax_sync_errors() {

  }

  function ajax_reset_errors() {

  }

  function ajax_delete() {

  }

  function ajax_download() {

  }

  function ajax_toggle() {

  }

  function ajax_status() {

  }
}
 
