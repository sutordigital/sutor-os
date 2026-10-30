<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sutor_Lead_OS_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_sutor_lead_os_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'wp_ajax_sutor_lead_os_save', array( __CLASS__, 'ajax_save' ) );
	}

	public static function menu() {
		add_menu_page(
			'Lead OS',
			'Lead OS',
			'manage_options',
			'sutor-lead-os',
			array( __CLASS__, 'render_settings' ),
			'dashicons-format-chat',
			58
		);
		add_submenu_page(
			'sutor-lead-os',
			'Form Settings',
			'Form Settings',
			'manage_options',
			'sutor-lead-os',
			array( __CLASS__, 'render_settings' )
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'sutor-lead-os' ) ) { return; }
		wp_enqueue_style( 'sutor-lead-os-admin', SUTOR_LEAD_OS_URL . 'admin/css/admin.css', array(), SUTOR_LEAD_OS_VERSION );
		wp_enqueue_script( 'sutor-lead-os-admin', SUTOR_LEAD_OS_URL . 'admin/js/admin.js', array(), SUTOR_LEAD_OS_VERSION, true );
		wp_localize_script( 'sutor-lead-os-admin', 'SutorLeadOSAdmin', array(
			'settings' => Sutor_Lead_OS_Settings::get(),
			'nonce'    => wp_create_nonce( 'sutor_lead_os_save' ),
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		) );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'sutor_lead_os_save', 'sutor_lead_os_nonce' );
		$raw = isset( $_POST['sutor_lead_os_json'] ) ? wp_unslash( $_POST['sutor_lead_os_json'] ) : '';
		$data = json_decode( $raw, true );
		$result = Sutor_Lead_OS_Settings::save( is_array( $data ) ? $data : array() );
		$redirect = add_query_arg(
			array(
				'page'    => 'sutor-lead-os',
				'updated' => is_wp_error( $result ) ? '0' : '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function ajax_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		check_ajax_referer( 'sutor_lead_os_save', 'nonce' );
		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		if ( is_string( $raw ) ) {
			$data = json_decode( $raw, true );
		} else {
			$data = $raw;
		}
		$result = Sutor_Lead_OS_Settings::save( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'settings' => $result ) );
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = Sutor_Lead_OS_Settings::get();
		$updated  = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( $_GET['updated'] ) ) : '';
		?>
		<div class="wrap slos-admin">
			<h1>Sutor Lead OS</h1>
			<?php if ( '1' === $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php elseif ( '0' === $updated ) : ?>
				<div class="notice notice-error is-dismissible"><p>Could not save settings.</p></div>
			<?php endif; ?>
			<p class="description">Edit the floating popup title, questions, answers, qualification rules and WhatsApp message. Each site stores its own config in WordPress options — no code changes needed.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="slos-settings-form">
				<input type="hidden" name="action" value="sutor_lead_os_save" />
				<?php wp_nonce_field( 'sutor_lead_os_save', 'sutor_lead_os_nonce' ); ?>
				<input type="hidden" name="sutor_lead_os_json" id="slos-settings-json" value="" />

			<section class="slos-card slos-master-toggle">
				<div class="slos-toggle-row">
					<div>
						<h2 style="margin:0 0 4px">Lead popup</h2>
						<p class="description" style="margin:0">Turn the floating button and popup on or off without deactivating the plugin. Leads page and settings stay available.</p>
					</div>
					<label class="slos-switch" for="slos-enabled">
						<input type="checkbox" id="slos-enabled" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
						<span class="slos-switch-ui" aria-hidden="true"></span>
						<span class="slos-switch-label" id="slos-enabled-label"><?php echo ! empty( $settings['enabled'] ) ? 'On' : 'Off'; ?></span>
					</label>
				</div>
			</section>

				<div class="slos-grid">
					<section class="slos-card">
						<h2>Popup copy</h2>
						<label>Popup title <input type="text" id="slos-popup-title" value="<?php echo esc_attr( $settings['popup_title'] ); ?>" /></label>
						<label>Subtitle <textarea id="slos-popup-subtitle" rows="2"><?php echo esc_textarea( $settings['popup_subtitle'] ); ?></textarea></label>
						<label>Floating button label <input type="text" id="slos-fab-label" value="<?php echo esc_attr( $settings['fab_label'] ); ?>" /></label>
						<label>Submit button text <input type="text" id="slos-cta-label" value="<?php echo esc_attr( $settings['cta_label'] ); ?>" /></label>
						<label>Close label <input type="text" id="slos-close-label" value="<?php echo esc_attr( $settings['close_label'] ); ?>" /></label>
						<label class="slos-check"><input type="checkbox" id="slos-auto-fab" <?php checked( ! empty( $settings['auto_show_fab'] ) ); ?> /> Auto-show floating button site-wide</label>
					</section>

					<section class="slos-card">
						<h2>IDs, WhatsApp &amp; tracking</h2>
						<label>client_id <input type="text" id="slos-client-id" value="<?php echo esc_attr( $settings['client_id'] ); ?>" /></label>
						<label>form_id <input type="text" id="slos-form-id" value="<?php echo esc_attr( $settings['form_id'] ); ?>" /></label>
						<label>WhatsApp number (digits only, with country code) <input type="text" id="slos-wa-number" value="<?php echo esc_attr( $settings['whatsapp_number'] ); ?>" /></label>
						<label>WhatsApp message template <textarea id="slos-wa-tpl" rows="5"><?php echo esc_textarea( $settings['whatsapp_template'] ); ?></textarea></label>
						<p class="description">Placeholders: {answers} {lead_id} {client_id} {form_id} {name} {phone}</p>
						<label class="slos-check"><input type="checkbox" id="slos-enable-gtm" <?php checked( ! empty( $settings['enable_gtm'] ) ); ?> /> Fire GTM dataLayer events</label>
						<label>Supabase Edge Function base URL (optional) <input type="url" id="slos-sb-url" value="<?php echo esc_attr( $settings['supabase_url'] ); ?>" placeholder="https://xxxx.supabase.co/" /></label>
						<label>Supabase anon / public key (optional) <input type="text" id="slos-sb-key" value="<?php echo esc_attr( $settings['supabase_anon_key'] ); ?>" /></label>
					</section>
				</div>


				<section class="slos-card">
					<div class="slos-card-head">
						<h2>Import / Export (AI setup)</h2>
					</div>
					<p class="description">Paste a JSON config from ChatGPT (or upload a <code>.json</code> file) to fill title, questions, answers and rules for a new website. Then click <strong>Save settings</strong>.</p>
					<label>Paste JSON
						<textarea id="slos-import-json" rows="10" placeholder="{ &#34;popup_title&#34;: &#34;…&#34;, &#34;questions&#34;: [ … ] }"></textarea>
					</label>
					<p class="slos-import-actions">
						<button type="button" class="button button-primary" id="slos-import-apply">Import into form</button>
						<button type="button" class="button" id="slos-import-file-btn">Upload .json</button>
						<input type="file" id="slos-import-file" accept="application/json,.json" style="display:none" />
						<button type="button" class="button" id="slos-export-json">Export current JSON</button>
						<button type="button" class="button" id="slos-copy-ai-prompt">Copy AI prompt</button>
					</p>
					<p class="description" id="slos-import-status"></p>
					<details class="slos-ai-help">
						<summary>JSON shape for AI</summary>
						<pre id="slos-schema-hint" style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border-radius:6px;overflow:auto;max-height:280px;"></pre>
					</details>
				</section>

				<section class="slos-card">
					<div class="slos-card-head">
						<h2>Questions</h2>
						<button type="button" class="button" id="slos-add-question">Add question</button>
					</div>
					<div id="slos-questions"></div>
				</section>

				<section class="slos-card">
					<div class="slos-card-head">
						<h2>Qualification rules</h2>
						<button type="button" class="button" id="slos-add-rule">Add rule</button>
					</div>
					<p class="description">A lead is qualified if <strong>any</strong> rule matches (OR). Evaluation runs server-side on save.</p>
					<div id="slos-rules"></div>
				</section>

				<p class="submit">
					<button type="submit" class="button button-primary button-hero" id="slos-save-btn">Save settings</button>
					<button type="button" class="button" id="slos-ajax-save">Save via AJAX</button>
				</p>
			</form>
		</div>
		<?php
	}
}
