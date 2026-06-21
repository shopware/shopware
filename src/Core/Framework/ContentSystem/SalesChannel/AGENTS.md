@README.md

## Constraints

- Routes registered via `ContentRouteLoader` (`routing.loader` tag) + `ContentRouteCompilerPass`, NOT via PHP attributes; the compiler pass builds one `ContentRoute` service per `content_system.section_resolver` × `content_system.output_format` and sets `ContentRouteLoader`'s first constructor argument to those route definitions
- `ContentRoute` parameterized via DI: `RenderingSpecificationResolver` (section) + `ContentSection` + content-layout `EntityRepository` + `AbstractResponseFactory` (format)
- Route calls resolver → loads `ContentLayoutEntity` (wrapped as `RenderableLayout`) → pipeline.load() → cacheFinalizer → responseFactory — specification resolution and layout-entity loading are in route, not pipeline
- Extension: decorate `AbstractContentRoute`
