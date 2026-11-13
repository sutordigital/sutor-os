# Sutor Lead OS (Phase 1)

Reusable WordPress plugin for floating WhatsApp lead capture. Each site edits its own popup title, questions, answers and qualification rules in **wp-admin → Lead OS**. Leads are saved to a custom WordPress table **before** WhatsApp opens. Optional Supabase forwarding is supported but not required.

## Install

1. Upload `sutor-lead-os.zip` via **Plugins → Add New → Upload**, or unzip into `wp-content/plugins/sutor-lead-os/`.
2. Activate **Sutor Lead OS**.
3. Open **Lead OS → Form Settings** and set:
   - Popup title / subtitle / button labels
   - `client_id` and `form_id` (same values used in GTM)
   - WhatsApp number (digits with country code, e.g. `85295420762`)
   - Questions + answer options (repeater UI)
   - Qualification rules (OR logic, evaluated server-side)
4. Leave **Auto-show floating button** on, place `[sutor_lead_os]` to open the popup on specific pages, paste `[sutor_enquiry]` for an enquiry button, or point your own link at `[sutor_enquiry_link]`.
5. Import `gtm-container.json` into Google Tag Manager (or recreate the three triggers/tags). Set GA4 Measurement ID and Google Ads conversion ID/label variables.

### SiteGround note

SiteGround Antibot can block automated hits to `/wp-json/`. Normal browser visitors are fine. If REST calls fail from a scanner/bot, that is expected; do not disable REST for real visitors.

## Editing questions in the admin

1. Go to **Lead OS → Form Settings**.
2. Under **Questions**, click **Add question**.
3. Set label, field type (`Single choice` / `Multi choice` / `Text` / `Phone`), required flag.
4. For choice fields, add/remove/reorder answer options (each has an Option ID used by rules).
5. Under **Qualification rules**, add a rule: pick a question, choose matching answer IDs, set a reason code/label.
6. Click **Save settings** (or **Save via AJAX**). Reload the page to confirm values persist.
7. Front-end popup reads the saved options immediately (no code deploy).

Two different sites can run **identical plugin code** with totally different questions — config lives in the `sutor_lead_os_settings` option per site.

## Lead save + WhatsApp flow

1. Visitor fills the popup and clicks the green CTA.
2. Browser `POST`s to `/wp-json/sutor-lead-os/v1/leads` with answers + `gclid`/`gbraid`/`wbraid`/`utm_*` + landing URL.
3. PHP validates, qualifies (server-side), inserts into `{prefix}sutor_lead_os_leads` (deduped by hash).
4. Response returns `lead_id`, `qualified`, `qualify_reason`, `whatsapp_url`, `fire_events`.
5. Only then does the browser fire dataLayer events and open WhatsApp.
6. If save fails, **no** conversion events fire and WhatsApp does **not** open.
7. Repeat clicks with the same answers/phone/landing reuse the existing lead (`fire_events: false`) — no duplicate rows/events.

View leads under **Lead OS → Leads**.

## GTM events (no PII)

Same event names on every site:

```js
dataLayer.push({ event: 'lead_saved', client_id, form_id, lead_id, qualified, qualify_reason });
dataLayer.push({ event: 'lead_qualified', client_id, form_id, lead_id, qualified, qualify_reason }); // only if qualified
dataLayer.push({ event: 'whatsapp_open', client_id, form_id, lead_id });
```

Never put names, phones or emails in dataLayer.

## Onboarding a new client site

1. Install the same zip.
2. Set a unique `client_id` / `form_id`.
3. Edit title, questions, answers, rules, WhatsApp number/template.
4. Reuse the same GTM container; filter or lookup by `client_id` if needed.
5. (Optional) Set Supabase URL + anon key; deploy `supabase/migration.sql` and `supabase/sutor-lead-ingest`.

## Optional Supabase

See `supabase/migration.sql` and `supabase/sutor-lead-ingest/index.ts`. Leave URL/key blank to keep everything in WordPress only.

## Shortcodes

### Enquiry button — `[sutor_enquiry]`

Paste this into a page, post, text widget, or a page-builder shortcode block. It prints a button that opens the **same** enquiry popup as the floating button (same questions, lead save, qualification, GTM events, and WhatsApp handoff).

The button still works when **Auto-show floating button** is off. On that page the plugin loads the popup assets and does not render the floating button unless `[sutor_lead_os]` is also present. The **Lead popup** switch in Form Settings must stay on — that switch turns the popup itself off.

```
[sutor_enquiry]
```

The label defaults to the floating button label from **Lead OS → Form Settings** (shipped default: `免費咨詢`).

```
[sutor_enquiry label="立即查詢"]
[sutor_enquiry label="WhatsApp 查詢" align="center"]
[sutor_enquiry label="免費咨詢" class="my-cta" id="hero-enquiry" align="right"]
[sutor_enquiry open="1"]
```

| Attribute | Description |
| --- | --- |
| `label` | Button text. Defaults to the floating button label, then `免費咨詢`. |
| `align` | `left` (default), `center`, or `right`. |
| `class` | Extra CSS classes added to the button. |
| `id` | Optional `id` on the button. |
| `open` | Set to `1` to also open the popup as soon as the page loads. |

`[sutor_lead_os label="免費咨詢"]` and `[sutor_lead_os button="1"]` render the same button.

### Custom link — `[sutor_enquiry_link]`

Use your own button or link and point its URL at this shortcode. The click opens the **same** enquiry popup. It does not navigate away, and it does not start a second form.

```html
<a href="[sutor_enquiry_link]">免費咨詢</a>
```

In a page-builder link field, a menu URL, or an Elementor button link, paste:

```
[sutor_enquiry_link]
```

Those fields often do not expand shortcodes. WordPress URL sanitization may also store the value as `http://[sutor_enquiry_link]`. The popup script treats that literal href as the trigger. When the shortcode does run, it outputs `#sutor-enquiry`, which is safe to paste into a URL field as well:

```html
<a href="#sutor-enquiry">免費咨詢</a>
```

The **Lead popup** switch must stay on. **Auto-show floating button** can be off; the link still loads and opens the popup.

### Auto-open on a page — `[sutor_lead_os]`

```
[sutor_lead_os]
```

Loads the popup on that page and opens it immediately. With **Auto-show floating button** turned off, pages that use this shortcode still show the floating button so the form can be reopened.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Phase 1 scope

UI + admin editor + WP REST + DB leads + qualification + GTM + optional Supabase forward. Notion sync / offline Ads conversions are later phases.


## Import / Export (AI setup for new sites)

1. In **Lead OS → Form Settings**, open **Import / Export**.
2. Click **Copy AI prompt**, paste into ChatGPT (or ask me), and add a short brief for that website.
3. Paste the returned JSON into the box (or upload a `.json` file) → **Import into form** → **Save settings**.
4. Use **Export current JSON** to back up a working site config and reuse it as a starting point.

Sample file: `sutor-lead-os/examples/sample-config.json`.


## GTM & tracking admin page

Open **Lead OS → GTM & tracking** for:
- how `lead_saved` / `lead_qualified` / `whatsapp_open` fire
- recommended GA4 + Google Ads wiring
- a copy-paste AI prompt to build the GTM container for this site’s `client_id` / `form_id`

Fresh installs ship with a **basic** 3-question form (interest + name + phone). Import a site-specific JSON for each client.


Repository: https://github.com/sutordigital/sutor-os
