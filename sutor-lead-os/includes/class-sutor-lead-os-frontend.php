<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sutor_Lead_OS_Frontend {
	/** @var bool */
	private static $shortcode_used = false;

	/** @var bool */
	private static $root_printed = false;

	/** @var bool */
	private static $script_localized = false;

	public static function init() {
		add_shortcode( 'sutor_lead_os', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'sutor_enquiry', array( __CLASS__, 'enquiry_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_root' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'maybe_render_root_late' ), 999 );
		add_filter( 'widget_text', array( __CLASS__, 'render_embedded_shortcodes' ) );
		add_filter( 'widget_text_content', array( __CLASS__, 'render_embedded_shortcodes' ) );
		add_filter( 'widget_custom_html_content', array( __CLASS__, 'render_embedded_shortcodes' ) );
	}

	public static function assets() {
		if ( ! self::should_load_early() ) { return; }
		self::enqueue_assets();
	}

	/**
	 * Legacy shortcode. With no button attributes, keeps the original behaviour:
	 * mark the page so the popup auto-opens. With label/button, render the enquiry button.
	 *
	 * @param array|string $atts
	 * @return string
	 */
	public static function shortcode( $atts = array() ) {
		$atts = self::parse_atts( $atts, 'sutor_lead_os' );
		$wants_button = '' !== trim( (string) $atts['label'] )
			|| in_array( strtolower( (string) $atts['button'] ), array( '1', 'true', 'yes' ), true );
		if ( $wants_button ) {
			if ( '' === (string) $atts['open'] ) {
				$atts['open'] = '0';
			}
			return self::render_enquiry_button( $atts );
		}

		$settings = Sutor_Lead_OS_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return '';
		}
		self::mark_shortcode_used();
		return '<div class="slos-shortcode-anchor" data-slos-open="1"></div>';
	}

	/**
	 * Button that opens the same enquiry popup as the floating widget.
	 *
	 * @param array|string $atts
	 * @return string
	 */
	public static function enquiry_shortcode( $atts = array() ) {
		$atts = self::parse_atts( $atts, 'sutor_enquiry' );
		return self::render_enquiry_button( $atts );
	}

	/**
	 * Run only this plugin's shortcodes inside text / HTML widgets.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function render_embedded_shortcodes( $content ) {
		if ( ! is_string( $content ) || ! self::text_has_shortcode( $content ) ) {
			return $content;
		}
		$regex = get_shortcode_regex( array( 'sutor_enquiry', 'sutor_lead_os' ) );
		$replaced = preg_replace_callback( '/' . $regex . '/', 'do_shortcode_tag', $content );
		return is_string( $replaced ) ? $replaced : $content;
	}

	public static function maybe_render_root() {
		if ( ! self::should_output_root() ) { return; }
		self::output_root();
	}

	/**
	 * Widgets and page-builder footers can render shortcodes after the first footer pass.
	 */
	public static function maybe_render_root_late() {
		if ( self::$root_printed || ! self::$shortcode_used ) { return; }
		self::output_root();
	}

	private static function should_load_early() {
		$settings = Sutor_Lead_OS_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		if ( ! empty( $settings['auto_show_fab'] ) ) { return true; }
		if ( self::query_has_shortcode() || self::widgets_have_shortcode() || self::builder_has_shortcode() ) {
			return true;
		}
		return (bool) apply_filters( 'sutor_lead_os_force_load', false );
	}

	private static function should_output_root() {
		$settings = Sutor_Lead_OS_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		if ( self::$shortcode_used || ! empty( $settings['auto_show_fab'] ) ) {
			return true;
		}
		if ( self::query_has_shortcode() || self::widgets_have_shortcode() || self::builder_has_shortcode() ) {
			return true;
		}
		return (bool) apply_filters( 'sutor_lead_os_force_load', false );
	}

	private static function mark_shortcode_used() {
		self::$shortcode_used = true;
		self::enqueue_assets();
		self::print_style_if_late();
	}

	private static function enqueue_assets() {
		$settings = Sutor_Lead_OS_Settings::get();
		if ( empty( $settings['enabled'] ) ) { return; }

		if ( ! wp_style_is( 'sutor-lead-os', 'registered' ) ) {
			wp_register_style( 'sutor-lead-os', SUTOR_LEAD_OS_URL . 'assets/css/popup.css', array(), SUTOR_LEAD_OS_VERSION );
		}
		if ( ! wp_script_is( 'sutor-lead-os', 'registered' ) ) {
			wp_register_script( 'sutor-lead-os', SUTOR_LEAD_OS_URL . 'assets/js/popup.js', array(), SUTOR_LEAD_OS_VERSION, true );
		}
		if ( ! self::$script_localized ) {
			wp_localize_script( 'sutor-lead-os', 'SutorLeadOS', Sutor_Lead_OS_Settings::public_config() );
			self::$script_localized = true;
		}

		// Style can print from the head. The script is printed immediately after the
		// popup root so it never runs before #sutor-lead-os-root exists.
		wp_enqueue_style( 'sutor-lead-os' );
	}

	/**
	 * Print the stylesheet from the footer when it was enqueued after wp_head.
	 * Shortcodes must not echo tags into post content.
	 */
	private static function print_style_if_late() {
		if ( wp_style_is( 'sutor-lead-os', 'done' ) ) { return; }
		if ( ! did_action( 'wp_footer' ) ) { return; }
		wp_print_styles( 'sutor-lead-os' );
	}

	private static function output_root() {
		if ( self::$root_printed ) { return; }
		$settings = Sutor_Lead_OS_Settings::get();
		if ( empty( $settings['enabled'] ) ) { return; }

		self::$root_printed = true;
		self::enqueue_assets();
		self::print_style_if_late();
		echo '<div id="sutor-lead-os-root" class="slos-root" aria-live="polite"></div>';
		if ( ! wp_script_is( 'sutor-lead-os', 'done' ) ) {
			wp_print_scripts( 'sutor-lead-os' );
		}
	}

	/**
	 * @param array|string $atts
	 * @param string       $tag
	 * @return array
	 */
	private static function parse_atts( $atts, $tag ) {
		if ( ! is_array( $atts ) ) {
			$atts = array();
		}
		return shortcode_atts(
			array(
				'label'  => '',
				'class'  => '',
				'align'  => 'left',
				'id'     => '',
				'open'   => '',
				'button' => '',
			),
			$atts,
			$tag
		);
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	private static function render_enquiry_button( $atts ) {
		$settings = Sutor_Lead_OS_Settings::get();
		if ( empty( $settings['enabled'] ) ) {
			return '';
		}
		self::mark_shortcode_used();

		$label = trim( (string) $atts['label'] );
		if ( '' === $label ) {
			$label = trim( (string) $settings['fab_label'] );
		}
		if ( '' === $label ) {
			$label = '免費咨詢';
		}

		$align = strtolower( (string) $atts['align'] );
		if ( 'centre' === $align ) {
			$align = 'center';
		}
		$align = sanitize_key( $align );
		if ( ! in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$align = 'left';
		}

		$classes = array( 'slos-enquiry-btn' );
		foreach ( preg_split( '/\s+/', (string) $atts['class'] ) as $class_name ) {
			$class_name = sanitize_html_class( $class_name );
			if ( '' !== $class_name ) {
				$classes[] = $class_name;
			}
		}

		$id = preg_replace( '/[^A-Za-z0-9_\-:]/', '', (string) $atts['id'] );
		$open = in_array( strtolower( (string) $atts['open'] ), array( '1', 'true', 'yes' ), true );

		$attrs = array(
			'type'              => 'button',
			'class'             => implode( ' ', $classes ),
			'data-slos-trigger' => '1',
			'aria-haspopup'     => 'dialog',
			'aria-controls'     => 'slos-modal',
			'aria-expanded'     => 'false',
		);
		if ( '' !== $id ) {
			$attrs['id'] = $id;
		}
		if ( $open ) {
			$attrs['data-slos-open'] = '1';
		}

		$html = '<div class="slos-enquiry-wrap slos-enquiry-align-' . esc_attr( $align ) . '">';
		$html .= '<button';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		$html .= '>';
		$html .= '<span class="slos-enquiry-icon" aria-hidden="true">&#128172;</span>';
		$html .= '<span class="slos-enquiry-label">' . esc_html( $label ) . '</span>';
		$html .= '</button></div>';
		return $html;
	}

	private static function text_has_shortcode( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return false;
		}
		if ( has_shortcode( $text, 'sutor_enquiry' ) || has_shortcode( $text, 'sutor_lead_os' ) ) {
			return true;
		}
		return false !== strpos( $text, '[sutor_enquiry' ) || false !== strpos( $text, '[sutor_lead_os' );
	}

	private static function query_has_shortcode() {
		global $post, $wp_query;
		$posts = array();
		if ( $post instanceof WP_Post ) {
			$posts[] = $post;
		}
		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			foreach ( $wp_query->posts as $candidate ) {
				$posts[] = $candidate;
			}
		}
		$seen = array();
		foreach ( $posts as $candidate ) {
			if ( ! $candidate instanceof WP_Post ) { continue; }
			if ( isset( $seen[ $candidate->ID ] ) ) { continue; }
			$seen[ $candidate->ID ] = true;
			if ( self::text_has_shortcode( $candidate->post_content ) ) {
				return true;
			}
		}
		return false;
	}

	private static function widgets_have_shortcode() {
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! is_array( $sidebars ) ) {
			return false;
		}
		$groups = array(
			'text'        => get_option( 'widget_text', array() ),
			'custom_html' => get_option( 'widget_custom_html', array() ),
			'block'       => get_option( 'widget_block', array() ),
		);
		foreach ( $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) { continue; }
			foreach ( $ids as $widget_id ) {
				if ( ! is_string( $widget_id ) || ! preg_match( '/^(text|custom_html|block)-(\d+)$/', $widget_id, $m ) ) {
					continue;
				}
				$group = isset( $groups[ $m[1] ] ) && is_array( $groups[ $m[1] ] ) ? $groups[ $m[1] ] : array();
				if ( empty( $group[ $m[2] ] ) || ! is_array( $group[ $m[2] ] ) ) { continue; }
				$instance = $group[ $m[2] ];
				$text = '';
				if ( isset( $instance['text'] ) ) { $text .= (string) $instance['text']; }
				if ( isset( $instance['content'] ) ) { $text .= (string) $instance['content']; }
				if ( self::text_has_shortcode( $text ) ) {
					return true;
				}
			}
		}
		return false;
	}

	private static function builder_has_shortcode() {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		$keys = array( '_elementor_data', '_fl_builder_data', 'panels_data' );
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $post->ID, $key, true );
			if ( is_string( $raw ) && self::text_has_shortcode( $raw ) ) {
				return true;
			}
			if ( is_array( $raw ) ) {
				$encoded = wp_json_encode( $raw );
				if ( is_string( $encoded ) && self::text_has_shortcode( $encoded ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
