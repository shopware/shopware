# Live inspection — `repro schema` / `repro search`

Before authoring `fixtures.json` (or an http plan), inspect the **running** shop instead of guessing
from source. Two commands read the live Admin API; both work identically on every Shopware version
(6.6 included) — they are core Admin API, not the version-specific MCP endpoint.

## `repro schema [entity]`

- `repro schema` — lists every entity name.
- `repro schema product` — prints one entity's fields: `type`, `required`, `primaryKey`,
  `translatable`, and for associations the target `entity` + `relation` (e.g. `tax` is a
  `many_to_one` association to `tax` via `taxId`). This reflects the real instance, so
  plugin/extension fields are included.

Use it to learn which fields a Sync payload must set and which associations reference other entities.

## `repro search <entity> [criteria-json]`

DAL search of the live instance; criteria defaults to `{"limit":10}`. Prints flat rows with ids and
fields at top level — pipe through `jq` for large results.

```
repro search tax '{"limit":1}'                     # a real tax id on this shop
repro search sales-channel '{"limit":5}'           # sales channels + accessKey
repro search product '{"limit":1,"filter":[{"type":"equals","field":"active","value":true}]}'
```

Use it to read real reference ids and to confirm that seeded rows landed.

## Authoring loop

1. `repro schema <entity>` — required fields and association shape.
2. `repro search <entity> …` — real ids for anything you reference.
3. Prefer a `{{PLACEHOLDER}}` for the common reference ids (see [fixtures.md](fixtures.md)); only
   hard-look-up ids the placeholder set doesn't cover.
4. Write the payload into `fixtures.json`, then `repro seed` — it runs the **real Sync API**, so a
   wrong payload fails immediately with the validation detail.

Writes always go through `fixtures.json` + `repro seed`, never a live write from inspection — the
deterministic seed is what replays identically on both legs.
