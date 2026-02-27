@README.md

## Constraints

- Routes registered via `ContentRouteLoader` + `ContentRouteCompilerPass`, NOT via PHP attributes
- `ContentRoute` parameterized via DI: `RenderingSpecificationResolver` (section) + `AbstractResponseFactory` (format) + `ContentSection`
- Route calls resolver → pipeline.load() → cacheFinalizer → responseFactory — specification resolution is in route, not pipeline
- Extension: decorate `AbstractContentRoute`
