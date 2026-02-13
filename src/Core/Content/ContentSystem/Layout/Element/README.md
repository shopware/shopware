# Element

ContentElement tree structure. Elements nest via named slots, traverse via visitor pattern, declare data requirements and context definitions.

## Key Class

- `ContentElement` - Tree aggregate root: `id`, `component`, `properties`, `slots` (`array<string, SlotContent>`), `dataRequirements`, `contextDefinitions`

## Traversal

`traverse(ElementVisitor)` walks tree depth-first: `enter()` before children, `leave()` after. `allSlotElements()` is a generator for memory-efficient traversal across all slots.

## Context and Data

Elements provide/consume context via string keys matched between `ContextProvider` and `ContextConsumer`. Context flows down tree only. See Context/ for definitions and Hydration/DataContext/ for distribution.

Data requirements declare external data via `DataRequirement` objects (`key`, `source`, `config`). See Hydration/DataLoader/ for loaders.

## Subdirectories

- **Context/** - ContextProvider, ContextConsumer, ContextDefinitions
- **DataRequirement/** - DataRequirement structure
- **Slot/** - SlotContent container
- **Visitor/** - ElementVisitor interface and implementations
