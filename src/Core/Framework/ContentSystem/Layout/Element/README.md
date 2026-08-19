# Element

ContentElement tree structure. Elements nest via named slots, traverse via visitor pattern, declare data requirements and context definitions.

## Guides

- [docs/authoring-elements.md](docs/authoring-elements.md) - The JSON shape of an element as a layout author writes it: fields, slots, and nesting.

## Key Class

- `ContentElement` - Tree aggregate root: `id`, `component`, `properties`, `slots` (`array<string, SlotContent>`), `dataRequirements`, `contextDefinitions`

## Traversal

`traverse(ElementVisitor)` walks tree depth-first: `enter()` before children, `leave()` after. `allSlotElements()` is a generator for memory-efficient traversal across all slots.

## Context and Data

Elements provide/consume context via string keys matched between `ContextProvider` and `ContextConsumer`. Context flows down tree only. See Context/ for definitions and Hydration/DataContext/ for distribution.

Data requirements declare external data via `DataRequirement` objects (`key`, `source`, `config`). See Hydration/DataLoader/ for loaders.

## Element Lifecycle

ContentElement is a mutable object, but on the serving path its `properties` map arrives complete. What the map holds depends on the stage the element is looked at:

| Stage | `properties` contains | `dataRequirements` / `acceptsContext` |
|---|---|---|
| **Storage** (database JSON, via field serializer encode) | Static/config values only (scalars set at design time) | Loading and context instructions — how to fill FQCN-typed properties at runtime |
| **Rendered** (runtime, built by `Layout/Element/ContentElementLowering` out of the `RenderedElement`) | Static values AND loaded data merged together | Still present as metadata, read off the stored element; the render step has already used them to resolve the values |
| **API output** (`jsonSerialize()`, Store API response) | Same as rendered — full merged map. Skeleton format strips properties entirely | Serialized alongside properties in full format; absent in skeleton format |

The merge happens on the rendered side, in `Hydration/RenderedElementFactory`: a resolved loader value lands under its data requirement's key, and delivered context under the consumer key or its `propertyAlias`. `ContentElementLowering` copies that finished map onto the `ContentElement` it builds and adds nothing. In the resulting map there is no distinction between a property that was set statically at design time and one that was loaded by a data loader or received via context.

Internally, `ContentElement` stores properties in two maps (`structProperties` for Struct instances, `nonStructProperties` for scalars/arrays). This is a serialization optimization — `jsonSerialize()` merges them back into a single `properties` key. The split is invisible to consumers.

## Subdirectories

- **[Context/](Context/README.md)** - ContextProvider, ContextConsumer, ContextDefinitions
- **DataRequirement/** - DataRequirement structure
- **Slot/** - SlotContent container
- **[Style/](Style/README.md)** - Universal per-breakpoint style options (alignment, span, spacing, display) settable on every element
- **Visitor/** - ElementVisitor interface and implementations
