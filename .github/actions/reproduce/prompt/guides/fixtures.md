# Fixtures — `fixtures.json`

`fixtures.json` seeds exactly the entities/config your repro needs, via the Admin Sync API, before
the test runs. `repro seed` normalizes it, resolves placeholders, upserts, uploads any media, and
reindexes — and **prints the real Sync response**, so a wrong payload fails right there with the
API's own error. Author against the live schema with `repro schema` / `repro search` ([inspect.md](inspect.md)).

## Shape

Each top-level key is a DAL entity. Either the bare rows or a full Sync operation envelope:

```json
{
  "product": [
    { "id": "0af1…", "productNumber": "REPRO-1", "name": "Seeded Product", "stock": 10,
      "taxId": "{{TAX}}", "price": [{ "currencyId": "{{CURRENCY}}", "gross": 19.99, "net": 16.8, "linked": true }],
      "visibilities": [{ "salesChannelId": "{{SC}}", "visibility": 30 }] }
  ],
  "category": { "entity": "category", "action": "upsert", "payload": [ { "id": "…", "parentId": "{{NAV_CAT}}", "name": "Repro Cat" } ] }
}
```

- A bare array is wrapped as an `upsert`; hyphenated keys (`property-group`) are snake_cased for you.
- `system_config`: `configurationValue` is the literal value (`true`, `"x"`, a number, an array) — not a wrapper object.
- Translations: use `languageId: "{{SYSTEM_LANGUAGE}}"` for required translated rows (the DAL validates
  against the system language, which can differ from the sales-channel display `{{LANGUAGE}}`).
- Give nested child rows stable `id`s so re-seeding updates instead of duplicating.

## Placeholders — never hardcode install ids

Every provisioned shop generates different UUIDs, so a literal id seeds here but FK-fails on the
trunk leg. `repro seed` **rejects** hardcoded install ids and tells you which placeholder to use:

`{{SC}}` `{{NAV_CAT}}` `{{COUNTRY}}` `{{SALUTATION}}` `{{SALUTATION2}}` `{{TAX}}` `{{CURRENCY}}`
`{{LANGUAGE}}` `{{SYSTEM_LANGUAGE}}` `{{CUSTOMER_GROUP}}` `{{PAYMENT_METHOD}}` `{{SHIPPING_METHOD}}`
`{{ORDER_STATE_OPEN}}` `{{ORDER_DELIVERY_STATE_OPEN}}` `{{ORDER_TRANSACTION_STATE_OPEN}}`

(HTTP plans may also use `{{STOREFRONT_URL}}` `{{SW_ACCESS_KEY}}` `{{SW_CONTEXT_TOKEN}}`.)

## Media bytes

To seed a media entity that must have a real file, add a `_repro_media_uploads` array (uploaded
separately — not sent to Sync). Each entry: `mediaId`, `path` (an existing file, e.g. under
`issue-assets/`), `extension`, `mimeType`, optional `fileName`. Do NOT set write-protected fields
(`path`, `uploadedAt`, `fileSize`, `hasFile`, `url`) on the `media` row itself.

## `seeded_readiness` — prove setup, not the symptom

For a playwright repro with fixtures, add checks that prove the seeded state is reachable and rendered
on its surface. Each: `{ "kind": "browser", "path": "/route", "selector": "…", "text"?: "…",
"min_width"?: n, "min_height"?: n }`. `repro check` runs them and screenshots each.

Keep readiness **weaker** than the bug assertion — use a stable seeded identity (product title,
number, a container), never the reported broken control (if the issue says a badge/price/button is
wrong, that belongs in the final assertion, not here). One identity marker is usually enough.
