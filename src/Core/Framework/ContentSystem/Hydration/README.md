# Hydration

Turns one stored element forest into the rendered forest it serves as: resolves each element's data, resolves what context every element received, and mints the rendered tree.

## Key Class

- `ElementLowering` - Entry point, owns the render step as a whole and the FULL/SKELETON distinction

## Render Layers

`ElementLowering::lower()` drives three layers, each answering one question about one thing:

1. **Data resolution**: `ElementDataResolver` runs ONE element's `DataRequirement`s and returns what they resolved to, keyed by requirement key. Each loader returns `ContentDataLoaderResult` with cache info. The walk over the whole forest lives in `ElementLowering` itself — each element before the elements under it, slot by slot. See DataLoader/.
2. **Context delivery resolution**: `ContextDeliveryResolver` walks the whole forest top-down and returns a `ContextDeliveryIndex` recording what every element received. It takes the collected loader values as an argument rather than resolving them itself. See DataContext/.
3. **Tree minting**: `RenderedTreeFactory` folds `RenderedElementFactory` over the stored forest bottom-up and returns `list<RenderedElement>`.

Data resolution MUST complete over the WHOLE forest before any distribution starts, because a provider may hand a loaded value on to a child. No layer writes into an element: each returns its values, and the rendered tree is minted from them.

In SKELETON mode no loader runs and no delivery is computed — the mint is handed an empty index and an empty loader-value map, and produces structure only. The traversal that shapes the tree is one code path in both modes.

`ContentElementHydrator` and `DataContext/DataContextResolver` are the pre-split two-phase implementation. Neither is on the serving path any more; nothing but their DI registrations references them.

## Subdirectories

- **[DataLoader/](DataLoader/README.md)** - Data fetching (`AbstractContentDataLoader` implementations)
- **[DataContext/](DataContext/README.md)** - Context distribution (`ContextDeliveryResolver`, `ContextDistributor`)
