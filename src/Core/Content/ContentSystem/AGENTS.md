# ContentSystem

@README.md

## Source Code References

- **Context Factories**: `Adapter/ProductContentLayoutContextFactory`, `Adapter/CategoryContentLayoutContextFactory`, `Adapter/LandingPageContentLayoutContextFactory`
- **Layout Search**: `Adapter/LayoutSearchHelper`
- **Specification**: `RenderingSpecification`, `PlaceholderValues`
- **Refinement**: `Layout/Refinery/RefinedLayoutBuilder`, `Layout/Refinery/LayoutRefinery`
- **Hydration**: `Hydration/ContentElementHydrator`
- **Store API**: `SalesChannel/ContentRoute`, `SalesChannel/ContentRouteLoader`

## Quick Reference

- **Pipeline**: Entity ID → Layout → Refinement → Hydration → Response
- **Main exception class**: `ContentSystemException`
- **ID generation**: `Uuid::randomHex()`
- **Package**: `#[Package('discovery')]`
- **Main API endpoint**: `/store-api/content/{path}`
- **DAL**: Use Criteria API + EntityDefinition, NOT Doctrine ORM
