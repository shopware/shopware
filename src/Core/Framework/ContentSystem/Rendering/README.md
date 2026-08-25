# Rendering

Turns one stored element forest into the rendered forest it serves as: resolves each element's data, resolves what context every element received, and mints the rendered tree. The wiring step that precedes it — context-wiring validation and redistribute derivation, both still on stored elements — lives here too, in `WiringPlanner`.

## Key Classes

- `WiringPlanner` - Runs before the render step: validates the stored forest's context wiring on the pre-prune forest, then derives the redistribute broadcast providers on the pruned tree and returns that tree. `ContentPipeline` is its sole production caller
- `ElementLowering` - Entry point, owns the render step as a whole and the FULL/SKELETON distinction
- `ContextDeliveryResolver` - Walks the stored forest top-down and returns a `ContextDeliveryIndex`
- `ContextDistributor` - The rule for ONE parent and its direct children: computes what each child receives and returns it
- `ContextDeliveryIndex` / `ContextDelivery` - What every element of that forest received, by element id; computed once, read back afterwards

## Render Layers

`ElementLowering::lower()` drives three layers, each answering one question about one thing:

1. **Data resolution**: `ElementDataResolver` runs ONE element's `DataRequirement`s and returns what they resolved to, keyed by requirement key. Each loader returns `ContentDataLoaderResult` with cache info. The walk over the whole forest lives in `ElementLowering` itself — each element before the elements under it, slot by slot. See ../Hydration/DataLoader/.
2. **Context delivery resolution**: `ContextDeliveryResolver` walks the whole forest top-down and returns a `ContextDeliveryIndex` recording what every element received. It takes the collected loader values as an argument rather than resolving them itself.
3. **Tree minting**: `RenderedTreeFactory` folds `RenderedElementFactory` over the stored forest bottom-up and returns a `LoweringResult`: the rendered forest plus the provenance recorded for every property key in it.

Data resolution MUST complete over the WHOLE forest before any distribution starts, because a provider may hand a loaded value on to a child. No layer writes into an element: each returns its values, and the rendered tree is minted from them.

In SKELETON mode no loader runs and no delivery is computed — the mint is handed an empty index and an empty loader-value map, and produces structure only. The traversal that shapes the tree is one code path in both modes.

## Distribution

`ContextDistributor` distributes context ONLY to direct children — never recursive. Multi-level requires explicit re-providing (`acceptsContext` + `providesContext`). The forest walk is separate and belongs to `ContextDeliveryResolver`; it runs top-down, so a container that receives context and re-provides it distributes what it was given.

The five strategies `ContextDistributor` dispatches on are declared in `Layout/Element/Context/Distribution/`: Broadcast, Indexed, Keyed, Sliced, Iterator. What each one means, and the context-flow rules bounding how far a distributed context reaches, are owned by [Layout/Element/Context/docs/distribution-strategies.md](../Layout/Element/Context/docs/distribution-strategies.md).
