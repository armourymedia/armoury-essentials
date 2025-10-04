<?php
/**
 * Login page customizations module.
 *
 * @package ArmouryEssentials
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login customizations class.
 */
class AE_Login {

	/**
	 * Cached primary color.
	 *
	 * @var string|null
	 */
	private $primary_color_cache = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_styles' ) );
		add_action( 'login_head', array( $this, 'add_dynamic_colors' ) );
	}

	/**
	 * Enqueue login styles.
	 */
	public function enqueue_login_styles() {
		wp_enqueue_style(
			'ae-login',
			AE_PLUGIN_URL . 'assets/css/login.css',
			array(),
			AE_VERSION
		);
	}

	/**
	 * Add dynamic colors based on site settings.
	 */
	public function add_dynamic_colors() {
		$primary_color = $this->get_primary_color();
		
		if ( $primary_color ) {
			?>
			<style id="ae-login-dynamic-colors">
				:root {
					--ae-brand-color: <?php echo esc_attr( $primary_color ); ?>;
				}
			</style>
			<?php
		}
	}

	/**
	 * Get primary color from various sources.
	 *
	 * @return string|false
	 */
	private function get_primary_color() {
		// Return cached value if available.
		if ( null !== $this->primary_color_cache ) {
			return $this->primary_color_cache;
		}
		
		// First, check if there's a defined constant.
		if ( defined( 'AE_BRAND_COLOR' ) ) {
			$color = AE_BRAND_COLOR;
			if ( $this->is_valid_color( $color ) ) {
				$this->primary_color_cache = $color;
				return $color;
			}
		}

		// Check theme mods for FSE themes.
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$global_settings = wp_get_global_settings();
			
			// Try to get primary color from theme.json settings.
			if ( isset( $global_settings['color']['palette'] ) ) {
				foreach ( $global_settings['color']['palette'] as $color ) {
					// Look for primary color in theme palette.
					if ( isset( $color['slug'] ) && strpos( $color['slug'], 'primary' ) !== false && isset( $color['color'] ) ) {
						if ( $this->is_valid_color( $color['color'] ) ) {
							$this->primary_color_cache = $color['color'];
							return $color['color'];
						}
					}
				}
				
				// Fallback to first custom color if no primary found.
				if ( ! empty( $global_settings['color']['palette'][0]['color'] ) ) {
					$first_color = $global_settings['color']['palette'][0]['color'];
					if ( $this->is_valid_color( $first_color ) ) {
						$this->primary_color_cache = $first_color;
						return $first_color;
					}
				}
			}
		}

		// Check customizer settings for classic themes.
		$customizer_color = get_theme_mod( 'primary_color' );
		if ( $customizer_color && $this->is_valid_color( $customizer_color ) ) {
			$this->primary_color_cache = $customizer_color;
			return $customizer_color;
		}

		// Default fallback color.
		$this->primary_color_cache = '#1a7e60';
		return '#1a7e60';
	}

	/**
	 * Validate color value.
	 *
	 * @param string $color Color value to validate.
	 * @return bool
	 */
	private function is_valid_color( $color ) {
		// Sanitize input.
		$color = trim( $color );
		
		// Check for hex color (3 or 6 digits).
		if ( preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $color ) ) {
			return true;
		}
		
		// Check for RGB/RGBA with proper validation.
		if ( preg_match( '/^rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*(?:,\s*(0|1|0?\.\d+))?\s*\)$/i', $color, $matches ) ) {
			// Validate RGB values are within range (0-255).
			if ( $matches[1] <= 255 && $matches[2] <= 255 && $matches[3] <= 255 ) {
				return true;
			}
		}
		
		// Check for CSS color keywords (limited set for security).
		$allowed_keywords = array( 'inherit', 'transparent', 'currentColor' );
		if ( in_array( $color, $allowed_keywords, true ) ) {
			return true;
		}
		
		return false;
	}
}
