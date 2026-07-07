# Shopware MCP — author fixtures against the live shop

The run exposes Shopware MCP tools that talk to the already-provisioned reported-version shop. They
are the best way to get fixture write-shapes right without guessing from source — use them on every
version.

On Shopware 6.7+ the bridge proxies the shop's remote MCP server and merges in the local fallback
tools below. On Shopware 6.6 and older the remote endpoint is absent, but the local fallback tools
still work against the live shop; see [shopware-6.6.md](shopware-6.6.md) for the version-specific
context.

The local fallback tools:

- `shopware-entity-schema <entity>` — Admin API entity schema metadata (fields, types, relations).
- `shopware-entity-search <entity> [criteria]` — DAL search of a live entity (defaults to `limit:10`).
- `shopware-entity-read <entity> <id>` — read one entity by UUID.
- `shopware-entity-upsert <entity> <payload>` — validate/post a Sync API upsert; `dryRun` defaults to
  `true`, so it normalizes and returns the envelope without mutating state.

## Flow

1. `shopware-entity-schema <entity>` for each entity you plan to seed — learn required fields,
   types, and relationships for *this* version.
2. `shopware-entity-search` / `shopware-entity-read` to inspect real default entities and see how
   relationships are shaped. Use the `{{PLACEHOLDER}}` names in `fixtures.json` — do **not** copy the
   install-specific UUIDs you see here into the file (they differ per shop; [fixtures.md](fixtures.md)).
3. `shopware-entity-upsert` with `dryRun=true` to validate a candidate payload's write-shape before
   committing it. A clean dry run is strong evidence the shape is right.
4. Mirror the validated static payload into `fixtures.json`. The deterministic pipeline re-seeds
   from that file (never from MCP-mutated state), so the bundle must be self-contained.

## What MCP can't answer

Opaque JSON config (e.g. `cms_slot.config`, `system_config.configurationValue`) and *which* rendered
field/route proves the seeded state — for those, read the registered element `defaultConfig`, the
block/slot registration, and the consuming template, and record them in the plan's rationale. MCP
proves the write-shape; source proves what the surface reads.
