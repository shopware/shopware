> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `redistribute: true` generates virtual provider at runtime — the redistribute derivation in `Rendering/WiringPlanner::plan()`, which runs on the stored tree after the partial prune and before the lowering — never persisted
- `consumerAlias` requires `redistribute: true` — enforced at `Layout/Codec/StoredElementCodec`, which throws `ContentSystemException::consumerAliasWithoutRedistribute()` on decode, and `Layout/Codec/StoredTreeConstraints`, which reports it as a write-descriptor violation on `[consumerAlias]`
- `propertyAlias` does NOT require `redistribute` (independent). It renames only the delivery key: matching and dot-path resolution run on the consumer key, and the alias names where the resolved value lands (`Rendering/ContextDistributor::deliverTo()`)
- `propertyAlias`: no dots — enforced at the same two sites as `consumerAlias` above; unique per element — the wiring validation in `Rendering/WiringPlanner::plan()` alone, which runs after the partial prune and before the redistribute derivation, on the pre-prune forest
- A child-facing delivery key is unique per element, counting both the authored providers (`distributionConfig->getConsumerAlias() ?? providerKey`) and the broadcast providers the redistribute derivation adds from `redistribute` consumers (`consumerAlias ?? contextKey`). `ProviderDeliveryKeyResolver` owns the rule and the derived-key formula, throwing `ContentSystemException::providerDeliveryCollision()`; it indexes the authored providers first, so the earlier of two colliding producers is the one reported as `first`. Both enforcement sites call it — the wiring validation in `Rendering/WiringPlanner::plan()` over the pre-prune forest, and the context walk in `Resolution/AvailableContextResolver` over the target and its ancestors — while which elements each site judges stays that site's own decision
