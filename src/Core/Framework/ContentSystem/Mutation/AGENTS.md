## Constraints

- Return a new tree from `apply()` and rebuild nodes with `with*()`, never by hand. Check: does an op build a `StoredElement` from another element's fields? [Layout/Element/AGENTS.md](../Layout/Element/AGENTS.md)
- Run `apply()` before any reporter read and never re-apply an instance to a second tree. Check: does a caller read `affected()` or `orphaned()` first, or reuse an instance? [LayoutMutation.php](LayoutMutation.php)
- Mirror a consumer only onto `created()`, never `affected()`. Check: does an op put a moved or kept node in `created()`? `MutationPipelineTest` pins it. [mutation.md](../docs/principles/mutation.md#the-draft-pipeline-writes-derived-wiring-only-where-proved)
- Keep `affected()` a highlight hint, never the correctness output, and keep each op's affected set as [docs/operations.md](docs/operations.md) states it. Check: does an op narrow `affected()` below the elements whose resolution can change? [mutation.md](../docs/principles/mutation.md#the-affected-set-is-a-highlight-hint-derived-from-how-context-flows)
- Report every loss: a detached subtree through `orphaned()`, a wiring key the op cannot re-home through `droppedWiring()`, a value the op cannot carry through `droppedProperties()`. Discard or re-map none silently. Check: `MutationResponseTest` pins all three on the wire. [failure-and-loss.md](../docs/principles/failure-and-loss.md#the-module-drops-nothing-silently-and-reports-every-loss)
- Fail a mutation payload defect as a typed `400` from a `ContentSystemException` factory, never a `500` or a silent save. List a new structural code in [Api/docs/mutation-errors.md](../Api/docs/mutation-errors.md) and keep it out of `CLIENT_DEFECT_CODES`. Check: is the code in that table?

## Where to look

- What an operation receives and returns: [README.md](README.md#stateless-whole-tree-model)
- What each result channel carries: [README.md](README.md#result-channels)
- Pipeline steps and who decodes the draft handed to `MutationPipeline::run()`: [README.md](README.md#pipeline)
- Re-diagnosis gate and root context of `MutationPipeline::run()`: [docs/runners.md](docs/runners.md#mutationpipeline)
- Persisted route, its lock, version token and known limits: [docs/runners.md](docs/runners.md#persistedlayoutmutator)
- Placement, errors, affected and created set of each operation: [docs/operations.md](docs/operations.md)
- What unwrap reports as dropped and the context its container provided: [docs/operations.md](docs/operations.md#unwrapelement)
- Context of a wrap container: [docs/operations.md](docs/operations.md#wrapelements)
- Binding specification resolution on insert, its error codes and statuses: [docs/operations.md](docs/operations.md#insertelement)
- Type swap carry-over and which value wins over a type default: [docs/replace-element.md](docs/replace-element.md#what-it-carries-over)
- Which resolutions become consumers on a created element: [docs/consumer-mirroring.md](docs/consumer-mirroring.md#what-proves-a-consumer)
- Helpers every operation inherits: [docs/shared-primitives.md](docs/shared-primitives.md)
