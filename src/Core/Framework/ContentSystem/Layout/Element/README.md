# Element

The storage-side element model, and the edit idiom for the rendered one. Elements nest via named slots, declare data requirements and context definitions, and are rebuilt rather than mutated.

## Guides

- [docs/authoring-elements.md](docs/authoring-elements.md) - The JSON shape of an element as a layout author writes it: fields, slots, and nesting.

## Key Classes

- `StoredElement` - The storage-side node, rebuilt rather than mutated: every edit returns a new instance through a `with*()` method.
- `StoredValue` - Wraps each property value by variant, so a hydrated entity can never sit in the stored tree by type rather than by convention.
- `RenderedTreeEditor` - Applies one per-element transformation across a whole rendered forest.

`RenderedElement`, the render-side counterpart, lives in [Rendering/](../../Rendering/README.md) with the classes that mint it. What each of the two models carries, and which one a name is about, is set out in [../../docs/stored-and-rendered.md](../../docs/stored-and-rendered.md).

## Translatable Properties

A property whose element type declares `translatable: true` stores one string per language rather than a bare value. Its `StoredValue` is the map variant, keyed by language id in lowercase UUID hex, and every entry value is a string variant. `Defaults::LANGUAGE_SYSTEM` is the anchor entry: it terminates every language chain a `SalesChannelContext` is built with, so an anchor entry resolves on every request while any other entry may not, and a required translatable property counts as filled only through it. The map carries at least one entry — an untranslated language simply has no key, and a property with no translation at all is absent from `properties()`.

The map is the shape every stored-side reader sees, the admin round-trip included. Serving does not: language reduction, the first pass of `Layout/Scaffolding/StoredTreePreparer`, replaces each such map with the value of the first entry along the request's language chain, verbatim, before any other preparation step runs. A map carrying no chain entry reduces to the null variant, which the mint skips, so the property behaves as unset. Everything downstream of that pass — placeholder resolution, the lowering, the mint, every encoder — sees a plain string. Reduction runs in FULL mode only: SKELETON skips it and its mint carries no property value at all, so neither mode puts a language map on the wire.

## Editing a Rendered Forest

`RenderedTreeEditor::mapNodes(array $tree, callable $mapper)` visits every node of the forest exactly once, slot children before their parent. A node whose slot map is non-empty reaches the mapper as a copy carrying the already-mapped children (`$mapper($element->withSlots($slots))`); only a node whose slot map is empty is handed over as the instance itself. The branch tests the map, not the child count — an element declaring a slot that currently holds no children still has a non-empty slot map, so it is re-created like any other parent. Whatever the mapper returns is what ends up in the tree — the editor keeps it verbatim rather than rebuilding it afterwards, so a mapper may return a separately constructed replacement. Elements a mapper introduces are not themselves visited — one pass is one pass.

Nothing is edited in place, but that is `RenderedElement`'s doing rather than the editor's: the class is `final readonly`, so a mapper has no way to mutate what it was handed.

It is the whole-tree half of the rendering extension idiom, aimed at third-party listeners: a `RenderedTreeFinalizationEvent` listener that has a rule for a single element hands it here instead of writing the recursion itself.

## Context and Data

Elements provide/consume context via string keys matched between `ContextProvider` and `ContextConsumer`. Context flows down tree only. See Context/ for definitions and Rendering/ for distribution.

Data requirements declare external data via `DataRequirement` objects (`key`, `source`, `config`). See Hydration/DataLoader/ for loaders.

## Subdirectories

- **[Context/](Context/README.md)** - ContextProvider, ContextConsumer, ContextDefinitions
- **DataRequirement/** - DataRequirement structure
- **[Style/](Style/README.md)** - Universal per-breakpoint style options (alignment, span, spacing, display) settable on every element
