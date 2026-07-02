# Shopware MCP — author fixtures against the live shop (6.7+)

On Shopware 6.7+ the run exposes Shopware MCP tools that talk to the already-provisioned
reported-version shop. When present, they're the best way to get fixture write-shapes right without
guessing from source. If they're absent (6.6 and older, or not started), skip this — read entity
definitions/tests near the reported surface instead, and note the limitation in `agent_explanation`.

Typical tools: `shopware-entity-schema`, `shopware-entity-search`, `shopware-entity-read`,
`shopware-entity-upsert`.

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
