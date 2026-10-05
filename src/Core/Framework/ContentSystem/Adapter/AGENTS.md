## Constraints

- Never let a request parameter choose the entity a loader targets or the property it reads. A per-request value reaches a loader only through a declared property. Check: can a query parameter named like the entity id field replace the server-derived placeholder value? Why it holds: [data-loading.md](../docs/principles/data-loading.md#loader-configuration-takes-its-final-form-at-write-time-and-no-request-selects-what-loads)

## Where to look

- Specification source chain of responsibility, the `supports()` contract and the tag priority: [custom-sources.md](docs/custom-sources.md#chain-of-responsibility)
- Registering an entity source with its assignable-entity definition: [custom-sources.md](docs/custom-sources.md#example-blog-post-source)
- Assignment-free resolution for the preview and diagnose actions: [custom-sources.md](docs/custom-sources.md#assignment-free-resolution-preview-support)
- Entity-based and domain-aware resolution, header and footer tiers, resolver instances and registration: [README.md](README.md#resolution-strategies)
- Root-source registry, resolver, factory and source base classes: [README.md](README.md#key-classes)
- FactoryHelper resolvers and the Entity base classes: [README.md](README.md#subdirectories)
- Main-section endpoints and supported path patterns: [entity-rendering.md](docs/entity-rendering.md#entity-based-rendering)
- The entity auto-loaded before the layout runs and how a layout receives it: [automatic-data-loading.md](docs/automatic-data-loading.md#automatic-data-loading)
- Default placeholders and extra query-string placeholders: [placeholders.md](docs/placeholders.md#placeholders)
- Admin API endpoint listing assignable entity types: [introspection.md](docs/introspection.md#entity-type-introspection)
