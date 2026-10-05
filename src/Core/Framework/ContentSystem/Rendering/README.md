# Rendering

Turns one stored element forest into the rendered forest it serves as: resolves each element's data, resolves what context every element received, and creates the rendered tree. The wiring step that precedes it (context-wiring validation and the derivation in `WiringPlanner::plan()`, both still on stored elements) lives here too.

## Render Layers

`ElementLowering::lower()` drives three layers, each answering one question about one thing:

1. **Data resolution**: `ElementDataResolver` runs ONE element's `DataRequirement`s and returns one `ResolvedLoaderValue` per requirement key: the loader's value and the `Output/Index/LoaderValueIdentity` it dedups by. `LoaderInputResolver` builds the requirement's `LoaderInputs` from its input source element before `load()`: the element itself, or for page-level requirements the [virtual root](../docs/principles/README.md#glossary), whose stored properties are the specification's placeholder values. Each loader returns `ContentDataLoaderResult` with cache info. The walk over the whole forest lives in `ElementLowering`, each element before the elements under it, slot by slot. See [Hydration/DataLoader/AGENTS.md](../Hydration/DataLoader/AGENTS.md).
2. **Context delivery resolution**: `ContextDeliveryResolver` walks the whole forest top-down and returns a `ContextDeliveryIndex` recording what every element received. It takes the loader values without their identities as an argument rather than resolving them itself.
3. **Tree creation**: `RenderedTreeFactory` folds `RenderedElementFactory` over the stored forest bottom-up, from the resolved loader values and the `ContextDeliveryIndex`. It returns a `LoweringResult`: the rendered forest plus each property key's provenance, the member that produced it.

`ElementLowering` owns the mode split. In SKELETON mode no loader runs and no delivery is computed. The tree factory gets an empty index and an empty loader-value map, so it records structure only and no provenance, in [one code path in both modes](../docs/principles/rendering.md#structure-is-a-function-independent-of-rendering-mode).

## Distribution

`ContextDistributor` is the rule for one parent and its direct children. [Root context](../docs/principles/README.md#glossary) is an argument to `ContextDeliveryResolver::resolve()` and fills each element's `ConsumerScope::Root` consumers directly. The forest walk is separate, belongs to `ContextDeliveryResolver` and runs top-down. See [Context flows only between adjacent elements](../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements).

The strategies `ContextDistributor` dispatches on are declared in `Layout/Element/Context/Distribution/` and explained in [Layout/Element/Context/docs/distribution-strategies.md](../Layout/Element/Context/docs/distribution-strategies.md).
