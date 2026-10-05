## Navigation

- Why the module adds nothing to a plugin-extended base class: [extension-surface.md](../docs/principles/extension-surface.md)

## Constraints

- Routes registered via `ContentRouteLoader` (`routing.loader` tag) + `ContentRouteCompilerPass`, NOT via PHP attributes; the compiler pass builds one `ContentRoute` service per `content_system.section_resolver` × `content_system.output_format`
- Specification resolution and layout-entity loading are in the route, not the pipeline
- The format's two answers travel from the factory through the route into the pipeline. They are independent questions: decomposed and data render in FULL mode like the full format and differ only in collecting a value index
- No extension surface: decorating `AbstractContentRoute` is not offered
- Add no protected member, subscribed service or import to `StorefrontController` or another base plugin controllers extend. Check: does the change touch a plugin-extended base?
