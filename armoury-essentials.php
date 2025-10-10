<?php
/**
 * Plugin Name:       Armoury Essentials
 * Plugin URI:        https://www.armourymedia.com/
 * Description:       Essential optimizations for websites hosted by Armoury Media.
 * Version:           1.1.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Armoury Media
 * Author URI:        https://www.armourymedia.com/
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       armoury-essentials
 * Domain Path:       /languages
 *
 * @package ArmouryEssentials
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'AE_VERSION', '1.1.2' );
define( 'AE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Suppress specific translation loading notice for ffmailpoet plugin.
 * This prevents the notice from being written to debug.log.
 *
 * Remove when issue is fixed in Fluent Forms Connector for MailPoet plugin.
 * 
 * @since 1.1.2
 */
add_filter( 'doing_it_wrong_trigger_error', function( $trigger, $function_name, $message, $version ) {
	// Only suppress the specific ffmailpoet translation loading notice.
	if ( $function_name === '_load_textdomain_just_in_time' && 
	     strpos( $message, 'ffmailpoet' ) !== false ) {
		return false;
	}
	return $trigger;
}, 10, 4 );

/**
 * Initialize plugin update checker.
 *
 * @since 1.0.1
 */
function ae_init_plugin_updater() {
	if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		require_once AE_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
	}

	\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/armourymedia/armoury-essentials/',
		__FILE__,
		'armoury-essentials'
	)->setBranch( 'main' );
}
add_action( 'init', 'ae_init_plugin_updater' );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class Armoury_Essentials {

	/**
	 * Plugin instance.
	 *
	 * @var Armoury_Essentials
	 */
	private static $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array
	 */
	private $modules = array();

	/**
	 * Get plugin instance.
	 *
	 * @return Armoury_Essentials
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
		$this->init_modules();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		// Load core classes.
		require_once AE_PLUGIN_DIR . 'includes/class-ae-admin.php';
		require_once AE_PLUGIN_DIR . 'includes/class-ae-login.php';
		require_once AE_PLUGIN_DIR . 'includes/class-ae-media.php';
		
		// Load cache module only if SpinupWP is active.
		if ( $this->is_spinupwp_active() ) {
			require_once AE_PLUGIN_DIR . 'includes/class-ae-cache.php';
		}
	}

	/**
	 * Initialize core hooks.
	 */
	private function init_hooks() {
		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Activation/Deactivation hooks.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Core WordPress modifications.
		add_action( 'init', array( $this, 'add_page_excerpt_support' ) );
		
		// Only modify Action Scheduler if it's available.
		if ( class_exists( 'ActionScheduler' ) || class_exists( 'Action_Scheduler' ) ) {
			add_filter( 'action_scheduler_retention_period', array( $this, 'modify_action_scheduler_retention' ) );
		}
		
		// SlimSEO support.
		add_filter( 'slim_seo_linkedin_tags', '__return_true' );
		
		// Privacy enhancements.
		add_filter( 'embed_oembed_html', array( $this, 'youtube_privacy_enhanced' ), 10, 4 );
	}

	/**
	 * Initialize modules.
	 */
	private function init_modules() {
		// Admin module.
		$this->modules['admin'] = new AE_Admin();

		// Login module.
		$this->modules['login'] = new AE_Login();

		// Media module.
		$this->modules['media'] = new AE_Media();

		// Cache module (conditional).
		if ( $this->is_spinupwp_active() ) {
			$this->modules['cache'] = new AE_Cache();
		}
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'armoury-essentials',
			false,
			dirname( AE_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Check requirements.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( AE_PLUGIN_BASENAME );
			wp_die( esc_html__( 'Armoury Essentials requires PHP 7.4 or higher.', 'armoury-essentials' ) );
		}

		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			deactivate_plugins( AE_PLUGIN_BASENAME );
			wp_die( esc_html__( 'Armoury Essentials requires WordPress 6.0 or higher.', 'armoury-essentials' ) );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clean up if needed.
		flush_rewrite_rules();
	}

	/**
	 * Add excerpt support to pages.
	 */
	public function add_page_excerpt_support() {
		add_post_type_support( 'page', 'excerpt' );
	}

	/**
	 * Modify Action Scheduler retention period to 1 day.
	 *
	 * @param int $seconds Default retention period.
	 * @return int Modified retention period.
	 */
	public function modify_action_scheduler_retention( $seconds ) {
		return DAY_IN_SECONDS;
	}

	/**
	 * Replace YouTube domain with nocookie for privacy.
	 *
	 * @param string $html    The HTML output.
	 * @param string $url     The original URL.
	 * @param array  $attr    The attributes.
	 * @param int    $post_id The post ID.
	 * @return string Modified HTML.
	 */
	public function youtube_privacy_enhanced( $html, $url, $attr, $post_id ) {
		if ( strpos( $url, 'youtube.com' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
			return str_replace( 'youtube.com', 'youtube-nocookie.com', $html );
		}
		return $html;
	}

	/**
	 * Check if SpinupWP is active.
	 *
	 * @return bool
	 */
	private function is_spinupwp_active() {
		return class_exists( 'SpinupWp\Plugin' ) || defined( 'SPINUPWP_PLUGIN_VERSION' );
	}
}

// Initialize plugin.
add_action( 'plugins_loaded', array( 'Armoury_Essentials', 'get_instance' ) );
