# ContentSystem Reference Documents

The module's reference material, one subject per file. The module overview is in
[../README.md](../README.md) and the symbol index in [../AGENTS.md](../AGENTS.md).

- [../NAMING.md](../NAMING.md) - How classes in this module are named, routing on to [stored-and-rendered.md](stored-and-rendered.md) (which of the two element models a class is about) and [role-suffixes.md](role-suffixes.md) (what each role suffix promises)
- [pipeline-steps.md](pipeline-steps.md) - The order `ContentPipeline::load()` runs its steps in, and the orderings inside preparation that are load-bearing
- [layout-write-gates.md](layout-write-gates.md) - What a `content_layout` write passes through before the DAL admits it, and what a delete is refused by
- [layout-mutation.md](layout-mutation.md) - The two structural-edit runners and what they guarantee about content
- [binding-specifications.md](binding-specifications.md) - What one binding specification declares, and the two modes it is applied in
- [element-styles.md](element-styles.md) - The universal style options and where an element's `style` is stored, validated and served
- [introspection-endpoints.md](introspection-endpoints.md) - The registries, compiler passes, and `/api/_info/` endpoints that publish the module's own shape
- [client-defect-codes.md](client-defect-codes.md) - Which error codes mark a defect in client-supplied layout input rather than an internal fault
- [product-detail-page.md](product-detail-page.md) - A worked layout combining entity rendering, data loading, and context distribution
- [service-tags-and-types.md](service-tags-and-types.md) - The DI tags and the base classes, value objects, enums, and events an extension uses
- [extending.md](extending.md) - The six extension mechanisms and where each one is authored
- [data-flow.md](data-flow.md) - A diagram of the rendering pipeline's data flow
