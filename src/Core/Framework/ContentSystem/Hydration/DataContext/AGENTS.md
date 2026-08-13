> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Distribution is direct-children-only — never recursive
- Path resolution requires Struct objects at every intermediate step
- Property alias applied after path resolution, not before
- `consumerAlias: null` (default) uses provider's context key as property name
- Redistribution: `redistribute: true` → auto-generates broadcast provider at parse-time via `RedistributeExpansionSubscriber`
