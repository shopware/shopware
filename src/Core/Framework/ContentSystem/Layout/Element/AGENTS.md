> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Two element models

This directory holds two element models, one on each side of the storage/render
split (see [role-suffixes.md](../../docs/role-suffixes.md#the-two-model-roles)):

- `StoredElement` — the storage model: what the admin edits, what the storage
  column holds. Validation and mutation still lower it to `ContentElement`
  first, then move onto it later. `final readonly`; every edit returns a new
  instance via a `with*()` method. Property values are
  wrapped in `StoredValue`, never a raw PHP scalar, so a hydrated object can
  never sit in the stored tree by type rather than by convention. Slots are
  `array<string, list<StoredElement>>`.
- `ContentElement` — the older model: still `extends Struct` and mutable.
  `ContentElementLowering` produces it from a `StoredElement` tree for the call
  sites that still speak it (see its class comment for the exact list and each
  site's removal condition). Slots are `array<string, SlotContent>`.

The mutation-oriented guidance below (`setProperty`, `AssignArrayTrait`, the
struct/non-struct split, the storage → post-hydration property lifecycle) is
about `ContentElement` alone: `StoredElement` has no setters and never goes
through hydration — it is fully formed before any `ContentElementLowering`
touches it.

## Constraints

- `ContentElement` property getters return null for missing keys — never throw. Use `hasProperty()` or null coalescing
- `ContentElement::allSlotElements()` is a generator — do NOT convert with `iterator_to_array()`
- `ContentElement::replacePlaceholders()` is recursive and only replaces scalar values
- Don't use `assign()` from `AssignArrayTrait` on a `ContentElement` — corrupts the struct/non-struct property split
- `ContentElement` slots: `array<string, SlotContent>`, multiple elements per slot; `StoredElement` slots: `array<string, list<StoredElement>>`, same multiplicity
- `ContentElement` `properties` changes between stages: right after lowering, static values only; post-hydration, static + loaded data merged. `StoredElement::properties()` never changes after construction — it holds only the authored static values, each wrapped in a `StoredValue`
- Hydrator writes into `ContentElement` properties via `setProperty($key, $data)` — same key as `dataRequirements[$key]` and `acceptsContext[$key]`
- After hydration, no distinction between static, loaded, and context-provided `ContentElement` properties
- `ContentElement::jsonSerialize()` merges `structProperties` + `nonStructProperties` into one `properties` key; `StoredElement::jsonSerialize()` instead maps each property through its own `StoredValue::jsonSerialize()`
- Skeleton output (`ContentSkeletonElement`) strips properties entirely — only `id`, `component`, `slots`, and `style` (the universal style rides the skeleton, omitted when empty)
