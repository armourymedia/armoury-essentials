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
	 * Get primary color for login branding.
	 *
	 * @return string
	 */
	private function get_primary_color() {
		$default = '#1a7e60';
		$color   = $default;

		if ( defined( 'AE_BRAND_COLOR' ) && $this->is_valid_color( AE_BRAND_COLOR ) ) {
			$color = AE_BRAND_COLOR;
		}

		$color = apply_filters( 'ae_brand_color', $color );

		if ( ! $this->is_valid_color( $color ) ) {
			$color = $default;
		}

		return $color;
	}

	/**
	 * Validate hex color value.
	 *
	 * @param mixed $color Color value to validate.
	 * @return bool
	 */
	private function is_valid_color( $color ) {
		if ( ! is_string( $color ) ) {
			return false;
		}

		return (bool) preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', trim( $color ) );
	}
}
