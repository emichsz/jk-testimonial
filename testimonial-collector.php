<?php
/**
 * Plugin Name:       Testimonial Collector
 * Plugin URI:        https://github.com/emichsz/jk-testimonial
 * Description:       Szöveges és videós testimonialok gyűjtése böngészős videófelvétellel, jóváhagyási folyamattal és lapozható megjelenítéssel. Shortcode-ok: [testimonial_form], [testimonial_wall]
 * Version:           1.0.1
 * Author:            Emich
 * Text Domain:       testimonial-collector
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TC_VERSION', '1.0.1' );
define( 'TC_PLUGIN_FILE', __FILE__ );
define( 'TC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TC_CPT', 'tc_testimonial' );
define( 'TC_OPTION', 'tc_settings' );

require_once TC_PLUGIN_DIR . 'includes/class-tc-strings.php';
require_once TC_PLUGIN_DIR . 'includes/class-tc-cpt.php';
require_once TC_PLUGIN_DIR . 'includes/class-tc-settings.php';
require_once TC_PLUGIN_DIR . 'includes/class-tc-admin.php';
require_once TC_PLUGIN_DIR . 'includes/class-tc-shortcodes.php';
require_once TC_PLUGIN_DIR . 'includes/class-tc-ajax.php';
require_once TC_PLUGIN_DIR . 'includes/class-tc-updater.php';

/**
 * Default settings.
 */
function tc_default_settings() {
	return array(
		'language'          => 'auto',   // auto | hu | en
		'collection_type'   => 'both',   // both | text | video
		'collect_rating'    => 1,
		'theme'             => 'light',  // light | dark
		'show_role'         => 1,
		'show_social'       => 0,
		'show_photo'        => 1,
		'logo_id'           => 0,
		'thankyou_image_id' => 0,
		'thankyou_show_image' => 1,
		'color_primary'     => '#2a7f92', // card / button color
		'color_accent'      => '#f0b429', // stars
		'color_bg'          => '#e2f2f8', // wall background
		'color_text_on_primary' => '#ffffff',
		'per_page'          => 6,
		'max_chars'         => 0,          // 0 = no limit
		'consent_mode'      => 'required', // required | optional | hidden
		'form_show_wall'    => 0,
		'show_title'        => 0,
		'verify_email'      => 0,
		'ios_no_record'     => 0,
		'events'            => '', // one per line; empty = no dropdown on the form
		'video_max_seconds' => 120,
		'video_max_mb'      => 200,
		'notify_email'      => get_option( 'admin_email' ),
		// Text overrides: {key}_hu / {key}_en (empty = built-in default)
	);
}

/**
 * Get merged settings.
 */
function tc_get_settings() {
	$saved = get_option( TC_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( tc_default_settings(), $saved );
}

/**
 * Resolve active display language (hu|en).
 */
function tc_get_language() {
	$settings = tc_get_settings();
	$lang     = $settings['language'];
	if ( 'auto' === $lang ) {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		return ( 0 === strpos( $locale, 'hu' ) ) ? 'hu' : 'en';
	}
	return in_array( $lang, array( 'hu', 'en' ), true ) ? $lang : 'en';
}

/**
 * Bootstrap.
 */
function tc_init_plugin() {
	load_plugin_textdomain( 'testimonial-collector', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	TC_CPT::init();
	TC_Settings::init();
	TC_Admin::init();
	TC_Shortcodes::init();
	TC_Ajax::init();
	TC_Updater::init();
}
add_action( 'plugins_loaded', 'tc_init_plugin' );

/**
 * Activation: register CPT and flush rewrite rules.
 */
function tc_activate() {
	TC_CPT::register();
	flush_rewrite_rules();
	if ( false === get_option( TC_OPTION, false ) ) {
		add_option( TC_OPTION, array() );
	}
}
register_activation_hook( __FILE__, 'tc_activate' );

/**
 * Deactivation: flush rewrite rules.
 */
function tc_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tc_deactivate' );

/**
 * Allow webm uploads for testimonial videos (many WP installs lack it).
 */
function tc_upload_mimes( $mimes ) {
	$mimes['webm'] = 'video/webm';
	return $mimes;
}
add_filter( 'upload_mimes', 'tc_upload_mimes' );
