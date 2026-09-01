# Rendering

Turns one stored element forest into the rendered forest it serves as: resolves each element's data, resolves what context every element received, and mints the rendered tree. The wiring step that precedes it — context-wiring validation and redistribute derivation, both still on stored elements — lives here too, in `WiringPlanner`.

## Render Layers

`ElementLowering::lower()` drives three layers, each answering one question about one thing:

1. **Data resolution**: `ElementDataResolver` runs ONE element's `DataRequirement`s and returns what they resolved to, keyed by requirement key. Each loader returns `ContentDataLoaderResult` with cache info. The walk over the whole forest lives in `ElementLowering` itself — each element before the elements under it, slot by slot. See [Hydration/DataLoader/AGENTS.md](../Hydration/DataLoader/AGENTS.md).
2. **Context delivery resolution**: `ContextDeliveryResolver` walks the whole forest top-down and returns a `ContextDeliveryIndex` recording what every element received. It takes the collected loader values as an argument rather than resolving them itself.
3. **Tree minting**: `RenderedTreeFactory` folds `RenderedElementFactory` over the stored forest bottom-up and returns a `LoweringResult`: the rendered forest plus the provenance recorded for every property key in it.

In SKELETON mode no loader runs and no delivery is computed — the mint is handed an empty index and an empty loader-value map, and produces structure only. The traversal that shapes the tree is one code path in both modes.

## Distribution

`ContextDistributor` is the rule for one parent and its direct children; reaching further down takes explicit re-providing (`acceptsContext` + `providesContext`). The forest walk is separate and belongs to `ContextDeliveryResolver`; it runs top-down, so a container that receives context and re-provides it distributes what it was given.

The strategies `ContextDistributor` dispatches on are declared in `Layout/Element/Context/Distribution/`. What each one means, and the context-flow rules bounding how far a distributed context reaches, are owned by [Layout/Element/Context/docs/distribution-strategies.md](../Layout/Element/Context/docs/distribution-strategies.md).
