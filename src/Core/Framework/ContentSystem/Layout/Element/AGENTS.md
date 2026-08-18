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
  entities unwrapped. Nothing produces one yet — the lowering that does arrives
  with the render layers. Slots are `array<string, list<RenderedElement>>`.
- `ContentElement` — the pre-split model: still `extends Struct` and mutable,
  and still the model the rendering pipeline speaks from its lowering onward.
  The pipeline's first steps — the preparation event and placeholder
  resolution — run on stored elements; `ContentElementLowering` produces this
  model at the one seam that remains (see its class comment for that site and
  its removal condition). Slots are `array<string, SlotContent>`.

The mutation-oriented guidance below (`setProperty`, `AssignArrayTrait`, the
struct/non-struct split, the storage → post-hydration property lifecycle) is
about `ContentElement` alone: `StoredElement` has no setters and never goes
through hydration — it is fully formed before any `ContentElementLowering`
touches it.

## Constraints

- `ContentElement` property getters return null for missing keys — never throw. Use `hasProperty()` or null coalescing
- `ContentElement::allSlotElements()` is a generator — do NOT convert with `iterator_to_array()`
- Don't use `assign()` from `AssignArrayTrait` on a `ContentElement` — corrupts the struct/non-struct property split
- `ContentElement` slots: `array<string, SlotContent>`, multiple elements per slot; `StoredElement` slots: `array<string, list<StoredElement>>`, same multiplicity
- `ContentElement` `properties` changes between stages: right after lowering, static values only, their placeholders already resolved in FULL mode and still verbatim in SKELETON mode; post-hydration, static + loaded data merged. `StoredElement::properties()` never changes after construction — it holds only the authored static values, each wrapped in a `StoredValue`
- Hydrator writes into `ContentElement` properties via `setProperty($key, $data)` — same key as `dataRequirements[$key]` and `acceptsContext[$key]`
- After hydration, no distinction between static, loaded, and context-provided `ContentElement` properties
- `ContentElement::jsonSerialize()` merges `structProperties` + `nonStructProperties` into one `properties` key; `StoredElement::jsonSerialize()` instead maps each property through its own `StoredValue::jsonSerialize()`
- Skeleton output (`ContentSkeletonElement`) strips properties entirely — only `id`, `component`, `slots`, and `style` (the universal style rides the skeleton, omitted when empty)
