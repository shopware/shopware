> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Three element models

Two of them are the storage/render split (see
[role-suffixes.md](../../docs/role-suffixes.md#the-two-model-roles)); the third
is the pre-split model they are replacing, still alive on the serving path:

- `StoredElement` — the storage model: what the admin edits, what the storage
  column holds. Storage, validation, mutation and diagnostics all run on it
  directly. `final readonly`; every edit returns a new
  instance via a `with*()` method. Property values are
  wrapped in `StoredValue`, never a raw PHP scalar, so a hydrated object can
  never sit in the stored tree by type rather than by convention. Slots are
  `array<string, list<StoredElement>>`.
- `RenderedElement` — the render model: what a response body and the Twig
  components read. `final readonly`, and deliberately not a `Struct`. It carries
  no data requirements, no context wiring and no attribution, and its property
  values are raw unwrapped PHP values, because the Twig filter chain needs
  entities unwrapped. `Rendering/RenderedElementFactory` mints one and
  `Rendering/RenderedTreeFactory` mints the forest, both driven by
  `Rendering/ElementLowering`. Slots are `array<string, list<RenderedElement>>`.
- `ContentElement` — the pre-split model: still `extends Struct` and mutable,
  and still the model the pipeline speaks from the bridge onward. Every
  pipeline step up to and including the render step — the preparation event,
  placeholder resolution, the virtual-root wrap, the partial prune, the wiring
  validation, the redistribute derivation and the render step itself — runs on
  stored elements; `ContentElementLowering` produces this model at the one seam
  that remains, taking a stored element together with the rendered element
  minted from it (see its class comment for that site and its removal
  condition). Slots are `array<string, SlotContent>`.

The mutation-oriented guidance below (`setProperty`, `AssignArrayTrait`, the
struct/non-struct split) is about `ContentElement` alone: `StoredElement` has no
setters, and it is fully formed before any `ContentElementLowering` touches it.

## Constraints

- `ContentElement` property getters return null for missing keys — never throw. Use `hasProperty()` or null coalescing
- `ContentElement::allSlotElements()` is a generator — do NOT convert with `iterator_to_array()`
- Don't use `assign()` from `AssignArrayTrait` on a `ContentElement` — corrupts the struct/non-struct property split
- `ContentElement` slots: `array<string, SlotContent>`, multiple elements per slot; `StoredElement` slots: `array<string, list<StoredElement>>`, same multiplicity
- `ContentElement` `properties` arrives complete from the bridge, which copies `RenderedElement::$properties` verbatim: in FULL mode that map already merges the declared primitive stored values (placeholders resolved), the resolved loader values and the delivered context; in SKELETON mode it is empty, because the mint carries no properties at all. `StoredElement::properties()` never changes after construction — it holds only the authored static values, each wrapped in a `StoredValue`
- `Rendering/RenderedElementFactory` decides which keys reach the rendered map, and the bridge adds none: declared primitives, `dataRequirements[$key]` keys carrying the resolved value, delivered context keys (`acceptsContext[$key]` or its `propertyAlias`), and stored keys a parent's distribution config names
- On a bridged element there is no distinction between a static, a loaded, and a context-provided property
- `ContentElement::jsonSerialize()` merges `structProperties` + `nonStructProperties` into one `properties` key; `StoredElement::jsonSerialize()` instead maps each property through its own `StoredValue::jsonSerialize()`
- Skeleton output (`ContentSkeletonElement`) strips properties entirely — only `id`, `component`, `slots`, and `style` (the universal style rides the skeleton, omitted when empty)
