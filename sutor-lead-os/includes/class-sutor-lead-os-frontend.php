<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sutor_Lead_OS_Frontend {
	public static function init() {
		add_shortcode( 'sutor_lead_os', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_root' ), 5 );
	}

	public static function assets() {
		$settings = Sutor_Lead_OS_Settings::get();
		if ( ! self::should_load( $settings ) ) { return; }
		wp_enqueue_style( 'sutor-lead-os', SUTOR_LEAD_OS_URL . 'assets/css/popup.css', array(), SUTOR_LEAD_OS_VERSION );
		wp_enqueue_script( 'sutor-lead-os', SUTOR_LEAD_OS_URL . 'assets/js/popup.js', array(), SUTOR_LEAD_OS_VERSION, true );
		wp_localize_script( 'sutor-lead-os', 'SutorLeadOS', Sutor_Lead_OS_Settings::public_config() );
	}

	private static function should_load( $settings ) {
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		if ( ! empty( $settings['auto_show_fab'] ) ) { return true; }
		global $post;
		if ( $post && has_shortcode( $post->post_content, 'sutor_lead_os' ) ) { return true; }
		return (bool) apply_filters( 'sutor_lead_os_force_load', false );
	}

	public static function shortcode( $atts = array() ) {
		return '<div class="slos-shortcode-anchor" data-slos-open="1"></div>';
	}

	public static function maybe_render_root() {
		$settings = Sutor_Lead_OS_Settings::get();
		if ( ! self::should_load( $settings ) ) { return; }
		echo '<div id="sutor-lead-os-root" class="slos-root" aria-live="polite"></div>';
	}
}
