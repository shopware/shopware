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

## Key Classes

- `RootSourceRegistry` - The single authority over the valid root-source set and their resolution. `knownRootSources()` (entity types + section keys + `none`, excludes `main`), `entityRootSources()` (entity-type subset, backs `content-system-entity-types.json`), `resolve(rootSource, Context): list<ProvidedContext>` (fail-hard on an unknown id; callers gate membership first), `sourceFor(rootSource)`. The entity-type id list is baked in at build time by `ContentLayoutAssignableCompilerPass`
- `NoneSpecificationSource` - The `none` root source for a context-free layout: `providedRootContext()` is `[]`, `supports()` / `supportsEntityType()` are `false`, and the four resolution methods throw. It keeps `content_layout.root_source` total
- `RenderingSpecificationResolver` - Iterates sources via `supports()` (path-based, returns a `ResolvedContentLayout`) or `supportsEntityType()` (`resolveWithoutLayout()` — assignment-free, returns a bare `RenderingSpecification` for the preview action), delegating to `RenderingSpecificationFactory`
- `RenderingSpecificationFactory` - `create()` assembles a `ResolvedContentLayout` from the source's discrete resolution steps; `createWithoutLayout()` assembles a `RenderingSpecification` with no layout id (no assignment lookup)
- `AbstractSpecificationSource` - Base class: `supports()`, `resolveLayoutId()`, `resolveSpecificationData()`, `resolveTargetElementId()`, `resolveCacheTags()`, plus the assignment-free `supportsEntityType()` (default `false`) and `resolveSpecificationDataForEntity()` (default throws), overridden by entity sources, and `providedRootContext(Context $context): list<ProvidedContext>` (default `[]`; overridden by entity sources to expose root-ambient context, reached via `RootSourceRegistry::resolve()`)
- Entity sources moved to domain aggregates: `Content/Product/.../ProductSpecificationSource`, `Content/Category/.../CategorySpecificationSource`, `Content/LandingPage/.../LandingPageSpecificationSource`
- Domain-aware sources moved to Storefront: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`

## Subdirectories

- **Entity/** - Abstract base classes for the assignment side: `AbstractContentLayoutAssignmentEntity` (the assignment-record entity) and `AbstractContentLayoutAssignableDefinition` (the `EntityDefinition` base for assignable entity types, exposing `getPageDataRequirements()`, `getContentLayoutEntityType()`, etc.)
- **FactoryHelper/** - Shared resolution logic (EntityLayoutResolver, EntityLayoutContextFactory, DomainAwareLayoutResolver, NavigationAliasResolver)
