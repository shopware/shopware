> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `redistribute: true` generates virtual provider at runtime (priority 4000) — never persisted
- `consumerAlias` requires `redistribute: true` — enforced at two sites: `Layout/Codec/StoredElementCodec` throws `ContentSystemException::consumerAliasWithoutRedistribute()` on decode, and `Layout/Codec/StoredTreeConstraints` reports it as a write-descriptor violation on `[consumerAlias]`
- `propertyAlias` does NOT require `redistribute` (independent)
- `propertyAlias`: no dots — enforced at the same two sites as `consumerAlias` above; unique per element — `RedistributeExpansionSubscriber` alone
