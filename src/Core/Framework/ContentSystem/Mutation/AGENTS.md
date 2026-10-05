## Constraints

- `apply()` cannot mutate its input: `StoredTree` and `StoredElement` are `final readonly`. The rebuild rule, `with*()` and never by hand, is owned by [Layout/Element/AGENTS.md](../Layout/Element/AGENTS.md)
- A mutation is single-use: `apply()` computes the report stash, so it must run before any reporter is read; never re-apply an instance to a second tree.
- Mirror a consumer only onto `created()`, never `affected()`, and only for a resolvable reference. Where the wiring decode would reject, mirror nothing and never throw. Mirror nothing on the persisted route. Check: does an op put a moved or kept node in `created()`? `MutationPipelineTest` pins it.
- `affected()` is a conservative highlight hint, never the correctness output: the authority is `MutationPipeline`'s `LayoutDiagnostics` pass over the whole new tree; `affected` only narrows the resolutions the result carries. The per-op rules are load-bearing, do not weaken them (rationale in [README.md](README.md#affected-set-rationale)): `RemoveElement` reports none; `MoveElement` reports the moved subtree only on a parent change; `ReplaceElement` reports the whole reconstructed subtree; `UnwrapElement` reports the whole hoisted forest.
- Report every loss: a detached subtree through `orphaned()`, a wiring key the op cannot re-home through `droppedWiring()`, a value the op cannot carry through `droppedProperties()`. Discard or re-map none silently. Check: `MutationResponseTest` pins all three on the wire.
- `droppedWiring` carries reference keys an operation could not re-home, populated by `ReplaceElement` and `UnwrapElement`. The context an unwrapped element *provided* to its descendants is ancestor context, not a re-homeable wiring key, so it is never a `droppedWiring` entry; a hoisted descendant that depended on it surfaces instead as a `ViolationCode::BrokenRequiredChain` binding violation when a source is bound. That is intentional, not a silent loss.
- Fail a mutation payload defect as a typed `400` from a `ContentSystemException` factory, never a `500` or a silent save. List a new structural code in [Api/docs/mutation-errors.md](../Api/docs/mutation-errors.md) and keep it out of `CLIENT_DEFECT_CODES`. Check: is the code in that table?
- `bindingSpecificationNotFound` and `bindingTypeMismatch` (`400`, also not client-defect codes) concern the specification rather than the tree's shape, and `bindingSpecificationDefaultAmbiguous` is `409`.
- `MutationPipeline::run()` takes an already-decoded `StoredTree`; the request draft is decoded upstream by `Api/DraftLayoutDecoder`, never by the pipeline.
- `ReplaceElement` honors a type default exactly as `scaffoldElement` does on insert: a carried or authored value wins and a default fills only a new-type primitive key the carry-over left empty ([docs/replace-element.md](docs/replace-element.md)).
- `WrapElements` containers provide no context of their own; wrapping changes the wrapped elements' nesting scope but adds no provider.

## Navigation

- [README.md](README.md) - the mental model
- [failure-and-loss.md](../docs/principles/failure-and-loss.md) - why nothing is dropped silently, why a payload defect is a typed 400, and what was not chosen
- [../docs/principles/mutation.md](../docs/principles/mutation.md) - why these constraints hold, and what was not chosen
