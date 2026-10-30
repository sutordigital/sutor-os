<?php
if ( ! defined( "ABSPATH" ) ) {
	exit;
}

class Sutor_Lead_OS_REST {

	public static function init() {
		add_action( "rest_api_init", array( __CLASS__, "register_routes" ) );
	}

	public static function register_routes() {
		register_rest_route(
			"sutor-lead-os/v1",
			"/leads",
			array(
				"methods"             => "POST",
				"callback"            => array( __CLASS__, "create_lead" ),
				"permission_callback" => "__return_true",
			)
		);
		register_rest_route(
			"sutor-lead-os/v1",
			"/leads/(?P<lead_id>[a-f0-9\\-]{36})/wa-opened",
			array(
				"methods"             => "POST",
				"callback"            => array( __CLASS__, "wa_opened" ),
				"permission_callback" => "__return_true",
			)
		);
		register_rest_route(
			"sutor-lead-os/v1",
			"/config",
			array(
				"methods"             => "GET",
				"callback"            => array( __CLASS__, "get_config" ),
				"permission_callback" => "__return_true",
			)
		);
	}

	public static function get_config() {
		return rest_ensure_response( Sutor_Lead_OS_Settings::public_config() );
	}

	public static function create_lead( WP_REST_Request $request ) {
		$settings = Sutor_Lead_OS_Settings::get();
		$answers  = $request->get_param( "answers" );
		if ( ! is_array( $answers ) ) {
			return new WP_Error( "invalid_answers", "Answers must be an object.", array( "status" => 400 ) );
		}
		$validation = self::validate_answers( $settings["questions"], $answers );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$contact = self::extract_contact( $settings["questions"], $answers );
		$attrs   = self::sanitize_attribution( $request );
		$qual    = self::evaluate_qualification( $settings["qualification_rules"], $answers );
		$dedupe_payload = array(
			"client_id" => $settings["client_id"],
			"form_id"   => $settings["form_id"],
			"answers"   => $answers,
			"phone"     => $contact["phone"],
			"landing"   => $attrs["landing_page"],
		);
		$dedupe_hash = hash( "sha256", wp_json_encode( $dedupe_payload ) );
		$lead_id = self::uuid_v4();

		$row = array(
			"lead_id"        => $lead_id,
			"client_id"      => $settings["client_id"],
			"form_id"        => $settings["form_id"],
			"dedupe_hash"    => $dedupe_hash,
			"answers"        => wp_json_encode( $answers ),
			"contact_name"   => $contact["name"],
			"contact_phone"  => $contact["phone"],
			"contact_email"  => $contact["email"],
			"qualified"      => $qual["qualified"] ? 1 : 0,
			"qualify_reason" => $qual["reason"],
			"gclid"          => $attrs["gclid"],
			"gbraid"         => $attrs["gbraid"],
			"wbraid"         => $attrs["wbraid"],
			"utm_source"     => $attrs["utm_source"],
			"utm_medium"     => $attrs["utm_medium"],
			"utm_campaign"   => $attrs["utm_campaign"],
			"utm_term"       => $attrs["utm_term"],
			"utm_content"    => $attrs["utm_content"],
			"landing_page"   => $attrs["landing_page"],
			"user_agent"     => isset( $_SERVER["HTTP_USER_AGENT"] ) ? sanitize_text_field( wp_unslash( $_SERVER["HTTP_USER_AGENT"] ) ) : "",
			"ip_hash"        => self::ip_hash(),
		);
		$result = Sutor_Lead_OS_DB::insert_or_get( $row );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$lead    = $result["lead"];
		$created = (bool) $result["created"];
		if ( $created ) {
			self::maybe_forward_supabase( $lead, $settings );
		}
		$wa_url = self::build_wa_url( $settings, $lead, $answers );
		return rest_ensure_response(
			array(
				"success"        => true,
				"created"        => $created,
				"lead_id"        => $lead->lead_id,
				"qualified"      => (bool) (int) $lead->qualified,
				"qualify_reason" => $lead->qualify_reason,
				"client_id"      => $lead->client_id,
				"form_id"        => $lead->form_id,
				"whatsapp_url"   => $wa_url,
				"fire_events"    => $created,
			)
		);
	}

	public static function wa_opened( WP_REST_Request $request ) {
		$lead_id = sanitize_text_field( $request["lead_id"] );
		Sutor_Lead_OS_DB::mark_whatsapp_opened( $lead_id );
		return rest_ensure_response( array( "success" => true ) );
	}

	private static function validate_answers( $questions, $answers ) {
		foreach ( $questions as $q ) {
			$id  = $q["id"];
			$val = array_key_exists( $id, $answers ) ? $answers[ $id ] : null;
			if ( ! empty( $q["required"] ) ) {
				$empty = ( null === $val || "" === $val || ( is_array( $val ) && 0 === count( $val ) ) );
				if ( $empty ) {
					return new WP_Error( "missing_field", sprintf( "Required field missing: %s", $q["label"] ), array( "status" => 400, "field" => $id ) );
				}
			}
			if ( null === $val || "" === $val ) {
				continue;
			}
			if ( "single" === $q["type"] ) {
				$ids = wp_list_pluck( $q["options"], "id" );
				if ( ! in_array( $val, $ids, true ) ) {
					return new WP_Error( "invalid_option", "Invalid option for " . $id, array( "status" => 400 ) );
				}
			}
			if ( "multi" === $q["type"] ) {
				if ( ! is_array( $val ) ) {
					return new WP_Error( "invalid_option", "Multi answers must be array.", array( "status" => 400 ) );
				}
				$ids = wp_list_pluck( $q["options"], "id" );
				foreach ( $val as $v ) {
					if ( ! in_array( $v, $ids, true ) ) {
						return new WP_Error( "invalid_option", "Invalid multi option for " . $id, array( "status" => 400 ) );
					}
				}
			}
			if ( "phone" === $q["type"] ) {
				$digits = preg_replace( "/[^0-9+]/", "", (string) $val );
				if ( strlen( $digits ) < 8 ) {
					return new WP_Error( "invalid_phone", "Phone looks too short.", array( "status" => 400 ) );
				}
			}
		}
		return true;
	}

	private static function extract_contact( $questions, $answers ) {
		$name = ""; $phone = ""; $email = "";
		foreach ( $questions as $q ) {
			$id = $q["id"];
			if ( ! isset( $answers[ $id ] ) ) { continue; }
			if ( "phone" === $q["type"] ) {
				$phone = sanitize_text_field( (string) $answers[ $id ] );
			} elseif ( "text" === $q["type"] && false !== stripos( $q["label"], "name" ) || false !== strpos( $q["label"], "名" ) ) {
				$name = sanitize_text_field( (string) $answers[ $id ] );
			} elseif ( "text" === $q["type"] && false !== stripos( $q["label"], "email" ) ) {
				$email = sanitize_email( (string) $answers[ $id ] );
			}
		}
		if ( "" === $name ) {
			foreach ( $questions as $q ) {
				if ( "text" === $q["type"] && isset( $answers[ $q["id"] ] ) ) {
					$name = sanitize_text_field( (string) $answers[ $q["id"] ] );
					break;
				}
			}
		}
		return compact( "name", "phone", "email" );
	}

	private static function sanitize_attribution( WP_REST_Request $request ) {
		$keys = array( "gclid", "gbraid", "wbraid", "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content", "landing_page" );
		$out = array();
		foreach ( $keys as $k ) {
			$v = $request->get_param( $k );
			$out[ $k ] = ( "landing_page" === $k ) ? esc_url_raw( (string) $v ) : sanitize_text_field( (string) $v );
		}
		return $out;
	}

	public static function evaluate_qualification( $rules, $answers ) {
		foreach ( (array) $rules as $rule ) {
			$qid = $rule["question_id"];
			if ( ! isset( $answers[ $qid ] ) ) { continue; }
			$val = $answers[ $qid ];
			$need = isset( $rule["answer_ids"] ) ? (array) $rule["answer_ids"] : array();
			$op = $rule["operator"] ?? "in";
			$match = false;
			if ( "eq" === $op ) {
				$match = in_array( $val, $need, true );
			} elseif ( "intersects" === $op ) {
				$arr = is_array( $val ) ? $val : array( $val );
				$match = count( array_intersect( $arr, $need ) ) > 0;
			} else {
				$match = is_array( $val ) ? ( count( array_intersect( $val, $need ) ) > 0 ) : in_array( $val, $need, true );
			}
			if ( $match ) {
				return array(
					"qualified" => true,
					"reason" => $rule["reason"] ?? "qualified",
					"reason_label" => $rule["reason_label"] ?? "",
				);
			}
		}
		return array( "qualified" => false, "reason" => "", "reason_label" => "" );
	}

	private static function build_wa_url( $settings, $lead, $answers ) {
		$number = preg_replace( "/[^0-9]/", "", (string) $settings["whatsapp_number"] );
		$lines = array();
		foreach ( $settings["questions"] as $q ) {
			$id = $q["id"];
			if ( ! isset( $answers[ $id ] ) ) { continue; }
			$display = self::format_answer_label( $q, $answers[ $id ] );
			$lines[] = $q["label"] . ": " . $display;
		}
		$answers_text = implode( "\n", $lines );
		$tpl = (string) $settings["whatsapp_template"];
		$msg = str_replace(
			array( "{answers}", "{lead_id}", "{client_id}", "{form_id}", "{name}", "{phone}" ),
			array( $answers_text, $lead->lead_id, $lead->client_id, $lead->form_id, $lead->contact_name, $lead->contact_phone ),
			$tpl
		);
		return "https://api.whatsapp.com/send?phone=" . $number . "&text=" . rawurlencode( $msg );
	}

	private static function format_answer_label( $q, $val ) {
		if ( in_array( $q["type"], array( "single", "multi" ), true ) ) {
			$map = array();
			foreach ( $q["options"] as $opt ) { $map[ $opt["id"] ] = $opt["label"]; }
			if ( is_array( $val ) ) {
				$labels = array();
				foreach ( $val as $v ) { $labels[] = isset( $map[ $v ] ) ? $map[ $v ] : $v; }
				return implode( ", ", $labels );
			}
			return isset( $map[ $val ] ) ? $map[ $val ] : (string) $val;
		}
		return (string) $val;
	}

	private static function maybe_forward_supabase( $lead, $settings ) {
		$url = trailingslashit( (string) $settings["supabase_url"] );
		$key = (string) $settings["supabase_anon_key"];
		if ( "" === $url || "" === $key ) { return; }
		$endpoint = $url . "functions/v1/sutor-lead-ingest";
		$body = array(
			"lead_id" => $lead->lead_id,
			"client_id" => $lead->client_id,
			"form_id" => $lead->form_id,
			"answers" => json_decode( $lead->answers, true ),
			"contact_name" => $lead->contact_name,
			"contact_phone" => $lead->contact_phone,
			"contact_email" => $lead->contact_email,
			"qualified" => (bool) (int) $lead->qualified,
			"qualify_reason" => $lead->qualify_reason,
			"gclid" => $lead->gclid,
			"gbraid" => $lead->gbraid,
			"wbraid" => $lead->wbraid,
			"utm_source" => $lead->utm_source,
			"utm_medium" => $lead->utm_medium,
			"utm_campaign" => $lead->utm_campaign,
			"utm_term" => $lead->utm_term,
			"utm_content" => $lead->utm_content,
			"landing_page" => $lead->landing_page,
			"created_at" => $lead->created_at,
		);
		$response = wp_remote_post(
			$endpoint,
			array(
				"timeout" => 8,
				"headers" => array(
					"Content-Type" => "application/json",
					"Authorization" => "Bearer " . $key,
					"apikey" => $key,
				),
				"body" => wp_json_encode( $body ),
			)
		);
		if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 300 ) {
			Sutor_Lead_OS_DB::mark_supabase_synced( $lead->lead_id );
		}
	}

	private static function uuid_v4() {
		$data = random_bytes( 16 );
		$data[6] = chr( ( ord( $data[6] ) & 0x0f ) | 0x40 );
		$data[8] = chr( ( ord( $data[8] ) & 0x3f ) | 0x80 );
		return vsprintf( "%s%s-%s-%s-%s-%s%s%s", str_split( bin2hex( $data ), 4 ) );
	}

	private static function ip_hash() {
		$ip = "";
		if ( ! empty( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
			$parts = explode( ",", sanitize_text_field( wp_unslash( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) );
			$ip = trim( $parts[0] );
		} elseif ( ! empty( $_SERVER["REMOTE_ADDR"] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER["REMOTE_ADDR"] ) );
		}
		if ( "" === $ip ) { return ""; }
		return hash( "sha256", $ip . wp_salt( "auth" ) );
	}
}
