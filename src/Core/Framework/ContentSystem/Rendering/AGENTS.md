> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Data resolution MUST complete over the WHOLE forest before any context-delivery resolution starts — a provider may hand a loaded value on to a child (`ElementLowering::lower()` is what orders the two)
- `ElementDataResolver` resolves each requirement's `LoaderInputs` (via `LoaderInputResolver`, from the loader's `configSpecification()`, the requirement's config, and the element's unwrapped `StoredElement::properties()`) before calling `load()`
- Nothing writes into an element: `ElementDataResolver::resolve()` returns a `ResolvedLoaderValue` per requirement key (the loader's value paired with the `Output/Index/LoaderValueIdentity` that value dedups by), `ContextDeliveryResolver::resolve()` returns a `ContextDeliveryIndex`, and `RenderedTreeFactory` mints the tree from both
- The identity is minted at the load, in `ElementDataResolver`, because two of its four components exist nowhere else: the resolved `LoaderInputs` do not outlive the call, and `producedFingerprint` must describe the value the LOADER returned rather than the value the response finally carries. Context distribution sees the plain values — dataflow does not care where a value came from
- A requirement whose loader found nothing yields a PRESENT `null`; `RenderedElementFactory` reads the map with `array_key_exists`, so present-null renders as null while an absent key never renders at all
- `ElementLowering::lower()` returns a `LoweringResult` (the rendered forest plus the provenance of every property key in it, keyed by element id) and owns the mode split: SKELETON resolves no data, computes no deliveries, mints from an empty index and an empty loader-value map, and therefore records no provenance
- Provenance is recorded by the write that produces the value, inside `RenderedElementFactory::create()`, so a contested key is filed under the member that WON it. The tier write order is therefore load-bearing twice — for the value and for the category — and a provenance assertion guards it, not only a value assertion: reordering the loops would recategorise index entries while every value stayed correct
- Distribution is direct-children-only — never recursive
- Property alias applied after path resolution, not before
- `consumerAlias: null` (default) makes the provider's own context key the consumer key it matches against; that key becomes the property name only for a matched consumer that declares no `propertyAlias`
- Redistribution: `redistribute: true` → auto-generates broadcast provider before the render step, in `ContentPipeline`'s redistribute-derivation step
- `ContextDeliveryResolver` owns the forest walk (top-down, required for chained re-providing) and `ContextDistributor` owns the rule for one parent and its direct children; neither writes into an element — deliveries come back in a `ContextDeliveryIndex`
