-- Sutor Lead OS optional Supabase schema (multi-tenant ready for Phase 1)
-- Plugin works fully without Supabase; apply only if forwarding is enabled.

create extension if not exists "pgcrypto";

create table if not exists public.leads (
  id uuid primary key default gen_random_uuid(),
  lead_id uuid not null unique,
  client_id text not null,
  form_id text not null default '',
  answers jsonb not null default '{}'::jsonb,
  contact_name text not null default '',
  contact_phone text not null default '',
  contact_email text not null default '',
  qualified boolean not null default false,
  qualify_reason text not null default '',
  gclid text not null default '',
  gbraid text not null default '',
  wbraid text not null default '',
  utm_source text not null default '',
  utm_medium text not null default '',
  utm_campaign text not null default '',
  utm_term text not null default '',
  utm_content text not null default '',
  landing_page text not null default '',
  created_at timestamptz not null default now()
);

create index if not exists leads_client_created_idx on public.leads (client_id, created_at desc);

alter table public.leads enable row level security;

-- Deny anon direct table access; Edge Function uses service role.
create policy "no direct anon select" on public.leads
  for select to anon using (false);

create policy "no direct anon insert" on public.leads
  for insert to anon with check (false);
