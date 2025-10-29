<?php
/**
 * Media embeds module.
 *
 * @package ArmouryEssentials
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Media embeds class.
 */
class AE_Media {

	/**
	 * Video providers configuration.
	 *
	 * @var array
	 */
	private $providers = array();

	/**
	 * Compiled regex pattern for video links.
	 *
	 * @var string|null
	 */
	private $video_pattern_cache = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->setup_providers();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_media_assets' ) );
	}

	/**
	 * Setup video providers.
	 */
	private function setup_providers() {
		$this->providers = array(
			'youtube' => array(
				'pattern' => 'youtube.com/watch|youtu.be/|youtube.com/shorts/',
				'embed'   => 'youtube',
				'allowed_hosts' => array( 'www.youtube.com', 'youtube.com', 'youtu.be', 'www.youtube-nocookie.com', 'youtube-nocookie.com' ),
			),
			'vimeo' => array(
				'pattern' => 'vimeo.com/',
				'embed'   => 'vimeo',
				'allowed_hosts' => array( 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com' ),
			),
			'bunny' => array(
				'pattern' => 'iframe.mediadelivery.net/play|player.mediadelivery.net/embed',
				'embed'   => array( '/play/', '/embed/' ),
				'allowed_hosts' => array( 'iframe.mediadelivery.net', 'player.mediadelivery.net' ),
			),
			'cloudflare' => array(
				'pattern' => 'cloudflarestream.com',
				'embed'   => array( '/watch', '/iframe?autoplay=true' ),
				'allowed_hosts' => array( 'cloudflarestream.com', 'customer.cloudflarestream.com' ),
			),
		);

		// Allow filtering of providers.
		$this->providers = apply_filters( 'ae_video_providers', $this->providers );
		
		// Validate provider structure after filtering.
		$this->validate_providers();
	}

	/**
	 * Validate provider structure and remove invalid entries.
	 */
	private function validate_providers() {
		foreach ( $this->providers as $key => $provider ) {
			// Check required fields exist.
			if ( ! isset( $provider['pattern'] ) || ! isset( $provider['embed'] ) || ! isset( $provider['allowed_hosts'] ) ) {
				unset( $this->providers[ $key ] );
				continue;
			}
			
			// Validate pattern is a string.
			if ( ! is_string( $provider['pattern'] ) ) {
				unset( $this->providers[ $key ] );
				continue;
			}
			
			// Validate embed is either string or array.
			if ( ! is_string( $provider['embed'] ) && ! is_array( $provider['embed'] ) ) {
				unset( $this->providers[ $key ] );
				continue;
			}
			
			// Validate allowed_hosts is an array.
			if ( ! is_array( $provider['allowed_hosts'] ) || empty( $provider['allowed_hosts'] ) ) {
				unset( $this->providers[ $key ] );
				continue;
			}
		}
	}

	/**
	 * Enqueue media embed assets conditionally.
	 */
	public function enqueue_media_assets() {
		// Only on singular pages.
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		// Check if content has video links.
		if ( ! $this->content_has_video_links( $post->post_content ) ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'ae-media-embed',
			AE_PLUGIN_URL . 'assets/css/media-embed.css',
			array(),
			AE_VERSION
		);

		// Enqueue script.
		wp_enqueue_script(
			'ae-media-embed',
			AE_PLUGIN_URL . 'assets/js/media-embed.js',
			array(),
			AE_VERSION,
			true
		);

		// Localize script.
		wp_localize_script( 'ae-media-embed', 'aeMediaConfig', array(
			'providers' => $this->providers,
			'i18n' => array(
				'playVideo'   => esc_attr__( 'Play video', 'armoury-essentials' ),
				'videoPlayer' => esc_attr__( 'Video player', 'armoury-essentials' ),
				'loadError'   => esc_attr__( 'Video could not be loaded', 'armoury-essentials' ),
			),
		) );
	}

	/**
	 * Check if content has video links.
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private function content_has_video_links( $content ) {
		// Return cached pattern if available.
		if ( null === $this->video_pattern_cache ) {
			$this->build_video_pattern_cache();
		}
		
		if ( empty( $this->video_pattern_cache ) ) {
			return false;
		}
		
		// Check content with cached pattern.
		return preg_match( $this->video_pattern_cache, $content ) === 1;
	}
	
	/**
	 * Build and cache the regex pattern for video links.
	 */
	private function build_video_pattern_cache() {
		$patterns = array();
		foreach ( $this->providers as $provider ) {
			$provider_patterns = explode( '|', $provider['pattern'] );
			foreach ( $provider_patterns as $pattern ) {
				$patterns[] = preg_quote( $pattern, '/' );
			}
		}
		
		if ( ! empty( $patterns ) ) {
			$this->video_pattern_cache = '/' . implode( '|', $patterns ) . '/i';
		}
	}
}
