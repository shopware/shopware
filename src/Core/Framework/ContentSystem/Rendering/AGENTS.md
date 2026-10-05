## Constraints

- Complete data resolution over the WHOLE forest before any context delivery starts. Check: does delivery start before the forest-wide loads finish? See [Preparation and resolution run in one fixed order](../docs/principles/context-wiring.md#preparation-and-resolution-run-in-one-fixed-order)
- Pass root context to the render step as an argument. Check: does a render step read root context off a node? See [Context flows only between adjacent elements](../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements)
- Create each `LoaderValueIdentity` in `ElementDataResolver`, at the load. Check: does identity creation leave `ElementDataResolver`? See [Also true by construction](../docs/principles/data-loading.md#also-true-by-construction)
- Yield a PRESENT `null` when a loader found nothing. Check: is the loader-value map read with `isset` or `??`? See [A null in the `properties` of a `RenderedElement` means a resolution found nothing](../docs/principles/rendering.md#a-null-in-the-properties-of-a-renderedelement-means-a-resolution-found-nothing)
- Record `ValueProvenance` in the write that produces the value. Check: does the change record it outside `RenderedElementFactory::create()` or reorder its property-writing loops? `RenderedElementFactoryTest::testProvenanceOfAContestedKeyNamesTheWinningMember` pins the order. See [Also true by construction](../docs/principles/context-wiring.md#also-true-by-construction)

## Where to look

- Why the render path's rules hold, and what was decided against: [rendering.md](../docs/principles/rendering.md)
- The render layers, loader input sources, the loader-value record and the SKELETON mode split: [README.md](README.md#render-layers)
- Context distribution for one parent and its direct children: [README.md](README.md#distribution)
- Provider, consumer, alias and redistribute semantics and their enforcing sites: [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md)
