## Constraints

- Enforce a change to an alias, redistribute or scope wiring rule at all three sites that judge wiring: `StoredElementWiringDecoder`, `StoredTreeWiringConstraints` and `WiringPlanner::plan()`. Check: does the change add or loosen a wiring rule at one site only? `StoredTreeShapeConformanceTest` pins codec and descriptor agreement. [drafts-and-gates.md](../../../docs/principles/drafts-and-gates.md)
- Never combine `scope: root` with `redistribute: true`: redistribution derives a provider for what a consumer received off the parent chain, and a root context value is not on it. Check: does the change admit the combination at any of the three sites? [context-wiring.md](../../../docs/principles/context-wiring.md)
- Derive the `redistribute: true` provider at runtime only and never persist it. Check: does an encode or write path put a derived provider into a stored `providesContext`? [redistribution.md](docs/redistribution.md#where-each-rule-is-enforced)
- Keep a [child-facing delivery key](docs/providers.md) unique per element, computed only in `ProviderDeliveryKeyResolver`. Check: does the change derive a delivery key elsewhere? `AvailableContextResolverTest` pins the shared key resolver. [context-wiring.md](../../../docs/principles/context-wiring.md)
- Serve direct children only. Check: does the change relay root context? [context-wiring.md](../../../docs/principles/context-wiring.md)

## Where to look

- Alias, redistribute and scope rules, and the decoder, descriptor and planner site of each: [redistribution.md](docs/redistribution.md#where-each-rule-is-enforced)
- The `providesContext` entry and the child-facing delivery key rule: [providers.md](docs/providers.md)
- The `acceptsContext` entry, `scope` and `propertyAlias`: [consumers.md](docs/consumers.md)
- Dot-notation access to a provided entity: [path-resolution.md](docs/path-resolution.md)
- Distribution strategies and the flow rules: [distribution-strategies.md](docs/distribution-strategies.md); the strategy value objects: [Distribution/README.md](Distribution/README.md)
- One provider feeding three consumers end to end: [worked-example.md](docs/worked-example.md)
- Key classes of the area: [README.md](README.md#key-classes)
