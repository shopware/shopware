> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Distribution is direct-children-only — never recursive
- Path resolution requires Struct objects at every intermediate step
- Property alias applied after path resolution, not before
- `consumerAlias: null` (default) makes the provider's own context key the consumer key it matches against; that key becomes the property name only for a matched consumer that declares no `propertyAlias`
- Redistribution: `redistribute: true` → auto-generates broadcast provider before the render step, in `ContentPipeline`'s redistribute-derivation step
- `ContextDeliveryResolver` owns the forest walk (top-down, required for chained re-providing) and `ContextDistributor` owns the rule for one parent and its direct children; neither writes into an element — deliveries come back in a `ContextDeliveryIndex`
