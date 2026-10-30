<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sutor_Lead_OS_Settings {

	public static function init() {
		// Reserved for future hooks.
	}

	/**
	 * @return array
	 */
	public static function get() {
		$stored = get_option( SUTOR_LEAD_OS_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, Sutor_Lead_OS::default_settings() );
	}

	/**
	 * Sanitize and save full settings payload from admin.
	 *
	 * @param array $raw
	 * @return array|WP_Error
	 */
	public static function save( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'invalid', 'Invalid settings payload.' );
		}

		$clean = array();
		$clean['enabled']           = empty( $raw['enabled'] ) ? 0 : 1;
		$clean['client_id']         = sanitize_text_field( $raw['client_id'] ?? '' );
		$clean['form_id']           = sanitize_text_field( $raw['form_id'] ?? '' );
		$clean['popup_title']       = sanitize_text_field( $raw['popup_title'] ?? '' );
		$clean['popup_subtitle']    = sanitize_textarea_field( $raw['popup_subtitle'] ?? '' );
		$clean['fab_label']         = sanitize_text_field( $raw['fab_label'] ?? '' );
		$clean['cta_label']         = sanitize_text_field( $raw['cta_label'] ?? '' );
		$clean['close_label']       = sanitize_text_field( $raw['close_label'] ?? '' );
		$clean['auto_show_fab']     = empty( $raw['auto_show_fab'] ) ? 0 : 1;
		$clean['whatsapp_number']   = preg_replace( '/[^0-9]/', '', (string) ( $raw['whatsapp_number'] ?? '' ) );
		$clean['whatsapp_template'] = sanitize_textarea_field( $raw['whatsapp_template'] ?? '' );
		$clean['enable_gtm']        = empty( $raw['enable_gtm'] ) ? 0 : 1;
		$clean['supabase_url']      = esc_url_raw( $raw['supabase_url'] ?? '' );
		$clean['supabase_anon_key'] = sanitize_text_field( $raw['supabase_anon_key'] ?? '' );

		$clean['questions'] = self::sanitize_questions( $raw['questions'] ?? array() );
		$clean['qualification_rules'] = self::sanitize_rules( $raw['qualification_rules'] ?? array() );

		update_option( SUTOR_LEAD_OS_OPTION, $clean, false );
		return $clean;
	}

	private static function sanitize_questions( $questions ) {
		if ( ! is_array( $questions ) ) {
			return array();
		}
		$allowed_types = array( 'single', 'multi', 'text', 'phone' );
		$out = array();
		foreach ( $questions as $q ) {
			if ( ! is_array( $q ) ) {
				continue;
			}
			$id = sanitize_key( $q['id'] ?? '' );
			if ( '' === $id ) {
				$id = 'q_' . wp_generate_password( 8, false, false );
			}
			$type = sanitize_key( $q['type'] ?? 'text' );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'text';
			}
			$options = array();
			if ( in_array( $type, array( 'single', 'multi' ), true ) && ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
				foreach ( $q['options'] as $opt ) {
					if ( ! is_array( $opt ) ) {
						continue;
					}
					$oid = sanitize_key( $opt['id'] ?? '' );
					if ( '' === $oid ) {
						$oid = 'o_' . wp_generate_password( 6, false, false );
					}
					$options[] = array(
						'id'    => $oid,
						'label' => sanitize_text_field( $opt['label'] ?? '' ),
					);
				}
			}
			$out[] = array(
				'id'       => $id,
				'label'    => sanitize_text_field( $q['label'] ?? '' ),
				'type'     => $type,
				'required' => empty( $q['required'] ) ? 0 : 1,
				'options'  => $options,
			);
		}
		return $out;
	}

	private static function sanitize_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array();
		}
		$allowed_ops = array( 'eq', 'in', 'intersects' );
		$out = array();
		foreach ( $rules as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$id = sanitize_key( $r['id'] ?? '' );
			if ( '' === $id ) {
				$id = 'rule_' . wp_generate_password( 6, false, false );
			}
			$op = sanitize_key( $r['operator'] ?? 'in' );
			if ( ! in_array( $op, $allowed_ops, true ) ) {
				$op = 'in';
			}
			$answer_ids = array();
			if ( ! empty( $r['answer_ids'] ) && is_array( $r['answer_ids'] ) ) {
				foreach ( $r['answer_ids'] as $aid ) {
					$answer_ids[] = sanitize_key( $aid );
				}
			}
			$out[] = array(
				'id'           => $id,
				'question_id'  => sanitize_key( $r['question_id'] ?? '' ),
				'operator'     => $op,
				'answer_ids'   => array_values( array_unique( array_filter( $answer_ids ) ) ),
				'reason'       => sanitize_key( $r['reason'] ?? 'qualified' ),
				'reason_label' => sanitize_text_field( $r['reason_label'] ?? '' ),
			);
		}
		return $out;
	}

	public static function public_config() {
		$s = self::get();
		return array(
			'client_id'      => $s['client_id'],
			'form_id'        => $s['form_id'],
			'popup_title'    => $s['popup_title'],
			'popup_subtitle' => $s['popup_subtitle'],
			'fab_label'      => $s['fab_label'],
			'cta_label'      => $s['cta_label'],
			'close_label'    => $s['close_label'],
			'auto_show_fab'  => (int) $s['auto_show_fab'],
			'enable_gtm'     => (int) $s['enable_gtm'],
			'questions'      => $s['questions'],
			'rest_url'       => esc_url_raw( rest_url( 'sutor-lead-os/v1/leads' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
		);
	}
}
