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
	 * Constructor.
	 */
	public function __construct() {
		// Only initialize if requirements are met.
		if ( ! $this->check_requirements() ) {
			add_action( 'admin_notices', array( $this, 'show_requirements_notice' ) );
			return;
		}

		// Hook into SpinupWP cache purge.
		add_action( 'spinupwp_site_purged', array( $this, 'handle_site_purged' ), 10, 1 );
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
	 * Handle SpinupWP cache purge.
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
			$error_message = isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : 'HTTP ' . $response_code;
			$this->log_error( 'Cloudflare API returned error: ' . $error_message );
			return false;
		}
		
		if ( empty( $data['success'] ) ) {
			$error_message = isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : 'Unknown error';
			$this->log_error( 'Cloudflare purge failed: ' . $error_message );
			return false;
		}
		
		return true;
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
