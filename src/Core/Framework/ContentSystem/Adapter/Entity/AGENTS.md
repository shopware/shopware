## Constraints

- Keep assignments unidirectional: a parent entity (Product, Category, Landing Page) must not know ContentSystem. Check: does a parent entity definition or entity class reference a ContentSystem type? See [README.md](../README.md#subdirectories)

## Where to look

- Assignment record and its unique key: [entity-rendering.md](../docs/entity-rendering.md#assignment-structure)
- Sales-channel fallback between assignments: [entity-rendering.md](../docs/entity-rendering.md#sales-channel-resolution)
- Assignment tables and repository service ids for entity, header and footer: [entity-rendering.md](../docs/entity-rendering.md#entity-based-rendering)
- The assignable-entity definition a specification source receives: [custom-sources.md](../docs/custom-sources.md#example-blog-post-source)
- Header and footer assignment key, fallback and registration: [README.md](../README.md#resolution-strategies)
- Entity base classes and the layout resolvers in FactoryHelper: [README.md](../README.md#subdirectories)
