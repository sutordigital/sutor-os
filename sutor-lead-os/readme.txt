=== Sutor Lead OS ===
Contributors: sutordigital
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Floating WhatsApp lead popup with editable questions and server-side qualification.

== Shortcode ==

Place an enquiry button anywhere shortcodes run (page, post, text widget, or a page builder). It opens the same popup as the floating button.

`[sutor_enquiry]`

`[sutor_enquiry label="立即查詢" align="center"]`

Optional attributes: label, align (left, center, right), class, id, open="1".

`[sutor_lead_os]` loads the popup on that page and opens it immediately. Add a label to render a button: `[sutor_lead_os label="免費咨詢"]`.

Use your own link or page-builder button. The URL opens the same popup and does not navigate away:

`<a href="[sutor_enquiry_link]">免費咨詢</a>`

Paste `[sutor_enquiry_link]` into a link URL field. If the builder does not expand shortcodes, that literal href still works (including `http://[sutor_enquiry_link]`). The expanded URL is `#sutor-enquiry`.
