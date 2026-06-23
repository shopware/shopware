# Element

ContentElement tree structure. Elements nest via named slots, traverse via visitor pattern, declare data requirements and context definitions.

## Key Class

- `ContentElement` - Tree aggregate root: `id`, `component`, `properties`, `slots` (`array<string, SlotContent>`), `dataRequirements`, `contextDefinitions`

## Traversal

`traverse(ElementVisitor)` walks tree depth-first: `enter()` before children, `leave()` after. `allSlotElements()` is a generator for memory-efficient traversal across all slots.

## Context and Data

Elements provide/consume context via string keys matched between `ContextProvider` and `ContextConsumer`. Context flows down tree only. See Context/ for definitions and Hydration/DataContext/ for distribution.

Data requirements declare external data via `DataRequirement` objects (`key`, `source`, `config`). See Hydration/DataLoader/ for loaders.

## Element Lifecycle

ContentElement is a mutable object whose `properties` map changes between lifecycle stages:

| Stage | `properties` contains | `data_requirements` / `accepts_context` |
|---|---|---|
| **Storage** (database JSON, via field serializer encode) | Static/config values only (scalars set at design time) | Loading and context instructions — how to fill FQCN-typed properties at runtime |
| **Post-hydration** (runtime, after `ContentElementHydrator`) | Static values AND loaded data merged together | Still present as metadata; hydrator has already used them to populate properties |
| **API output** (`jsonSerialize()`, Store API response) | Same as post-hydration — full merged map. Skeleton format strips properties entirely | Serialized alongside properties in full format; absent in skeleton format |

The hydrator writes loaded data into `properties` using the data requirement's key: `$element->setProperty($key, $result->data)`. Context resolution does the same: `$consumer->setProperty($propertyKey, $data)`. After hydration, there is no distinction between a property that was set statically at design time and one that was loaded by a data loader or received via context.

Internally, `ContentElement` stores properties in two maps (`structProperties` for Struct instances, `nonStructProperties` for scalars/arrays). This is a serialization optimization — `jsonSerialize()` merges them back into a single `properties` key. The split is invisible to consumers.

## Subdirectories

- **Context/** - ContextProvider, ContextConsumer, ContextDefinitions
- **DataRequirement/** - DataRequirement structure
- **Slot/** - SlotContent container
- **Visitor/** - ElementVisitor interface and implementations
