# Adapter

Connects CMS-capable entities (Product, Category, Landing Page) and domain-scoped content (Header, Footer) to the rendering pipeline. Specification sources implement `supports()` to claim paths, then `RenderingSpecificationFactory` assembles a `ResolvedContentLayout` (layout ID plus `RenderingSpecification`) from discrete resolution steps.

## Guides

- [docs/entity-rendering.md](docs/entity-rendering.md) - The main-section endpoints, the assignment record, and the sales channel fallback between assignments.
- [docs/automatic-data-loading.md](docs/automatic-data-loading.md) - The entity an entity-based render loads before the layout runs, and how a layout takes delivery of it.
- [docs/placeholders.md](docs/placeholders.md) - The placeholders entity-based rendering provides, and passing more via the query string.
- [docs/custom-sources.md](docs/custom-sources.md) - The plugin-facing guide to authoring and registering a specification source.
- [docs/introspection.md](docs/introspection.md) - The Admin API endpoint listing the entity types a layout can be assigned to.

## Resolution Strategies

**Entity-based** (Product, Category, Landing Page): Assignment tables with sales channel fallback. `EntityLayoutResolver` queries: sales channel specific → global.

**Domain-aware** (Header, Footer): Three-tier fallback via `DomainAwareLayoutResolver`: domain+channel → channel → global.

## Subdirectories

- **Entity/** - Abstract base classes for the assignment side: `AbstractContentLayoutAssignmentEntity` (the assignment-record entity) and `AbstractContentLayoutAssignableDefinition` (the `EntityDefinition` base for assignable entity types, exposing `getPageDataRequirements()`, `getContentLayoutEntityType()`, etc.)
- **FactoryHelper/** - Shared resolution logic (EntityLayoutResolver, EntityLayoutContextFactory, DomainAwareLayoutResolver, NavigationAliasResolver)
