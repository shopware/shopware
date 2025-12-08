# ContentSystem

@README.md

## Source Code References

- **Context Factories**: `Adapter/ProductContentLayoutContextFactory`, `Adapter/CategoryContentLayoutContextFactory`, `Adapter/LandingPageContentLayoutContextFactory`
- **Events**: `Event/PreContentHydrationEvent`, `Event/PostHydrationEvent`
- **Event Subscribers**: `EventSubscriber/PreHydration/`, `EventSubscriber/PostHydration/`
- **Specification**: `RenderingSpecification`, `PlaceholderValues`
- **Hydration**: `Hydration/ContentElementHydrator`
- **Store API**: `SalesChannel/ContentRoute`, `SalesChannel/ContentDecomposedRoute`, `SalesChannel/ContentSkeletonRoute`, `SalesChannel/ContentDataRoute`
- **Pipeline**: `ContentPipeline`, `RenderingSpecificationResolver`

## Quick Reference

- **Architecture**: Event-driven pipeline with PreContentHydrationEvent and PostHydrationEvent
- **Pipeline**: Entity ID → Layout → Event (PreHydration) → Hydration → Event (PostHydration) → Response
- **Core Subscribers**: EventSubscriber/PreHydration/ (preparation) and EventSubscriber/PostHydration/ (finalization)
- **Main exception class**: `ContentSystemException`
- **ID generation**: `Uuid::randomHex()`
- **Package**: `#[Package('discovery')]`
- **Main API endpoint**: `/store-api/content/{path}`
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
