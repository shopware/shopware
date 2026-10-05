## Constraints

- Complete data resolution over the WHOLE forest before any context delivery starts. Check: does delivery start before the forest-wide loads finish? See [Preparation and resolution run in one fixed order](../docs/principles/context-wiring.md#preparation-and-resolution-run-in-one-fixed-order)
- Pass root-ambient context to the render step as an argument. Check: does a render step read root context off a node? See [Context flows only between adjacent elements](../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements)
- Create each `LoaderValueIdentity` in `ElementDataResolver`, at the load: the resolved `LoaderInputs` do not outlive the call, and `producedFingerprint` must describe the value the LOADER returned, not the value the response finally carries. Check: does identity creation leave `ElementDataResolver`? See [LoaderValueIdentity.php](../Output/Index/LoaderValueIdentity.php)
- Yield a PRESENT `null` when a loader found nothing. Check: is the loader-value map read with `isset` or `??`? See [A null in the rendered map means a resolution found nothing](../docs/principles/rendering.md#a-null-in-the-rendered-map-means-a-resolution-found-nothing)
- Record provenance in the write that produces the value, inside `RenderedElementFactory::create()`, so a contested key is recorded for the member that WON it, and pin the tier write order with a provenance assertion: reordering the loops recategorises index entries while every value stays correct. Check: is provenance recorded outside that write? See [Also true by construction](../docs/principles/context-wiring.md#also-true-by-construction)

## Where to look

- Why the render path's rules hold, and what was not chosen: [rendering.md](../docs/principles/rendering.md)
- The render layers, loader input sources, the loader-value record and the skeleton-mode split: [README.md](README.md#render-layers)
- Context distribution for one parent and its direct children: [README.md](README.md#distribution)
- Provider, consumer, alias and redistribute semantics and their enforcing sites: [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md)
