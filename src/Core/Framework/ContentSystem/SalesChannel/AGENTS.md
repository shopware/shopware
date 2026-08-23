> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Routes registered via `ContentRouteLoader` (`routing.loader` tag) + `ContentRouteCompilerPass`, NOT via PHP attributes; the compiler pass builds one `ContentRoute` service per `content_system.section_resolver` × `content_system.output_format` and sets `ContentRouteLoader`'s first constructor argument to those route definitions
- `ContentRoute` parameterized via DI: `RenderingSpecificationResolver` (section) + `ContentSection` + content-layout `EntityRepository` + `AbstractResponseFactory` (format)
- Route calls resolver → loads `ContentLayoutEntity` (wrapped as `RenderableLayout`) → pipeline.load() → cacheFinalizer → responseFactory — specification resolution and layout-entity loading are in route, not pipeline
- The format's two answers travel from the factory through the route into the pipeline: `AbstractResponseFactory::getRenderingMode()` and `collectsValueIndex()`. They are independent questions — decomposed and data render in FULL mode like the full format and differ only in collecting a value index — and the route passes both, then hands the returned `Output/RenderResult` to `createResponse()`
- No extension surface: decorating `AbstractContentRoute` is not offered
