## Navigation

- Why these constraints hold, and what was not chosen: [stored-model.md](../../docs/principles/stored-model.md)
- Why the value rules hold, and what was not chosen: [values.md](../../docs/principles/values.md)

## Two element models

The storage/render split (see
[stored-and-rendered.md](../../docs/stored-and-rendered.md)) is the whole
model set:

- `StoredElement` — the storage model: what the admin edits, what the storage
  column holds. Storage, validation, mutation and diagnostics all run on it
  directly. `final readonly`; every edit returns a new instance via a `with*()`
  method. Property values are wrapped in `StoredValue`, never a raw PHP scalar.
  Slots are `array<string, list<StoredElement>>`.
- `RenderedElement` — the render model: what a response body and the Twig
  components read. It lives in `Rendering/`, not here. `final readonly`, and
  deliberately not a `Struct`. Its property values are raw unwrapped PHP
  values.
  `Rendering/RenderedElementFactory` creates one and
  `Rendering/RenderedTreeFactory` creates the forest, both driven by
  `Rendering/ElementLowering`. Slots are
  `array<string, list<RenderedElement>>`.

`RenderedTreeEditor` is the one class in this directory that touches the
render model — see [Editing a Rendered Forest](README.md#editing-a-rendered-forest)
for the traversal contract and its slot-map constraint.

## Constraints

- Tell a present null from an absent key: `StoredElement::property()` returns `null` only for an absent key and never throws. An authored null comes back with `isNull()` true. Check: does the change test `property($key) === null`?
- `StoredElement::properties()` never changes on a given instance — the map is private and `withProperties()` returns a new element. Every value is wrapped in a `StoredValue`
- `Rendering/RenderedElementFactory` decides which keys it creates: declared authored properties — every declared type except a single-FQCN reference, unions and bare `object` included — carrying the stored value, skipped when that value is the null variant, `dataRequirements[$key]` keys carrying the resolved loader value, the keys context was actually delivered under, and stored keys a parent's distribution config names — that last member excluding a declared reference property. Downstream is not closed: a `RenderedTreeFinalizationEvent` listener may hand back elements changed through `withProperty()` / `withProperties()`, and `ContentPipeline` carries that replacement forward rather than the tree it dispatched
- A rendered element's own property map draws no distinction between a static, a loaded and a context-provided value; provenance is recorded separately, in `Rendering/ElementMintResult` and the `LoweringResult` that collects them
- `StoredElement::jsonSerialize()` maps each property through its own `StoredValue::jsonSerialize()`
- Skeleton output (`ContentSkeletonElement`) strips properties entirely and keeps `id`, `component`, `slots` and `style`. Style is omitted when empty.
- Keep the two element models apart: no wiring, data requirement or attribution on `RenderedElement`, no raw or hydrated value in a `StoredElement` property. Check: does the change put a member of one model on the other? `RenderedElementFactoryTest` pins the conversion between them.
- Change a stored element only through a `with*()` method such as `StoredElement::withSlots()`. Never add a setter, drop `readonly`, or rebuild one by hand. Check: does a `new StoredElement(` take its arguments from another element? Use a with-method.
- Reject an integer-castable map key, never coerce it: every property, slot, data-requirement and wiring map is string-keyed. Check: does the change build such a map from input without a constructor or decoder that throws `invalidMapKey`?
