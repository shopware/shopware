# ContentSystem

@README.md

## Source Code References

- **Context Factories**: `Adapter/ProductContentLayoutContextFactory`, `Adapter/CategoryContentLayoutContextFactory`, `Adapter/LandingPageContentLayoutContextFactory`
- **Events**: `Event/PreContentHydrationEvent`, `Event/AfterContentHydrationEvent`
- **Event Subscribers**: `EventSubscriber/PreHydration/`, `EventSubscriber/PostHydration/`
- **Specification**: `RenderingSpecification`, `PlaceholderValues`
- **Hydration**: `Hydration/ContentElementHydrator`
- **Store API**: `SalesChannel/ContentRoute`, `SalesChannel/ContentRouteLoader`

## Quick Reference

- **Architecture**: Event-driven pipeline with PreContentHydrationEvent and AfterContentHydrationEvent
- **Pipeline**: Entity ID → Layout → Event (PreHydration) → Hydration → Event (PostHydration) → Response
- **Core Subscribers**: EventSubscriber/PreHydration/ (preparation) and EventSubscriber/PostHydration/ (finalization)
- **Main exception class**: `ContentSystemException`
- **ID generation**: `Uuid::randomHex()`
- **Package**: `#[Package('discovery')]`
- **Main API endpoint**: `/store-api/content/{path}`
- **DAL**: Use Criteria API + EntityDefinition, NOT Doctrine ORM
