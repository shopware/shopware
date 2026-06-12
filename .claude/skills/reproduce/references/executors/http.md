# Executor: `http` (store-/admin-API)

Use when the symptom surfaces on a store-api or admin-api **response**. Cheapest faithful
layer for most API bugs. Builds neither storefront nor theme.

## What you author
Put the request(s) in `analysis.json` — there is **no** separate script file (the executor
generates `repro.sh` from the plan and runs it). Use:

- `request` — a single object: `{ method, path, headers, body }`, OR
- `requests` — an array for a multi-step flow (e.g. create context → add to cart → read).
  The assertion runs on the **FINAL** response.

The executor authenticates each request **by its path** — admin API (`/api/...`) gets an
admin OAuth **Bearer** token, store API (`/store-api/...`) gets `sw-access-key` — and
captures/carries `sw-context-token` across the sequence. **Do NOT** put any auth header
(`sw-access-key`, `Authorization`) or `sw-context-token` in the plan: the executor injects
the right credential for the surface and drops any you add. Just give the correct `path`
(an admin-api bug → `/api/...`; a store-api bug → `/store-api/...`).

## Install-specific ids → placeholders
Reference pre-existing install ids via the placeholders the executor resolves against the
running shop (see SCHEMA "fixtures" for the full catalog):
`{{SC}} {{NAV_CAT}} {{COUNTRY}} {{SALUTATION}} {{SALUTATION2}} {{TAX}} {{CURRENCY}}
{{LANGUAGE}} {{STOREFRONT_URL}}` — also valid inside `assertion.expect`.
Entities you create yourself go in `fixtures.json` with known 32-char hex UUIDs.

## Assertion
- `assertion.kind`: `http_status` | `response_field` | `exception`.
- `assertion.expect` is the **healthy** value (what a FIXED shop returns). Leg is
  `reproduced` when `actual != expect`, `not_reproduced` when `actual == expect`.
- `assertion.field` is a jq path, used only by `response_field`.
- `assertion.locator` is a human reference to the endpoint.
- Send `Accept: application/json` when you need the flat (non-JSON:API) response shape.

## Failure semantics (no false positives)
- A non-2xx on a **non-final** request → `blocked` (setup broke; body shown).
- A **missing field** on a non-2xx **final** response → `inconclusive`, never a bogus
  `reproduced` (the symptom couldn't be evaluated).

## Comment every step
Comment each request explaining what it does and what the final assertion checks.
