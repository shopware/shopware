# Adapter

Connects CMS-capable entities (Product, Category, Landing Page) and domain-scoped content (Header, Footer) to the rendering pipeline. Specification sources implement `supports()` to claim paths, then `RenderingSpecificationFactory` assembles a `ResolvedContentLayout` (layout ID plus `RenderingSpecification`) from discrete resolution steps.

## Resolution Strategies

**Entity-based** (Product, Category, Landing Page): Assignment tables with sales channel fallback. `EntityLayoutResolver` queries: sales channel specific → global.

**Domain-aware** (Header, Footer): Three-tier fallback via `DomainAwareLayoutResolver`: domain+channel → channel → global.

## Key Classes

- `RenderingSpecificationResolver` - Iterates sources via `supports()` (path-based, returns a `ResolvedContentLayout`) or `supportsEntityType()` (`resolveWithoutLayout()` — assignment-free, returns a bare `RenderingSpecification` for the preview action), delegating to `RenderingSpecificationFactory`
- `RenderingSpecificationFactory` - `create()` assembles a `ResolvedContentLayout` from the source's discrete resolution steps; `createWithoutLayout()` assembles a `RenderingSpecification` with no layout id (no assignment lookup)
- `AbstractSpecificationSource` - Base class: `supports()`, `resolveLayoutId()`, `resolveSpecificationData()`, `resolveTargetElementId()`, `resolveCacheTags()`, plus the assignment-free `supportsEntityType()` (default `false`) and `resolveSpecificationDataForEntity()` (default throws), overridden by entity sources, and `providedRootContext(Context $context): list<ProvidedContext>` (default `[]`; overridden by entity sources to expose root-ambient context for the diagnose route)
- Entity sources moved to domain aggregates: `Content/Product/.../ProductSpecificationSource`, `Content/Category/.../CategorySpecificationSource`, `Content/LandingPage/.../LandingPageSpecificationSource`
- Domain-aware sources moved to Storefront: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`

## Subdirectories

- **Entity/** - Abstract base classes for the assignment side: `AbstractContentLayoutAssignmentEntity` (the assignment-record entity) and `AbstractContentLayoutAssignableDefinition` (the `EntityDefinition` base for assignable entity types, exposing `getPageDataRequirements()`, `getContentLayoutEntityType()`, etc.)
- **FactoryHelper/** - Shared resolution logic (EntityLayoutResolver, EntityLayoutContextFactory, DomainAwareLayoutResolver, NavigationAliasResolver)
