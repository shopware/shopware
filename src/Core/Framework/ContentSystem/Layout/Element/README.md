# Element

Stored elements and `RenderedTreeEditor`, which edits a rendered forest. Elements nest via named slots, declare data requirements and context definitions, and are rebuilt rather than mutated.

## Guides

- [docs/authoring-elements.md](docs/authoring-elements.md) - The JSON shape of an element as a layout author writes it: fields, slots, and nesting.

## Key Classes

- `StoredElement` - The storage-side node, rebuilt rather than mutated: every edit returns a new instance through a `with*()` method. Its slots are `array<string, list<StoredElement>>`.
- `StoredValue` - Wraps each property value by variant; `StoredElement::jsonSerialize()` maps each property through its own `StoredValue::jsonSerialize()`.
- `RenderedTreeEditor` - Applies one per-element transformation across a whole rendered forest.
- `ElementIdRule` - The element-id value domain, stated once for the decode gate, the write descriptor and the published `ContentElementId` schema.

`RenderedElement`, the render-side counterpart, lives in [Rendering/](../../Rendering/README.md) with the classes that create it. What each of the two models carries, and which one a name is about, is set out in [../../docs/stored-and-rendered.md](../../docs/stored-and-rendered.md).

## Rendered Element Keys

`Rendering/RenderedElementFactory` decides which keys a rendered element carries:

- declared authored properties (every declared type except a single-FQCN reference, unions and bare `object` included) carrying the stored value and skipped when that value is the null variant
- `dataRequirements[$key]` keys carrying the resolved loader value
- the keys context was actually delivered under
- stored keys that a parent's `KeyedDistributionConfig` names, excluding a declared reference property

The rendered property map draws no distinction between a static, a loaded and a context-provided value; the origin of each value is recorded separately in `Rendering/ElementMintResult`, collected by `LoweringResult`. A rendered element's slots are `array<string, list<RenderedElement>>`. A `RenderedTreeFinalizationEvent` listener may hand back elements changed through `withProperty()` / `withProperties()`, and `ContentPipeline` carries that replacement forward rather than the tree it dispatched.

## Translatable Properties

A property whose element type declares `translatable: true` stores a language map rather than a bare value. Its `StoredValue` is the map variant, keyed by language id in lowercase UUID hex, and every entry value matches the declared primitive. The entry under `Defaults::LANGUAGE_SYSTEM` is the system-language entry. Every language chain a `SalesChannelContext` is built with ends at the system language, so that entry resolves on every request while any other entry may not. A required translatable property counts as filled only through it. The map carries at least one entry. An untranslated language has no key, and a property with no translation at all is absent from `properties()`.

The map is the shape every stored-side reader sees, the admin round-trip included. Serving does not: language reduction, a pass of `Layout/Scaffolding/StoredTreePreparer`, replaces each such map with the value of the first entry along the request's language chain, verbatim. A map with no chain entry [serves as an absent property](../../docs/principles/translation.md#language-reduction-happens-on-the-server-and-nothing-downstream-receives-a-language-map). Neither rendering mode puts a language map on the wire. Where that pass sits among the preparation passes is owned by [../../docs/pipeline-steps.md](../../docs/pipeline-steps.md).

## Editing a Rendered Forest

`RenderedTreeEditor::mapNodes()` visits every node of the forest exactly once, slot children before their parent. A node whose slot map is non-empty reaches the mapper as a copy carrying the already-mapped children. Only a node whose slot map is empty is handed over as the instance itself. The branch tests the map, not the child count. An element declaring a slot that currently holds no children still has a non-empty slot map, so it is re-created like any other parent. Whatever the mapper returns is what ends up in the tree. The editor keeps it verbatim rather than rebuilding it afterwards, so a mapper may return a separately constructed replacement. Elements a mapper introduces are not themselves visited.

Nothing is edited in place. That is `RenderedElement`'s doing rather than the editor's: the class is `final readonly`, so a mapper has no way to mutate what it was handed.

A `RenderedTreeFinalizationEvent` listener that has a rule for a single element hands it here instead of writing the recursion itself.

## Context and Data

Elements provide/consume context via string keys matched between `ContextProvider` and `ContextConsumer`; context flows only between adjacent elements ([context-wiring.md](../../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements)). See Context/ for definitions and Rendering/ for distribution.

Data requirements declare external data via `DataRequirement` objects. See Hydration/DataLoader/ for loaders.

## Subdirectories

- **[Context/](Context/README.md)** - ContextProvider, ContextConsumer, ContextDefinitions
- **DataRequirement/** - DataRequirement structure
- **[Style/](Style/README.md)** - Universal per-breakpoint style options (alignment, span, spacing, display) settable on every element
