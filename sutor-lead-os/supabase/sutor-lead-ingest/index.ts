// Supabase Edge Function: sutor-lead-ingest
// Deploy: supabase functions deploy sutor-lead-ingest --no-verify-jwt
// Uses service role to insert; plugin calls with anon key + Authorization bearer.

import { createClient } from "https://esm.sh/@supabase/supabase-js@2";

Deno.serve(async (req) => {
  if (req.method !== "POST") {
    return new Response(JSON.stringify({ error: "method_not_allowed" }), { status: 405 });
  }
  try {
    const body = await req.json();
    const url = Deno.env.get("SUPABASE_URL")!;
    const key = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
    const supabase = createClient(url, key);

    const row = {
      lead_id: body.lead_id,
      client_id: body.client_id,
      form_id: body.form_id || "",
      answers: body.answers || {},
      contact_name: body.contact_name || "",
      contact_phone: body.contact_phone || "",
      contact_email: body.contact_email || "",
      qualified: !!body.qualified,
      qualify_reason: body.qualify_reason || "",
      gclid: body.gclid || "",
      gbraid: body.gbraid || "",
      wbraid: body.wbraid || "",
      utm_source: body.utm_source || "",
      utm_medium: body.utm_medium || "",
      utm_campaign: body.utm_campaign || "",
      utm_term: body.utm_term || "",
      utm_content: body.utm_content || "",
      landing_page: body.landing_page || "",
      created_at: body.created_at || new Date().toISOString(),
    };

    const { error } = await supabase.from("leads").upsert(row, { onConflict: "lead_id" });
    if (error) {
      return new Response(JSON.stringify({ error: error.message }), { status: 500 });
    }
    return new Response(JSON.stringify({ success: true }), {
      headers: { "Content-Type": "application/json" },
    });
  } catch (e) {
    return new Response(JSON.stringify({ error: String(e) }), { status: 400 });
  }
});
