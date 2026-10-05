# SalesChannel

Store API entry point. A single `ContentRoute` class serves all formats and content sections, parameterized via dependency injection.

## Key Classes

- `AbstractContentRoute` - Internal base class of `ContentRoute`, so route decoration is not offered ([extension surface](../docs/principles/extension-surface.md#the-extension-surface-is-a-deliberate-bounded-listed-choice))
- `ContentRoute` - One service per section and format, parameterized via DI. Passes the format factory's `getRenderingMode()` and `collectsValueIndex()` to the pipeline. Decomposed and data render in FULL mode like full and differ only in collecting a value index

## Endpoints

All endpoints use HTTP GET with cache enabled. `?elementId` partial rendering is gated per section, not per format: every main-section format accepts it, header and footer accept it in no format. See [Partial Rendering](../Output/README.md#partial-rendering).

**Main section:** `/store-api/content/{path}`, `/store-api/content-decomposed/{path}`, `/store-api/content-skeleton/{path}`, `/store-api/content-data/{path}`

**Header/Footer:** Same format variants at `/store-api/content-header*` and `/store-api/content-footer*`.

Field selection is not supported on any route. A request carrying an `includes` or `excludes` parameter in the request attributes, query or body is rejected with HTTP 400 (`CONTENT_SYSTEM__FIELD_SELECTION_NOT_SUPPORTED`) before `ContentPipeline` runs, in every format including skeleton. The error message names the parameter.

## Route Registration

`ContentRouteLoader` (`routing.loader` tag) registers the routes, not PHP attributes. `ContentRouteCompilerPass` builds one `ContentRoute` service per `content_system.section_resolver` × `content_system.output_format`.

## Subdirectories

- **Routing/** - Programmatic route registration (ContentRouteLoader, ContentRouteDefinition)
