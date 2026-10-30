<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sutor_Lead_OS_DB {

	const TABLE = 'sutor_lead_os_leads';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function activate() {
		self::create_table();
		if ( false === get_option( SUTOR_LEAD_OS_OPTION, false ) ) {
			add_option( SUTOR_LEAD_OS_OPTION, Sutor_Lead_OS::default_settings(), '', false );
		}
		update_option( 'sutor_lead_os_db_version', SUTOR_LEAD_OS_DB_VERSION );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		$ver = get_option( 'sutor_lead_os_db_version' );
		if ( $ver !== SUTOR_LEAD_OS_DB_VERSION ) {
			self::create_table();
			update_option( 'sutor_lead_os_db_version', SUTOR_LEAD_OS_DB_VERSION );
		}
		if ( false === get_option( SUTOR_LEAD_OS_OPTION, false ) ) {
			add_option( SUTOR_LEAD_OS_OPTION, Sutor_Lead_OS::default_settings(), '', false );
		}
	}

	public static function create_table() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			lead_id char(36) NOT NULL,
			client_id varchar(100) NOT NULL DEFAULT '',
			form_id varchar(100) NOT NULL DEFAULT '',
			dedupe_hash char(64) NOT NULL,
			answers longtext NOT NULL,
			contact_name varchar(191) NOT NULL DEFAULT '',
			contact_phone varchar(64) NOT NULL DEFAULT '',
			contact_email varchar(191) NOT NULL DEFAULT '',
			qualified tinyint(1) NOT NULL DEFAULT 0,
			qualify_reason varchar(191) NOT NULL DEFAULT '',
			gclid varchar(255) NOT NULL DEFAULT '',
			gbraid varchar(255) NOT NULL DEFAULT '',
			wbraid varchar(255) NOT NULL DEFAULT '',
			utm_source varchar(191) NOT NULL DEFAULT '',
			utm_medium varchar(191) NOT NULL DEFAULT '',
			utm_campaign varchar(191) NOT NULL DEFAULT '',
			utm_term varchar(191) NOT NULL DEFAULT '',
			utm_content varchar(191) NOT NULL DEFAULT '',
			landing_page text NOT NULL,
			user_agent text NOT NULL,
			ip_hash char(64) NOT NULL DEFAULT '',
			whatsapp_opened tinyint(1) NOT NULL DEFAULT 0,
			supabase_synced tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY lead_id (lead_id),
			UNIQUE KEY dedupe_hash (dedupe_hash),
			KEY client_form (client_id, form_id),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insert lead or return existing row for same dedupe hash.
	 *
	 * @param array $row
	 * @return array{lead:object,created:bool}|WP_Error
	 */
	public static function insert_or_get( $row ) {
		global $wpdb;
		$table = self::table_name();

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE dedupe_hash = %s LIMIT 1", $row['dedupe_hash'] )
		);
		if ( $existing ) {
			return array( 'lead' => $existing, 'created' => false );
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'lead_id'          => $row['lead_id'],
				'client_id'        => $row['client_id'],
				'form_id'          => $row['form_id'],
				'dedupe_hash'      => $row['dedupe_hash'],
				'answers'          => $row['answers'],
				'contact_name'     => $row['contact_name'],
				'contact_phone'    => $row['contact_phone'],
				'contact_email'    => $row['contact_email'],
				'qualified'        => (int) $row['qualified'],
				'qualify_reason'   => $row['qualify_reason'],
				'gclid'            => $row['gclid'],
				'gbraid'           => $row['gbraid'],
				'wbraid'           => $row['wbraid'],
				'utm_source'       => $row['utm_source'],
				'utm_medium'       => $row['utm_medium'],
				'utm_campaign'     => $row['utm_campaign'],
				'utm_term'         => $row['utm_term'],
				'utm_content'      => $row['utm_content'],
				'landing_page'     => $row['landing_page'],
				'user_agent'       => $row['user_agent'],
				'ip_hash'          => $row['ip_hash'],
				'whatsapp_opened'  => 0,
				'supabase_synced'  => 0,
				'created_at'       => current_time( 'mysql', true ),
			),
			array(
				'%s','%s','%s','%s','%s','%s','%s','%s','%d','%s',
				'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s',
			)
		);

		if ( false === $ok ) {
			// Race: another request inserted same hash.
			$existing = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE dedupe_hash = %s LIMIT 1", $row['dedupe_hash'] )
			);
			if ( $existing ) {
				return array( 'lead' => $existing, 'created' => false );
			}
			return new WP_Error( 'db_insert_failed', 'Failed to save lead.', array( 'status' => 500 ) );
		}

		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ) );
		return array( 'lead' => $lead, 'created' => true );
	}

	public static function mark_whatsapp_opened( $lead_id ) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'whatsapp_opened' => 1 ),
			array( 'lead_id' => $lead_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	public static function mark_supabase_synced( $lead_id ) {
		global $wpdb;
		$wpdb->update(
			self::table_name(),
			array( 'supabase_synced' => 1 ),
			array( 'lead_id' => $lead_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	public static function list_leads( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = self::table_name();
		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	public static function count_leads() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}
}
