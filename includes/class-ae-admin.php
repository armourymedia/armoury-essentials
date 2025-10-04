<?php
/**
 * Admin customizations module.
 *
 * @package ArmouryEssentials
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin customizations class.
 */
class AE_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'remove_color_scheme_picker' ) );
		add_filter( 'enable_post_by_email_configuration', '__return_false', PHP_INT_MAX );
	}

	/**
	 * Remove the admin color scheme picker.
	 */
	public function remove_color_scheme_picker() {
		remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
	}
}
