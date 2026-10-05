# Mutation

The rules in this area govern a mutation, one server-side edit of a stored layout tree, and the context wiring that a mutation derives.

## The draft pipeline writes derived wiring only where proved

The draft pipeline writes derived wiring only onto created elements. Only the draft pipeline turns a resolution into wiring. The draft pipeline writes a consumer only onto an element the operation created, and only for a reference that the resolution proved unambiguously. It aliases the written consumer to the property that proved the reference. Where decode would reject the wiring or the element itself provides the key, it writes no consumer and never throws. Unproved wiring stays dropped and returns to the client. An unwired consumer stays unwired. Resolution output never enters storage.

Why: A guessed consumer is wiring that nobody chose. A consumer written onto a moved element re-adds a consumer that the author removed. An unaliased consumer fills a foreign key while the declared property stays empty.

Not chosen: Widening the written set from `created()` to `affected()`, or wiring a consumer on a guess. Testing the resolved `contextKey` instead of the written property key against a data requirement or a provider.

Exceptions: A type swap re-scaffolds the node under the same id and counts as created. The re-scaffolded node may regain a consumer that the author removed.

In code:

- `ContextConsumerMirror::apply()` wires only the elements in `created()`.
- `PersistedLayoutMutator` derives no wiring.
- `MutationPipelineTest` pins the created-only wiring.
- See [consumer-mirroring.md](../../Mutation/docs/consumer-mirroring.md).

## A mutation operation is a pure tree transform that makes a layout change and nothing else

An edit that the operation rejects changes nothing. An operation is opaque to each element and each key that it does not name. It has no normalization, default or value rule of its own.

Why: One normalizing operation invites another for each value shape. A client cannot predict a result that changes keys its request did not name.

Exceptions: `ReplaceElement` drops a carried value that its new type cannot hold. `UnwrapElement` drops the container's own values when the operation removes the container. Both operations report each dropped value in `droppedProperties()`.

In code:

- See [operations.md](../../Mutation/docs/operations.md).

## The affected set is a highlight hint derived from how context flows

An operation derives `affected()` from how context can flow, not from what structurally moved. `affected()` is a conservative highlight hint and never a correctness claim. The `LayoutDiagnostics` pass over the whole new tree is the authority. `affected()` only narrows the resolutions that the result carries.

Why: Context flows strictly down, so a provider inside a removed subtree feeds only elements inside it. Resolution selects candidates by type and key, never by sibling index. A same-parent move, whether a reorder or a slot change, changes no available provider. A replaced element may lose providers that its kept descendants consumed. A hoisted subtree loses the context of its former container.

In code:

- `MutationPipeline` diagnoses the whole new tree.
- `MutationResult::fromAnalyzedMutation()` restricts the carried resolutions to the affected set.
- `MoveElementTest`, `ReplaceElementTest` and `UnwrapElementTest` pin the sets of those three operations.
- See [operations.md](../../Mutation/docs/operations.md).
