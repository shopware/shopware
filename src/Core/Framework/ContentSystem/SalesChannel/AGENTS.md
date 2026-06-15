@README.md

## Constraints

- Routes registered via `ContentRouteLoader` + `ContentRouteCompilerPass`, NOT via PHP attributes
- `ContentRoute` parameterized via DI: `RenderingSpecificationResolver` (section) + `ContentSection` + content-layout `EntityRepository` + `AbstractResponseFactory` (format)
- Route calls resolver → loads `ContentLayoutEntity` (wrapped as `RenderableLayout`) → pipeline.load() → cacheFinalizer → responseFactory — specification resolution and layout-entity loading are in route, not pipeline
- Extension: decorate `AbstractContentRoute`
