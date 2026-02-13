# Adapter

Connects CMS-capable entities (Product, Category, Landing Page) and domain-scoped content (Header, Footer) to the rendering pipeline. Specification sources implement `supports()` to claim paths, then `RenderingSpecificationFactory` assembles `RenderingSpecification` objects from discrete resolution steps.

## Resolution Strategies

**Entity-based** (Product, Category, Landing Page): Assignment tables with sales channel fallback. `EntityLayoutResolver` queries: sales channel specific → global.

**Domain-aware** (Header, Footer): Three-tier fallback via `DomainAwareLayoutResolver`: domain+channel → channel → global.

## Key Classes

- `RenderingSpecificationResolver` - Iterates sources via `supports()`, delegates to `RenderingSpecificationFactory`
- `RenderingSpecificationFactory` - Assembles specification from source's discrete resolution steps
- `AbstractSpecificationSource` - Base class: `supports()`, `resolveLayoutId()`, `resolveSpecificationData()`, `resolveTargetElementId()`, `resolveCacheTags()`
- `ProductSpecificationSource`, `CategorySpecificationSource`, `LandingPageSpecificationSource` - Entity sources
- `HeaderSpecificationSource`, `FooterSpecificationSource` - Domain-aware sources

## Subdirectories

- **Entity/** - Assignment entity definitions (Product, Category, LandingPage, Header, Footer)
- **FactoryHelper/** - Shared resolution logic (EntityLayoutResolver, DomainAwareLayoutResolver, NavigationAliasResolver)
- **Field/** - Custom DAL field types (CriteriaFilter, ParameterBinding, ResolutionConfig)
- **ParameterBinding/** - Parameter binding DTOs
