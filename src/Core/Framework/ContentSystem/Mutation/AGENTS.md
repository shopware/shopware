> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Immutability is the core invariant, enforced by the types: `StoredTree` and `StoredElement` are `final readonly`, so `apply()` cannot mutate the input, and a result tree may safely alias an input subtree by reference.
- Rebuild a stored element through `StoredElement::withSlots()`, never by hand: `Layout/StoredTree`'s surgery and `Output/ElementTreePruner`'s partial-render rebuild both go through it, and a hand-written rebuild silently drops any field added to `StoredElement` later. This replaced an earlier reading (two layers with opposite intents, a surface-only similarity), which held while `Output/ElementTreePruner` rebuilt a different element model by hand; both layers now rebuild stored elements.
- A mutation is single-use: `apply()` computes the report stash, so it must run before any reporter is read; never re-apply an instance to a second tree.
- Do not collapse `created()` into `affected()`. `created` is the exact set of nodes the op built fresh, and the only input the consumer mirroring reads; widening it to match `affected` would re-mirror consumers onto elements the op merely relocated or carried over, undoing an explicit unwiring.
- `affected()` is a conservative highlight hint, never the correctness output: the authority is `MutationPipeline`'s `LayoutDiagnostics` pass over the whole new tree; `affected` only narrows the resolutions the result carries. The per-op rules are load-bearing, do not weaken them (rationale in [README.md](README.md#affected-set-rationale)): `RemoveElement` reports none; `MoveElement` reports the moved subtree only on a parent change; `ReplaceElement` reports the whole reconstructed subtree; `UnwrapElement` reports the whole hoisted forest.
- No silent loss: a detached subtree always comes back via `orphaned()`, a wiring key that cannot be re-homed via `droppedWiring()`, a property value that cannot be carried over via `droppedProperties()`. None is discarded or silently re-mapped.
- `droppedWiring` carries reference keys an operation could not re-home, populated by `ReplaceElement` and `UnwrapElement`. The context an unwrapped element *provided* to its descendants is ancestor context, not a re-homeable wiring key, so it is never a `droppedWiring` entry; a hoisted descendant that depended on it surfaces instead as a `ViolationCode::BrokenRequiredChain` binding violation when a source is bound. That is intentional, not a silent loss.
- Structural impossibilities fail `400` via `ContentSystemException`: `mutationTargetNotFound`, `mutationCycle`, `mutationSlotRequired`, `mutationInvalidWrapTargets`, `mutationUnknownType`, `mutationPropertyUnknown`, `mutationPropertyConflict`, `mutationPropertyValueRejected`. Mutation-specific structural errors, NOT client-defect codes (not in `CLIENT_DEFECT_CODES`).
- `bindingSpecificationNotFound` and `bindingTypeMismatch` (`400`, also not client-defect codes) concern the specification rather than the tree's shape, and `bindingSpecificationDefaultAmbiguous` is `409`.
- `MutationPipeline::run()` takes an already-decoded `StoredTree`; the request draft is decoded upstream by `Api/DraftLayoutDecoder`, never by the pipeline.
- `ReplaceElement` honors a type default exactly as `scaffoldElement` does on insert: `apply()` overlays `primitiveDefaults($newType)` with PHP's `+`, so a carried or authored value wins and a default fills only a new-type primitive key the carry-over left empty ([docs/replace-element.md](docs/replace-element.md)).
- `ReplaceElement::carryProperties()` judges a carried value with `Layout/Type/Specification/PropertyType::admits()` behind its `isPrimitive()` pre-gate, and keeps no match table of its own — do not reintroduce one, the write path and `Diagnostics/LayoutDiagnostics` read the same predicate. Two consequences the predicate owns: a translatable property's language map carries between two types both declaring that key translatable, and an authored present `null` under a non-translatable primitive carries instead of being dropped, with the default overlay leaving it in place. The `resolvedBy` storage-key rule deliberately does NOT reuse `admits()`: a storage key is undeclared by design, so it is never a new-type property. `UpdateElementProperties` judges the one value it reads the same way, behind its own primitive-and-declared gate, with no match table of its own either.
- Every other op is opaque to property values, and that is what carries language maps through: `RemoveElement`, `MoveElement`, `DuplicateElement`, `AttachElement` and `UnwrapElement` read no property value, as does `Layout/StoredTree`'s slot rebuild. `UpdateElementProperties` is opaque to every key it is not named for in `$values` or `$removeKeys`: those carry verbatim, unread. Do not add a value-inspecting branch to one of the fully opaque ops.
- `WrapElements` containers provide no context of their own; wrapping changes the wrapped elements' nesting scope but adds no provider.

## Navigation

- [docs/symbols.md](docs/symbols.md) - the classes, their roles, and their paths
- [docs/operations.md](docs/operations.md) - per-operation contracts, constructors, error codes
- [docs/replace-element.md](docs/replace-element.md) - carry-over and drop rules
- [docs/shared-primitives.md](docs/shared-primitives.md) - the helpers every op inherits
- [docs/runners.md](docs/runners.md) - pipeline, persisted mutator, result assembly
- [docs/consumer-mirroring.md](docs/consumer-mirroring.md) - match rules and skips
- [README.md](README.md) - the mental model
