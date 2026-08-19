> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Data resolution MUST complete over the WHOLE forest before any context-delivery resolution starts — a provider may hand a loaded value on to a child (`ElementLowering::lower()` is what orders the two)
- `ElementDataResolver` resolves each requirement's `LoaderInputs` (via `LoaderInputResolver`, from the loader's `configSpecification()`, the requirement's config, and the element's unwrapped `StoredElement::properties()`) before calling `load()` — loaders never read the element
- Nothing writes into an element: `ElementDataResolver::resolve()` returns values keyed by requirement key, `ContextDeliveryResolver::resolve()` returns a `ContextDeliveryIndex`, and `RenderedTreeFactory` mints the tree from both
- A requirement whose loader found nothing yields a PRESENT `null`; `RenderedElementFactory` reads the map with `array_key_exists`, so present-null renders as null while an absent key never renders at all
- `ElementLowering::lower()` returns `list<RenderedElement>` and owns the mode split: SKELETON resolves no data, computes no deliveries, and mints from an empty index and an empty loader-value map
- Uncacheable loader result disables page caching entirely via `RenderingCacheContext::disable()`
