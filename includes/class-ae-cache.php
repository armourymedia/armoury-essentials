<?php
/**
 * Cache management module.
 *
 * @package ArmouryEssentials
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache management class.
 */
class AE_Cache {

	/**
	 * Cloudflare API endpoint.
	 *
	 * @var string
	 */
	private $cf_api_endpoint = 'https://api.cloudflare.com/client/v4/zones/';

	/**
	 * Track URLs being purged to prevent duplicate API calls.
	 *
	 * @var array
	 */
	private $purging_urls = array();

	/**
	 * Whether APO mode is enabled.
	 *
	 * @var bool
	 */
	private $apo_enabled = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Check basic requirements.
		if ( ! $this->check_requirements() ) {
			add_action( 'admin_notices', array( $this, 'show_requirements_notice' ) );
			return;
		}

		// Check if APO mode is enabled.
		$this->apo_enabled = defined( 'ARMOURY_CF_APO_ENABLED' ) && ARMOURY_CF_APO_ENABLED;

		// Hook into SpinupWP cache purge - always enabled for full site purges.
		add_action( 'spinupwp_site_purged', array( $this, 'handle_site_purged' ), 10, 1 );

		// Hook into granular purges only if APO is enabled.
		if ( $this->apo_enabled ) {
			add_action( 'spinupwp_page_cache_purged', array( $this, 'handle_url_purged' ), 10, 2 );
			add_action( 'spinupwp_object_cache_purged', array( $this, 'handle_url_purged' ), 10, 2 );
			add_action( 'spinupwp_purged_post', array( $this, 'handle_post_purged' ), 10, 3 );
		}
	}

	/**
	 * Check if required constants are defined.
	 *
	 * @return bool
	 */
	private function check_requirements() {
		if ( ! defined( 'ARMOURY_CF_ZONE_ID' ) || ! defined( 'ARMOURY_CF_API_TOKEN' ) ) {
			return false;
		}
		
		// Validate zone ID format (basic check).
		$zone_id = ARMOURY_CF_ZONE_ID;
		if ( empty( $zone_id ) || ! preg_match( '/^[a-f0-9]{32}$/i', $zone_id ) ) {
			return false;
		}
		
		// Check token is not empty.
		$token = ARMOURY_CF_API_TOKEN;
		if ( empty( $token ) ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Show admin notice if requirements are not met.
	 */
	public function show_requirements_notice() {
		// Only show to admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible">
			<p><?php esc_html_e( 'Armoury Essentials: To enable Cloudflare cache synchronization, define ARMOURY_CF_ZONE_ID and ARMOURY_CF_API_TOKEN in wp-config.php', 'armoury-essentials' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Handle SpinupWP full site cache purge.
	 *
	 * @param bool $result The result of the SpinupWP cache purge.
	 */
	public function handle_site_purged( $result ) {
		// Only proceed if SpinupWP purge was successful.
		if ( ! $result ) {
			return;
		}

		// Purge Cloudflare cache.
		$cf_result = $this->purge_cloudflare_cache();
		
		if ( $cf_result ) {
			$this->log_info( 'Successfully purged Cloudflare cache after SpinupWP purge' );
		} else {
			$this->log_error( 'Failed to purge Cloudflare cache after SpinupWP purge' );
		}
	}

	/**
	 * Handle individual URL purges from SpinupWP (APO mode).
	 *
	 * @param string $url    URL that was purged.
	 * @param bool   $result Result of SpinupWP purge.
	 */
	public function handle_url_purged( $url, $result ) {
		// Only proceed if APO is enabled and SpinupWP purge was successful.
		if ( ! $this->apo_enabled || ! $result || empty( $url ) ) {
			return;
		}

		// Avoid duplicate purges in same request.
		if ( in_array( $url, $this->purging_urls, true ) ) {
			return;
		}

		$this->purging_urls[] = $url;

		// Purge single URL.
		$cf_result = $this->purge_cloudflare_urls( array( $url ) );

		if ( $cf_result ) {
			$this->log_info( 'Purged URL from Cloudflare APO: ' . esc_url( $url ) );
		} else {
			$this->log_error( 'Failed to purge URL from Cloudflare APO: ' . esc_url( $url ) );
		}
	}

	/**
	 * Handle individual post purges from SpinupWP (APO mode).
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $urls    URLs to purge.
	 * @param string $type    Type of purge.
	 */
	public function handle_post_purged( $post_id, $urls, $type ) {
		// Only proceed if APO is enabled.
		if ( ! $this->apo_enabled || empty( $urls ) ) {
			return;
		}

		// Filter out already-purged URLs.
		$new_urls = array_diff( $urls, $this->purging_urls );
		if ( empty( $new_urls ) ) {
			return;
		}

		// Track these URLs as being purged.
		$this->purging_urls = array_merge( $this->purging_urls, $new_urls );

		// Purge specific URLs at Cloudflare.
		$result = $this->purge_cloudflare_urls( $new_urls );

		if ( $result ) {
			$this->log_info( sprintf( 'Purged %d URLs from Cloudflare APO for post %d', count( $new_urls ), $post_id ) );
		} else {
			$this->log_error( sprintf( 'Failed to purge URLs from Cloudflare APO for post %d', $post_id ) );
		}
	}

	/**
	 * Purge all Cloudflare cache.
	 *
	 * @return bool
	 */
	private function purge_cloudflare_cache() {
		// Validate zone ID format before making request.
		$zone_id = ARMOURY_CF_ZONE_ID;
		if ( ! preg_match( '/^[a-f0-9]{32}$/i', $zone_id ) ) {
			$this->log_error( 'Invalid Cloudflare Zone ID format' );
			return false;
		}
		
		$api_url = $this->cf_api_endpoint . $zone_id . '/purge_cache';
		
		$response = wp_remote_post( $api_url, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . ARMOURY_CF_API_TOKEN,
				'Content-Type'  => 'application/json',
			),
			'body' => wp_json_encode( array( 'purge_everything' => true ) ),
			'timeout' => 30,
		) );
		
		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Cloudflare API error: ' . $response->get_error_message() );
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		// Check for HTTP errors.
		if ( $response_code !== 200 ) {
			$error_message = isset( $data['errors'][0]['message'] ) 
				? $data['errors'][0]['message'] 
				: 'HTTP ' . $response_code;
			$this->log_error( 'Cloudflare API returned error: ' . $error_message );
			return false;
		}
		
		if ( empty( $data['success'] ) ) {
			$error_message = isset( $data['errors'][0]['message'] ) 
				? $data['errors'][0]['message'] 
				: 'Unknown error';
			$this->log_error( 'Cloudflare purge failed: ' . $error_message );
			return false;
		}
		
		return true;
	}

	/**
	 * Purge specific URLs from Cloudflare cache (APO mode).
	 *
	 * @param array $urls Array of URLs to purge.
	 * @return bool
	 */
	private function purge_cloudflare_urls( $urls ) {
		// Validate zone ID.
		$zone_id = ARMOURY_CF_ZONE_ID;
		if ( ! preg_match( '/^[a-f0-9]{32}$/i', $zone_id ) ) {
			$this->log_error( 'Invalid Cloudflare Zone ID format' );
			return false;
		}

		// Ensure URLs are unique and fully qualified.
		$urls = array_unique( $urls );
		$full_urls = array_map( array( $this, 'ensure_full_url' ), $urls );

		// Cloudflare API accepts max 30 URLs per request.
		$chunks = array_chunk( $full_urls, 30 );
		$all_success = true;

		foreach ( $chunks as $url_batch ) {
			$api_url = $this->cf_api_endpoint . $zone_id . '/purge_cache';

			$response = wp_remote_post( $api_url, array(
				'headers' => array(
					'Authorization' => 'Bearer ' . ARMOURY_CF_API_TOKEN,
					'Content-Type'  => 'application/json',
				),
				'body' => wp_json_encode( array( 
					'files' => $url_batch,
				) ),
				'timeout' => 30,
			) );

			if ( is_wp_error( $response ) ) {
				$this->log_error( 'Cloudflare API error: ' . $response->get_error_message() );
				$all_success = false;
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( $response_code !== 200 || empty( $data['success'] ) ) {
				$error_message = isset( $data['errors'][0]['message'] ) 
					? $data['errors'][0]['message'] 
					: 'HTTP ' . $response_code;
				$this->log_error( 'Cloudflare URL purge failed: ' . $error_message );
				$all_success = false;
			}
		}

		return $all_success;
	}

	/**
	 * Ensure URL is fully qualified with scheme and domain.
	 *
	 * @param string $url URL to process.
	 * @return string Full URL.
	 */
	private function ensure_full_url( $url ) {
		// If already full URL, return as-is.
		if ( preg_match( '/^https?:\/\//', $url ) ) {
			return $url;
		}

		// Build full URL from home URL.
		return home_url( $url );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'AE Cache Error: ' . $message );
		}
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Info message.
	 */
	private function log_info( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'AE Cache Info: ' . $message );
		}
	}
}
