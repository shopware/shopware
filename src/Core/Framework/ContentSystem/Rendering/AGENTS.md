## Navigation

- Why the render path's rules hold, and what was not chosen: [rendering.md](../docs/principles/rendering.md)

## Constraints

- Data resolution MUST complete over the WHOLE forest before any context-delivery resolution starts
- `ElementDataResolver` resolves each requirement's `LoaderInputs` (via `LoaderInputResolver`, from the loader's `configSpecification()`, the requirement's config, and the unwrapped `StoredElement::properties()` of the INPUT SOURCE element) before calling `load()`. For an element's own requirements the input source is that element; for the page-level requirements it is the virtual-root wrapper, whose stored properties are the specification's placeholder values
- Root-ambient context is an explicit input to the render step, never a tree lookup. It reaches an element only through that element's own `ConsumerScope::Root` consumers
- A `ResolvedLoaderValue`, one per requirement key, pairs the loader's value with the `Output/Index/LoaderValueIdentity` that value dedups by, and `RenderedTreeFactory` creates the tree from those values and the `ContextDeliveryIndex`
- The identity is created at the load, in `ElementDataResolver`, because two of its four components exist nowhere else: the resolved `LoaderInputs` do not outlive the call, and `producedFingerprint` must describe the value the LOADER returned rather than the value the response finally carries. Context distribution sees the plain values. Dataflow does not care where a value came from
- A requirement whose loader found nothing yields a PRESENT `null`; `RenderedElementFactory` reads the map with `array_key_exists`
- `ElementLowering` owns the mode split: SKELETON resolves no data, computes no deliveries, builds from an empty index and an empty loader-value map, and therefore records no provenance
- Provenance is recorded by the write that produces the value, inside `RenderedElementFactory::create()`, so a contested key is recorded for the member that WON it. The tier write order therefore matters twice, for the value and for the category, and a provenance assertion guards it, not only a value assertion: reordering the loops would recategorise index entries while every value stayed correct
- Provider, consumer, alias and redistribute semantics — and which sites enforce them — are owned by [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md) and its `docs/`; this directory executes those rules and restates none of them