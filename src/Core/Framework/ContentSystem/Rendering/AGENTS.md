## Constraints

- Complete forest/page loading, then expand captured inline tokens, then deliver context and perform keyed selection. Check: children and distribution keys read expanded text, and placeholder-inserted tokens stay literal. See [Preparation and resolution run in one fixed order](../docs/principles/context-wiring.md#preparation-and-resolution-run-in-one-fixed-order)
- Pass root context to the render step as an argument. Check: does a render step read root context off a node? See [Context flows only between adjacent elements](../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements)
- Create each `LoaderValueIdentity` in `ElementDataResolver`, at the load. Check: does identity creation leave `ElementDataResolver`? See [Also true by construction](../docs/principles/data-loading.md#also-true-by-construction)
- For ordinary unmapped properties, yield a PRESENT `null` when a loader found nothing. Preserve the explicit mapping fallback/omission outcomes documented in [Mapping/README.md](../Mapping/README.md#stored-mappings-and-rendering). Check: is the loader-value map read with `isset` or `??`? See [A null in the `properties` of a `RenderedElement` means a resolution found nothing](../docs/principles/rendering.md#a-null-in-the-properties-of-a-renderedelement-means-a-resolution-found-nothing)
- Record `ValueProvenance` in the write that produces the value. Check: does the change record it outside `RenderedElementFactory::create()` or reorder its property-writing loops? `RenderedElementFactoryTest::testProvenanceOfAContestedKeyNamesTheWinningMember` pins the order. See [Also true by construction](../docs/principles/context-wiring.md#also-true-by-construction)

## Where to look

- Why the render path's rules hold, and what was decided against: [rendering.md](../docs/principles/rendering.md)
- The render layers, loader input sources, the loader-value record and the SKELETON mode split: [README.md](README.md#render-layers)
- Context distribution for one parent and its direct children: [README.md](README.md#distribution)
- Provider, consumer, alias and redistribute semantics and their enforcing sites: [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md)
- Mapping admission, caller-owned cache context, shared expansion budget, fallback and omission, and request error boundaries: [mapping principles](../docs/principles/data-mapping.md), [Mapping/README.md](../Mapping/README.md)
