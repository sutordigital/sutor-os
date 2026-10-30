<?php
/**
 * Plugin Name: Sutor Lead OS
 * Plugin URI: https://sutor.digital/
 * Description: Reusable WhatsApp lead capture popup with editable questions, server-side qualification, GTM events, and optional Supabase forward.
 * Version: 1.0.4
 * Author: Sutor Digital
 * Author URI: https://sutor.digital/
 * Text Domain: sutor-lead-os
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SUTOR_LEAD_OS_VERSION', '1.0.4' );
define( 'SUTOR_LEAD_OS_FILE', __FILE__ );
define( 'SUTOR_LEAD_OS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUTOR_LEAD_OS_URL', plugin_dir_url( __FILE__ ) );
define( 'SUTOR_LEAD_OS_OPTION', 'sutor_lead_os_settings' );
define( 'SUTOR_LEAD_OS_DB_VERSION', '1.0.1' );

require_once SUTOR_LEAD_OS_DIR . 'includes/class-sutor-lead-os.php';
require_once SUTOR_LEAD_OS_DIR . 'includes/class-sutor-lead-os-db.php';
require_once SUTOR_LEAD_OS_DIR . 'includes/class-sutor-lead-os-settings.php';
require_once SUTOR_LEAD_OS_DIR . 'includes/class-sutor-lead-os-rest.php';
require_once SUTOR_LEAD_OS_DIR . 'includes/class-sutor-lead-os-frontend.php';
require_once SUTOR_LEAD_OS_DIR . 'admin/class-sutor-lead-os-admin.php';
require_once SUTOR_LEAD_OS_DIR . 'admin/class-sutor-lead-os-leads-page.php';
require_once SUTOR_LEAD_OS_DIR . 'admin/class-sutor-lead-os-gtm-page.php';

register_activation_hook( __FILE__, array( 'Sutor_Lead_OS_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Sutor_Lead_OS', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Sutor_Lead_OS', 'instance' ) );
