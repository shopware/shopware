# SalesChannel

Store API entry point. A single `ContentRoute` class serves all formats and content sections, parameterized via dependency injection.

## Key Classes

- `AbstractContentRoute` - Base class of `ContentRoute`. Route decoration is not an offered extension surface
- `ContentRoute` - DI-parameterized: `RenderingSpecificationResolver` + `ContentSection` + content-layout `EntityRepository` + `AbstractResponseFactory`

## Endpoints

All endpoints use HTTP GET with cache enabled. `?elementId` partial rendering is gated per section, not per format: every main-section format accepts it, and header and footer accept it in no format, because their specification sources never resolve a target element. See [Partial Rendering](../Output/README.md#partial-rendering).

**Main section:** `/store-api/content/{path}`, `/store-api/content-decomposed/{path}`, `/store-api/content-skeleton/{path}`, `/store-api/content-data/{path}`

**Header/Footer:** Same format variants at `/store-api/content-header*` and `/store-api/content-footer*`.

Field selection is not supported on any of the twelve routes. A request carrying an `includes` or `excludes` parameter, in the attribute, query or request bag, is rejected with HTTP 400 (`CONTENT_SYSTEM__FIELD_SELECTION_NOT_SUPPORTED`) before the pipeline runs, in every format including skeleton. The parameter is named in the error message.

Routes registered programmatically via `ContentRouteLoader` in Routing/, not via PHP attributes.

## Subdirectories

- **Routing/** - Programmatic route registration (ContentRouteLoader, ContentRouteDefinition)
