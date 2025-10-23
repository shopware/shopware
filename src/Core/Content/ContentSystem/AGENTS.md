# ContentSystem

@README.md

## Source Code References

- **Routing**: `Routing/Router/ContentRouter`
- **Entity Resolution**: `Routing/IdResolution/EntityIdResolver`
- **Layout Resolution**: `Routing/LayoutResolution/LayoutResolver`
- **Context Factories**: `Routing/RouteBasedContextFactory`, `Adapter/ProductContextFactory`, `Adapter/CategoryContextFactory`
- **Layout Search**: `Adapter/LayoutSearchHelper`
- **Specification**: `RenderingSpecification`, `PlaceholderValues`
- **Refinement**: `Layout/Refinery/RefinedLayoutBuilder`, `Layout/Refinery/LayoutRefinery`
- **Hydration**: `Hydration/ContentElementHydrator`
- **Store API**: `SalesChannel/ContentRoute`, `SalesChannel/ContentRouteLoader`

## Quick Reference

- **Pipeline**: Routing → Resolution → Layout → Refinement → Hydration
- **Main exception class**: `ContentSystemException`
- **ID generation**: `Uuid::randomHex()`
- **Package**: `#[Package('discovery')]`
- **Main API endpoint**: `/store-api/content/{path}`
- **DAL**: Use Criteria API + EntityDefinition, NOT Doctrine ORM
