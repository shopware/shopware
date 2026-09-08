> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Two element models

The storage/render split (see
[stored-and-rendered.md](../../docs/stored-and-rendered.md)) is the whole
model set:

- `StoredElement` — the storage model: what the admin edits, what the storage
  column holds. Storage, validation, mutation and diagnostics all run on it
  directly. `final readonly`; every edit returns a new instance via a `with*()`
  method. Property values are wrapped in `StoredValue`, never a raw PHP scalar,
  so a hydrated object can never sit in the stored tree by type rather than by
  convention. Slots are `array<string, list<StoredElement>>`.
- `RenderedElement` — the render model: what a response body and the Twig
  components read. It lives in `Rendering/`, not here. `final readonly`, and
  deliberately not a `Struct`. It carries no data requirements, no context
  wiring and no attribution, and its property values are raw unwrapped PHP
  values, because the Twig filter chain needs entities unwrapped.
  `Rendering/RenderedElementFactory` mints one and
  `Rendering/RenderedTreeFactory` mints the forest, both driven by
  `Rendering/ElementLowering`. Slots are
  `array<string, list<RenderedElement>>`.

`RenderedTreeEditor` is the one class in this directory that touches the
render model — see [Editing a Rendered Forest](README.md#editing-a-rendered-forest)
for the traversal contract and its slot-map constraint.

## Constraints

- `StoredElement::property()` returns `null` for an absent key and never throws. An authored null is a PRESENT value whose variant is null, so it comes back as a `StoredValue` answering true to `isNull()` — absent and present-null are distinct states, and a bare null check conflates them
- `StoredElement::properties()` never changes on a given instance — the map is private and `withProperties()` returns a new element. Its content is not only what an author typed: `Layout/LayoutDefaultSeeder` seeds a type's primitive defaults at the write boundary, and `Layout/Scaffolding/StoredTreePreparer` collapses each translatable property's language map to the request language and then substitutes placeholders into string values, both during preparation and in FULL mode only. Every value is wrapped in a `StoredValue`
- A property whose type declares `translatable: true` holds the MAP variant, not the string variant: one string per language keyed by language id in lowercase UUID hex, anchored on `Defaults::LANGUAGE_SYSTEM` ([README.md](README.md#translatable-properties)). Never write a bare string under such a key — the strict write rejects it (400) and reduction rejects it (`TRANSLATION_SHAPE_INVALID`, 500). `PropertyType::admits()` is the one predicate that answers whether a stored value fits its declared type, translatable or not
- `Rendering/RenderedElementFactory` decides which keys the MINT produces: declared authored properties — every declared type except a single-FQCN reference, unions and bare `object` included — carrying the stored value, skipped when that value is the null variant, `dataRequirements[$key]` keys carrying the resolved loader value, the keys context was actually delivered under, and stored keys a parent's distribution config names — that last member excluding a declared reference property. Downstream is not closed: a `RenderedTreeFinalizationEvent` listener may hand back elements changed through `withProperty()` / `withProperties()`, and `ContentPipeline` carries that replacement forward rather than the tree it dispatched
- A rendered element's own property map draws no distinction between a static, a loaded and a context-provided value; provenance is recorded beside the mint instead, in `Rendering/ElementMintResult` and the `LoweringResult` that collects them
- `StoredElement::jsonSerialize()` maps each property through its own `StoredValue::jsonSerialize()`
- Skeleton output (`ContentSkeletonElement`) strips properties entirely — `id`, `component`, `slots` and `style` (the universal style rides the skeleton, omitted when empty), plus, on root elements only, the `apiAlias` `StructEncoder` appends
