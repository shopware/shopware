# Element

The storage-side element model, and the edit idiom for the rendered one. Elements nest via named slots, declare data requirements and context definitions, and are rebuilt rather than mutated.

## Guides

- [docs/authoring-elements.md](docs/authoring-elements.md) - The JSON shape of an element as a layout author writes it: fields, slots, and nesting.

## Key Classes

- `StoredElement` - The storage-side node, rebuilt rather than mutated: every edit returns a new instance through a `with*()` method. Its slots are `array<string, list<StoredElement>>`.
- `StoredValue` - Wraps each property value by variant; `StoredElement::jsonSerialize()` maps each property through its own `StoredValue::jsonSerialize()`.
- `RenderedTreeEditor` - Applies one per-element transformation across a whole rendered forest.

`RenderedElement`, the render-side counterpart, lives in [Rendering/](../../Rendering/README.md) with the classes that create it. What each of the two models carries, and which one a name is about, is set out in [../../docs/stored-and-rendered.md](../../docs/stored-and-rendered.md).

## Rendered Element Keys

`Rendering/RenderedElementFactory` decides which keys a rendered element carries:

- declared authored properties (every declared type except a single-FQCN reference, unions and bare `object` included) carrying the stored value and skipped when that value is the null variant
- `dataRequirements[$key]` keys carrying the resolved loader value
- the keys context was actually delivered under
- stored keys a parent's distribution config names, excluding a declared reference property

The rendered property map draws no distinction between a static, a loaded and a context-provided value; provenance is recorded separately in `Rendering/ElementMintResult`, collected by `LoweringResult`. A rendered element's slots are `array<string, list<RenderedElement>>`. A `RenderedTreeFinalizationEvent` listener may hand back elements changed through `withProperty()` / `withProperties()`, and `ContentPipeline` carries that replacement forward rather than the tree it dispatched.

## Editing a Rendered Forest

`RenderedTreeEditor::mapNodes()` visits every node of the forest exactly once, slot children before their parent. A node whose slot map is non-empty reaches the mapper as a copy carrying the already-mapped children. Only a node whose slot map is empty is handed over as the instance itself. The branch tests the map, not the child count. An element declaring a slot that currently holds no children still has a non-empty slot map, so it is re-created like any other parent. Whatever the mapper returns is what ends up in the tree. The editor keeps it verbatim rather than rebuilding it afterwards, so a mapper may return a separately constructed replacement. Elements a mapper introduces are not themselves visited.

Nothing is edited in place. That is `RenderedElement`'s doing rather than the editor's: the class is `final readonly`, so a mapper has no way to mutate what it was handed.

It is the whole-tree half of the rendering extension idiom, aimed at third-party listeners. A `RenderedTreeFinalizationEvent` listener that has a rule for a single element hands it here instead of writing the recursion itself.

## Context and Data

Elements provide/consume context via string keys matched between `ContextProvider` and `ContextConsumer`; context flows only between adjacent elements ([context-wiring.md](../../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements)). See Context/ for definitions and Rendering/ for distribution.

Data requirements declare external data via `DataRequirement` objects. See Hydration/DataLoader/ for loaders.

## Subdirectories

- **[Context/](Context/README.md)** - ContextProvider, ContextConsumer, ContextDefinitions
- **DataRequirement/** - DataRequirement structure
- **[Style/](Style/README.md)** - Universal per-breakpoint style options (alignment, span, spacing, display) settable on every element
