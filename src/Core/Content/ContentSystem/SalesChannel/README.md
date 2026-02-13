# SalesChannel

Store API entry point. A single `ContentRoute` class serves all formats and content sections, parameterized via dependency injection.

## Key Classes

- `AbstractContentRoute` - Decorator base for route extension
- `ContentRoute` - DI-parameterized: `RenderingSpecificationResolver` + `ContentSection` + `AbstractResponseFactory`

## Endpoints

All endpoints use HTTP GET with cache enabled. Full and decomposed accept `?elementId` for partial rendering.

**Main section:** `/store-api/content/{path}`, `/store-api/content-decomposed/{path}`, `/store-api/content-skeleton/{path}`, `/store-api/content-data/{path}`

**Header/Footer:** Same format variants at `/store-api/content-header*` and `/store-api/content-footer*`.

Routes registered programmatically via `ContentRouteLoader` in Routing/, not via PHP attributes.

## Subdirectories

- **Routing/** - Programmatic route registration (ContentRouteLoader, ContentRouteDefinition)
