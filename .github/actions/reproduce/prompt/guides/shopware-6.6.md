# Shopware 6.6 MCP fallback

Shopware 6.6 and older do not expose the remote Shopware MCP endpoint. That only means the remote
tool set is unavailable; it does **not** mean you should skip live inspection.

The reproduce bridge still exposes local fallback tools that call the provisioned shop's Admin and
Sync APIs directly:

- `shopware-entity-schema`
- `shopware-entity-search`
- `shopware-entity-read`
- `shopware-entity-upsert`

Use these tools on 6.6 the same way you would use MCP on newer versions: inspect the live schema,
look at default entities, dry-run the candidate Sync payload, then mirror the static payload into
`fixtures.json`.

Only fall back to reading entity definitions or tests when a fallback tool genuinely errors, such as
missing credentials or an unreachable shop. If that happens, record the limitation in
`agent_explanation`; do not treat the version number alone as a reason to guess from source.
