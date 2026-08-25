> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `redistribute: true` generates virtual provider at runtime — the redistribute derivation in `Rendering/WiringPlanner::plan()`, which runs on the stored tree after the partial prune and before the lowering — never persisted
- `consumerAlias` requires `redistribute: true` — enforced at `Layout/Codec/StoredElementCodec`, which throws `ContentSystemException::consumerAliasWithoutRedistribute()` on decode, and `Layout/Codec/StoredTreeConstraints`, which reports it as a write-descriptor violation on `[consumerAlias]`
- `propertyAlias` does NOT require `redistribute` (independent). It renames only the delivery key: matching and dot-path resolution run on the consumer key, and the alias names where the resolved value lands (`Rendering/ContextDistributor::deliverTo()`)
- `propertyAlias`: no dots — enforced at the same two sites as `consumerAlias` above; unique per element — the wiring validation in `Rendering/WiringPlanner::plan()` alone, which runs after the partial prune and before the redistribute derivation, on the pre-prune forest
