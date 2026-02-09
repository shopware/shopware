# ContentSystem

@README.md

## Source Code References

- **Context Factories**: `Adapter/ProductContentLayoutContextFactory`, `Adapter/CategoryContentLayoutContextFactory`, `Adapter/LandingPageContentLayoutContextFactory`
- **Header/Footer Factories**: `Adapter/HeaderSpecificationFactory`, `Adapter/FooterSpecificationFactory`
- **Resolvers**: `Adapter/FactoryHelper/DomainAwareLayoutResolver`, `Adapter/FactoryHelper/NavigationAliasResolver`
- **Events**: `Event/PreContentHydrationEvent`, `Event/PostHydrationEvent`
- **Event Subscribers**: `EventSubscriber/PreHydration/`, `EventSubscriber/PostHydration/`
- **Specification**: `LayoutType`, `RenderingSpecification`, `PlaceholderValues`
- **Hydration**: `Hydration/ContentElementHydrator`
- **Store API (Main)**: `SalesChannel/ContentRoute`, `SalesChannel/ContentDecomposedRoute`, `SalesChannel/ContentSkeletonRoute`, `SalesChannel/ContentDataRoute`
- **Store API (Header)**: `SalesChannel/Header/ContentHeaderRoute`, `SalesChannel/Header/ContentHeaderDecomposedRoute`, `SalesChannel/Header/ContentHeaderSkeletonRoute`, `SalesChannel/Header/ContentHeaderDataRoute`
- **Store API (Footer)**: `SalesChannel/Footer/ContentFooterRoute`, `SalesChannel/Footer/ContentFooterDecomposedRoute`, `SalesChannel/Footer/ContentFooterSkeletonRoute`, `SalesChannel/Footer/ContentFooterDataRoute`
- **Pipeline**: `ContentPipeline`, `RenderingSpecificationResolver`

## Quick Reference

- **Architecture**: Event-driven pipeline with PreContentHydrationEvent and PostHydrationEvent
- **Pipeline**: Entity ID → Layout → Event (PreHydration) → Hydration → Event (PostHydration) → Response
- **Core Subscribers**: EventSubscriber/PreHydration/ (preparation) and EventSubscriber/PostHydration/ (finalization)
- **Main exception class**: `ContentSystemException`
- **ID generation**: `Uuid::randomHex()`
- **Package**: `#[Package('discovery')]`
- **API endpoints**:
  - Main: `/store-api/content/{path}` (entity-based resolution)
  - Header: `/store-api/content-header*` (domain-aware resolution)
  - Footer: `/store-api/content-footer*` (domain-aware resolution)
- **Layout types**: `LayoutType::MAIN`, `LayoutType::HEADER`, `LayoutType::FOOTER`
- **DAL**: Use Criteria API + EntityDefinition, NOT Doctrine ORM

## Store API Schema

Update OpenAPI schema files when modifying endpoints or response structures.

- **Location**: `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/`
- **Files**: `paths/content.json`, `components/schemas/Content*.json`
- **Validate**: `jq '.' <file>.json`

## Constraints

### Pipeline Orchestration

`ContentPipeline` orchestrates rendering via `RenderingSpecificationResolver`:

1. **Factory Selection**: Iterate context factories in DI priority order until one returns RenderingSpecification
2. **PreHydration Events**: Subscribers prepare layout (placeholder resolution, virtual root, partial pruning)
3. **Hydration**: ContentElementHydrator loads data + resolves context
4. **PostHydration Events**: Subscribers finalize layout (virtual root cleanup, partial extraction)

See `ContentPipeline::load()` for implementation.

### Chain of Responsibility

`RenderingSpecificationResolver` implements Chain of Responsibility pattern. Factories tagged with `content_system.context_factory` are tried in DI priority order. First non-null `RenderingSpecification` wins. Throws `ContentSystemException` if no factory handles the path.
