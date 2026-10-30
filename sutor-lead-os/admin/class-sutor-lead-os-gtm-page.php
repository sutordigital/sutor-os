<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sutor_Lead_OS_Gtm_Page {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_submenu_page(
			'sutor-lead-os',
			'GTM & tracking',
			'GTM & tracking',
			'manage_options',
			'sutor-lead-os-gtm',
			array( __CLASS__, 'render' )
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'sutor-lead-os-gtm' ) ) { return; }
		wp_enqueue_style( 'sutor-lead-os-admin', SUTOR_LEAD_OS_URL . 'admin/css/admin.css', array(), SUTOR_LEAD_OS_VERSION );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$settings = Sutor_Lead_OS_Settings::get();
		$client   = $settings['client_id'] ? $settings['client_id'] : 'CLIENT_ID';
		$form     = $settings['form_id'] ? $settings['form_id'] : 'FORM_ID';
		$ai_prompt = self::ai_prompt( $client, $form );
		?>
		<div class="wrap slos-admin slos-gtm-docs">
			<h1>GTM &amp; Google tracking</h1>
			<p class="description">How Sutor Lead OS fires tracking events, and a ready AI prompt so you can ask ChatGPT (or any coding AI) to wire GTM → GA4 → Google Ads for this site.</p>

			<section class="slos-card">
				<h2>How the plugin triggers GTM</h2>
				<ol>
					<li>Visitor fills the popup and clicks the green WhatsApp CTA.</li>
					<li>The plugin <strong>saves the lead first</strong> via <code>/wp-json/sutor-lead-os/v1/leads</code> and waits for a <code>lead_id</code>.</li>
					<li>Only if that save created a <strong>new</strong> lead, the browser pushes to <code>window.dataLayer</code>.</li>
					<li>Then WhatsApp opens. A failed save fires <strong>no</strong> conversion events.</li>
				</ol>
				<p>Turn events on/off with <strong>Lead OS → Form Settings → Fire GTM dataLayer events</strong>. Names, phones and emails are never sent to <code>dataLayer</code>.</p>
			</section>

			<section class="slos-card">
				<h2>Event names (same on every site)</h2>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th>Event</th><th>When</th><th>Payload fields</th></tr></thead>
					<tbody>
						<tr>
							<td><code>lead_saved</code></td>
							<td>New lead saved successfully</td>
							<td><code>client_id</code>, <code>form_id</code>, <code>lead_id</code>, <code>qualified</code>, <code>qualify_reason</code></td>
						</tr>
						<tr>
							<td><code>lead_qualified</code></td>
							<td>Same moment, only if <code>qualified === true</code></td>
							<td>Same fields as above</td>
						</tr>
						<tr>
							<td><code>whatsapp_open</code></td>
							<td>Right before WhatsApp opens (after save)</td>
							<td><code>client_id</code>, <code>form_id</code>, <code>lead_id</code></td>
						</tr>
					</tbody>
				</table>
				<pre style="background:#f6f7f7;padding:12px;border-radius:6px;overflow:auto;max-width:900px">dataLayer.push({
  event: 'lead_saved',
  client_id: '<?php echo esc_html( $client ); ?>',
  form_id: '<?php echo esc_html( $form ); ?>',
  lead_id: 'uuid…',
  qualified: true,
  qualify_reason: 'intent'
});</pre>
			</section>

			<section class="slos-card">
				<h2>Recommended Google setup</h2>
				<ul>
					<li><strong>GTM</strong> on the site (one container per site, or one with a Lookup Table by hostname).</li>
					<li><strong>Custom Event triggers</strong> for <code>lead_saved</code>, <code>lead_qualified</code>, <code>whatsapp_open</code>.</li>
					<li><strong>GA4 Event tags</strong> for each (pass <code>client_id</code>, <code>form_id</code>, <code>lead_id</code>, <code>qualified</code> as event params).</li>
					<li><strong>Google Ads conversion</strong> tag on <code>lead_qualified</code> only (not on every WhatsApp click).</li>
					<li>Use GTM variables: GA4 Measurement ID, Google Ads Conversion ID, Conversion Label.</li>
				</ul>
				<p>A starter container JSON ships with the plugin zip as <code>gtm-container.json</code> (in the project folder next to the plugin). Import it in GTM → Admin → Import Container, then fill the ID variables.</p>
			</section>

			<section class="slos-card">
				<div class="slos-card-head">
					<h2>AI prompt — build GTM around Lead OS</h2>
					<button type="button" class="button button-primary" id="slos-copy-gtm-prompt">Copy AI prompt</button>
				</div>
				<p class="description">Paste this into ChatGPT / Codex and add this site’s GA4 ID and Google Ads conversion ID + label. Current Form Settings IDs are already filled in.</p>
				<textarea id="slos-gtm-ai-prompt" rows="22" readonly style="width:100%;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px"><?php echo esc_textarea( $ai_prompt ); ?></textarea>
				<p class="description" id="slos-gtm-copy-status"></p>
			</section>

			<section class="slos-card">
				<h2>Quick test checklist</h2>
				<ol>
					<li>Install GTM and enable <strong>Fire GTM dataLayer events</strong>.</li>
					<li>Open the site in GTM Preview / Tag Assistant.</li>
					<li>Submit the popup once → expect <code>lead_saved</code> (and <code>lead_qualified</code> if rules match), then <code>whatsapp_open</code>.</li>
					<li>Submit again with the same answers quickly → should <strong>not</strong> re-fire <code>lead_saved</code> / <code>lead_qualified</code> (dedupe).</li>
				</ol>
			</section>
		</div>
		<script>
		(function(){
			var btn = document.getElementById('slos-copy-gtm-prompt');
			var box = document.getElementById('slos-gtm-ai-prompt');
			var status = document.getElementById('slos-gtm-copy-status');
			if (!btn || !box) return;
			btn.addEventListener('click', function(){
				var text = box.value;
				function ok(){ if (status) status.textContent = 'Copied. Paste into your AI, then add GA4 / Ads IDs.'; }
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(ok).catch(function(){ box.select(); document.execCommand('copy'); ok(); });
				} else { box.select(); document.execCommand('copy'); ok(); }
			});
		})();
		</script>
		<?php
	}

	private static function ai_prompt( $client, $form ) {
		return <<<PROMPT
You are setting up Google Tag Manager for a WordPress site that uses the "Sutor Lead OS" plugin.

The plugin already pushes these dataLayer events (do NOT invent other event names):

1) lead_saved — after a NEW lead is saved
   fields: client_id, form_id, lead_id, qualified (bool), qualify_reason
2) lead_qualified — only when qualified === true (same fields)
3) whatsapp_open — after save, right before WhatsApp opens
   fields: client_id, form_id, lead_id

Important rules from the plugin:
- Events fire ONLY after the lead is saved and lead_id is returned.
- Duplicate / deduped submits set fire_events=false → do not count as new conversions.
- Never put name, phone, or email in tags or dataLayer mappings.
- This site's Form Settings IDs: client_id="{$client}", form_id="{$form}"

Please produce a clear GTM build plan AND concrete tag/trigger/variable setup for:
- GA4 Measurement ID: {{PASTE_GA4_ID}}
- Google Ads Conversion ID: {{PASTE_AW_ID}}
- Google Ads Conversion Label (qualified lead): {{PASTE_AW_LABEL}}

Requirements:
1. Custom Event triggers for lead_saved, lead_qualified, whatsapp_open.
2. GA4 Event tags for all three; map the payload fields as event parameters.
3. Google Ads Conversion tag ONLY on lead_qualified (primary conversion).
4. Optional: secondary Ads/remarketing on lead_saved if useful — say so explicitly.
5. Data Layer Variables for client_id, form_id, lead_id, qualified, qualify_reason.
6. A short QA checklist for GTM Preview.
7. If helpful, output a JSON snippet describing tags/triggers/variables I can recreate by hand.

Do not change the plugin event names. Do not ask the plugin to push PII.
PROMPT;
	}
}
