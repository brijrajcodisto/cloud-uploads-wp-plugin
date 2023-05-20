<?php
/**
 * API module.
 * Handles all functions that are executing remote calls.
 */

/**
 * The main API class.
 */
class S3_Uploads_Api_Handler {
  
	/**
	 * The API server.
	 *ś
	 * @var string (URL)
	 */
	public $server_root = 'https://s3uploads.com/';

	/**
	 * Path to the REST API on the server.
	 *
	 * @var string (URL)
	 */
	protected $rest_api = 'api/v1/';

	/**
	 * The complete REST API endpoint. Defined in constructor.
	 *
	 * @var string (URL)
	 */
	protected $server_url = '';

	/**
	 * Stores the API token used for authentication.
	 *
	 * @var string
	 */
	protected $api_token = '';

	/**
	 * Stores the site_id from the API.
	 *
	 * @var int
	 */
	protected $api_site_id = '';

	/**
	 * Holds the last API error that occured (if any)
	 *
	 * @var string
	 */
	public $api_error = '';

	/**
	 * Set up the API module.
	 *
	 * @internal
	 */
	public function __construct() {

		if ( defined( 'S3_UPLOADS_CUSTOM_API_SERVER' ) ) {
			$this->server_root = trailingslashit( S3_UPLOADS_CUSTOM_API_SERVER );
		}
		$this->server_url = $this->server_root . $this->rest_api;

		$this->api_token   = get_site_option( 's3up_apitoken' );
		$this->api_site_id = get_site_option( 's3up_site_id' );

		// Schedule automatic data update on the main site of the network.
		if ( is_main_site() ) {
			if ( ! wp_next_scheduled( 's3_uploads_sync' ) ) {
				wp_schedule_event( time(), 'daily', 's3_uploads_sync' );
			}

			add_action( 's3_uploads_sync', [ $this, 'get_site_data' ] );
			add_action( 'wp_ajax_nopriv_s3-uploads-refresh', [ &$this, 'remote_refresh' ] );
		}
	}

	/**
	 * Returns true if the API token is defined.
	 *
	 * @return bool
	 */
	public function has_token() {
		return ! empty( $this->api_token );
	}

	/**
	 * Returns the API token.
	 *
	 * @return string
	 */
	public function get_token() {
		return $this->api_token;
	}

	/**
	 * Updates the API token in the database.
	 *
	 * @param string $token The new API token to store.
	 */
	public function set_token( $token ) {
		$this->api_token = $token;
		update_site_option( 's3up_apitoken', $token );
	}

	/**
	 * Returns the site_id.
	 *
	 * @return int
	 */
	public function get_site_id() {
		return $this->api_site_id;
	}

	/**
	 * Updates the API site_id in the database.
	 *
	 * @param int $site_id The new site_id to store.
	 */
	public function set_site_id( $site_id ) {
		$this->api_site_id = $site_id;
		update_site_option( 's3up_site_id', $site_id );
	}

		/**
	 * Returns the canonical site_url that should be used for the site on the site.
	 *
	 * Define S3_UPLOADS_SITE_URL to override or make static the url it should show as
	 *  in the site. Defaults to network_site_url() which may be dynamically filtered
	 *  by some plugins and hosting providers.
	 *
	 * @return string
	 */
	public function network_site_url() {
		return defined( 'S3_UPLOADS_SITE_URL' ) ? S3_UPLOADS_SITE_URL : network_site_url();
	}

	
	/**
	 * Get site data from API, normally cached for 12hrs.
	 *
	 * @param bool $force_refresh
	 *
	 * @return mixed|void
	 */
	public function get_site_data( $force_refresh = false ) {

		if ( ! $this->has_token() || ! $this->get_site_id() ) {
			return false;
		}

		if ( ! $force_refresh ) {
			$data = get_site_option( 's3up_api_data' );
			if ( $data ) {
				$data = json_decode( $data );

				if ( $data->refreshed >= ( time() - HOUR_IN_SECONDS * 12 ) ) {
					return $data;
				}
			}
		}


		$result = $this->call( "site/" . $this->get_site_id(), [], 'GET' );
		if ( $result ) {
			$result->refreshed = time();
			//json_encode to prevent object injections
			update_site_option( 's3up_api_data', json_encode( $result ) );

			return $result;
		}

		return $data; //if a temp API issue default to using cached data
	}
  
}