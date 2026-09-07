> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Source Code References

- `LayoutMutation` - The operation contract, `@internal`. `apply(StoredTree $tree): StoredTree` is a pure transform returning a NEW tree; `affected()`, `created()`, `orphaned()`, `droppedWiring()`, `droppedProperties()` report the change computed during `apply()`.
- `AbstractLayoutMutation` - `@internal` base implementing `LayoutMutation`: what `StoredTree` does not carry. Holds the protected `$affected` / `$created` / `$orphaned` / `$droppedWiring` / `$droppedProperties` stash plus their getters, and the element-level primitives the ops share ([docs/shared-primitives.md](docs/shared-primitives.md)). `$created` defaults to the empty list, so an op that mints no node needs no assignment. No tree edits of its own: `find`, `remove`, `insertAtRoot`, `insertIntoSlot` and `replace` are `Layout/StoredTree`'s and the ops call them there.
- `ElementLocation` - `@internal final readonly`. `public StoredElement $node`, `public int $index`, `public ?ParentSlot $parent = null`. A `null` parent marks a root element; `$index` is then the root-list index.
- `ParentSlot` - `@internal final readonly`. `public string $parentId`, `public string $slot`.
- `MutationResult` - `@internal final readonly`, private constructor. Two named constructors, `fromAnalyzedMutation()` (the single owner of result assembly) and `fromParts()`: [docs/runners.md](docs/runners.md).
- `MutationPipeline` - `@internal`, `@final` annotation. The stateless runner over an already-decoded tree: apply, diagnose, mirror onto `created()`, re-diagnose only when the wiring changed the tree, assemble. Never persists. Instance-identity gate: [docs/runners.md](docs/runners.md).
- `PageContextConsumerWiring` - `@internal`. Mirrors onto the created elements the `acceptsContext` consumers their own resolutions prove, and nothing else. Match rules and the four skips: [docs/consumer-mirroring.md](docs/consumer-mirroring.md).
- `PersistedLayoutMutator` - `@internal`, `@final` annotation. Commits one mutation to a stored `content_layout` under a named lock and an optimistic `updatedAt` token; runs no mirroring. Codes and interim limitations: [docs/runners.md](docs/runners.md).
- `Op/` - The nine operations, all extending `AbstractLayoutMutation`: `InsertElement`, `RemoveElement`, `MoveElement`, `ReplaceElement`, `DuplicateElement`, `WrapElements`, `UnwrapElement`, `AttachElement`, `BindElement`. Per-op contracts: [docs/operations.md](docs/operations.md).

## Constraints

- Immutability is the core invariant, enforced by the types: `StoredTree` and `StoredElement` are `final readonly`, so `apply()` cannot mutate the input, and a result tree may safely alias an input subtree by reference.
- Rebuild a stored element through `StoredElement::withSlots()`, never by hand: `Layout/StoredTree`'s surgery and `Output/ElementTreePruner`'s partial-render rebuild both go through it, and a hand-written rebuild silently drops any field added to `StoredElement` later. This replaced an earlier reading (two layers with opposite intents, a surface-only similarity), which held while `Output/ElementTreePruner` rebuilt a different element model by hand; both layers now rebuild stored elements.
- A mutation is single-use: `apply()` computes the report stash, so it must run before any reporter is read; never re-apply an instance to a second tree.
- Do not collapse `created()` into `affected()`. `created` is the exact set of nodes the op built fresh, and the only input the consumer mirroring reads; widening it to match `affected` would re-mirror consumers onto elements the op merely relocated or carried over, undoing an explicit unwiring.
- `affected()` is a conservative highlight hint, never the correctness output: the authority is `MutationPipeline`'s `LayoutDiagnostics` pass over the whole new tree; `affected` only narrows the resolutions the result carries. The per-op rules are load-bearing, do not weaken them (rationale in [README.md](README.md#affected-set-rationale)): `RemoveElement` reports none; `MoveElement` reports the moved subtree only on a parent change; `ReplaceElement` reports the whole reconstructed subtree; `UnwrapElement` reports the whole hoisted forest.
- No silent loss: a detached subtree always comes back via `orphaned()`, a wiring key that cannot be re-homed via `droppedWiring()`, a property value that cannot be carried over via `droppedProperties()`. None is discarded or silently re-mapped.
- `droppedWiring` carries reference keys an operation could not re-home, populated by `ReplaceElement` and `UnwrapElement`. The context an unwrapped element *provided* to its descendants is ancestor context, not a re-homeable wiring key, so it is never a `droppedWiring` entry; a hoisted descendant that depended on it surfaces instead as a `ViolationCode::BrokenRequiredChain` binding violation when a source is bound. That is intentional, not a silent loss.
- Structural impossibilities fail `400` via `ContentSystemException`: `mutationTargetNotFound`, `mutationCycle`, `mutationSlotRequired`, `mutationInvalidWrapTargets`, `mutationUnknownType`. Mutation-specific structural errors, NOT client-defect codes (not in `CLIENT_DEFECT_CODES`).
- `bindingSpecificationNotFound` and `bindingTypeMismatch` (`400`, also not client-defect codes) concern the specification rather than the tree's shape, and `bindingSpecificationDefaultAmbiguous` is `409`.
- `MutationPipeline::run()` takes an already-decoded `StoredTree`; the request draft is decoded upstream by `Api/DraftLayoutDecoder`, never by the pipeline.
- `ReplaceElement` honors a type default exactly as `scaffoldElement` does on insert: `apply()` overlays `primitiveDefaults($newType)` with PHP's `+`, so a carried or authored value wins and a default fills only a new-type primitive key the carry-over left empty ([docs/replace-element.md](docs/replace-element.md)).
- `WrapElements` containers provide no context of their own; wrapping changes the wrapped elements' nesting scope but adds no provider.

## Navigation

- [docs/operations.md](docs/operations.md) - per-operation contracts, constructors, error codes
- [docs/replace-element.md](docs/replace-element.md) - carry-over and drop rules
- [docs/shared-primitives.md](docs/shared-primitives.md) - the helpers every op inherits
- [docs/runners.md](docs/runners.md) - pipeline, persisted mutator, result assembly
- [docs/consumer-mirroring.md](docs/consumer-mirroring.md) - match rules and skips
- [README.md](README.md) - the mental model
