@README.md

## Constraints

- Distribution is direct-children-only — never recursive
- Path resolution requires Struct objects at every intermediate step
- Property alias applied after path resolution, not before
- `consumer_alias: null` (default) uses provider's context key as property name
- Redistribution: `redistribute: true` → auto-generates broadcast provider at parse-time via `RedistributeExpansionSubscriber`
