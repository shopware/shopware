# Mutation

Server-side structural edits to a layout tree. Each operation takes a whole tree, applies exactly one structural
change, re-resolves the result, and returns the new tree plus a diagnostics report. This is the "assemble" step
performed server-side: the admin editor (or an agentic layout builder) sends the current draft and one edit, and gets
back the edited, freshly diagnosed layout. The operations run two ways: statelessly over a request draft
(`MutationPipeline`, no persistence) and against a stored `content_layout` that the edit is committed to
(`PersistedLayoutMutator`).

## Stateless Whole-Tree Model

An operation is a pure transform over the element tree:

1. It receives the entire draft tree (`Layout/StoredTree`).
2. It applies one structural change through that tree's own algebra (`remove`, `insertAtRoot`, `insertIntoSlot`,
   `replace`), each returning a new `StoredTree`. Every walked node is rebuilt (`withSlots()` never returns `$this`);
   only a subtree handed in whole is placed by reference.
3. It returns a new tree. The input cannot be mutated at all: `StoredTree` and `StoredElement` are `final readonly`.

Because the operation never mutates shared state, the same draft can be diffed against the result, and the result fed
straight back into the next operation.

## Pipeline

`MutationPipeline` is the shared runner every operation goes through, on an **already-decoded** tree. The admin
routes decode the request draft upstream through the shared `Api/DraftLayoutDecoder` (the structural pre-gate that
fails a malformed or config-defective element with a `400` so the caller never sees a serializer `500`); the pipeline
itself is agnostic to whether the tree came from a request draft or a loaded `content_layout`:

1. **Apply** the operation to the decoded tree.
2. **Diagnose** the whole new tree via `Diagnostics/LayoutDiagnostics`. This pass is the authoritative correctness
   output.
3. **Mirror** the proven context consumers onto the elements the operation created, via `ContextConsumerMirror`.
   A tree that gained a consumer is diagnosed a second time, so the report and the resolutions always describe the
   tree the result carries; a tree that gained none comes back as the same instance and is diagnosed once.
4. **Assemble** a `MutationResult`: the new layout, the resolutions restricted to the affected elements, the
   diagnostics report, the affected element ids, and the orphaned subtrees, dropped wiring, and dropped property
   values the operation surfaced.

## Result Channels

Every operation reports five things alongside the new tree:

- **affected** (`list<string>`) - element ids whose resolution may have changed. A conservative highlight hint for
  the editor, not a correctness claim; the diagnostics pass is the authority.
- **created** (`list<string>`) - element ids whose node the operation built fresh. A replacement keeps the target's
  id and still counts, because its node is re-scaffolded. The consumer mirroring wires these ids and no others.
- **orphaned** (`list<StoredElement>`) - subtrees the operation detached (for example, a replace dropping the
  children of a slot the new type does not have). Returned so the caller can re-place them; never discarded.
- **droppedWiring** (`list<string>`) - wiring keys the operation could not re-home (for example, a replace to a type
  without that reference property, or the data-requirement and accepted-context keys an unwrapped container
  consumed). Reported so the caller can re-wire; never silently re-mapped.
- **droppedProperties** (`array<string, StoredValue>`) - static property values the operation could not carry over
  (a replace whose new type cannot hold them, key absent or a value the new type's property type rejects, or an
  unwrap that removes the container), keyed by property key. Reported so the caller can re-apply them; never
  silently discarded.

The contract is that no structural edit silently loses content or wiring: anything an operation cannot keep is handed
back through `orphaned`, `droppedWiring`, or `droppedProperties`.

## Affected-set rationale

`affected` is a conservative highlight hint, never the correctness output: the diagnostics pass over the whole new
tree is the authority. Each operation derives its affected set from how context can flow, not from what structurally
moved:

- **RemoveElement reports nothing.** Context flows strictly down the tree, so a provider inside the removed subtree
  could only feed elements that are themselves inside it. A removed subtree therefore strands no surviving element.
- **MoveElement reports the moved subtree only when the parent changes.** Resolution is candidate selection by
  type/key, never by sibling index, so a same-parent move (a reorder, or a different slot under the same parent)
  leaves every element's available providers unchanged and re-resolves nothing. Only a parent change re-scopes the
  moved subtree.
- **ReplaceElement reports the whole reconstructed subtree.** The new type may provide fewer context providers than
  the old, so a kept descendant that consumed a now-dropped provider must re-resolve.
- **UnwrapElement reports the whole hoisted forest.** The hoisted subtrees lose the container from their ancestor
  chain, so any context the container provided is gone.

## Reference

- [docs/operations.md](docs/operations.md) - the nine operations one by one: insert, remove, move, replace,
  duplicate, wrap, unwrap, attach, bind
- [docs/replace-element.md](docs/replace-element.md) - what a type swap carries over, and what it drops
- [docs/runners.md](docs/runners.md) - `MutationPipeline`, `PersistedLayoutMutator`, and result assembly
- [docs/consumer-mirroring.md](docs/consumer-mirroring.md) - which resolutions become consumers on a created element
- [docs/shared-primitives.md](docs/shared-primitives.md) - the helpers every operation inherits
- [AGENTS.md](AGENTS.md) - the symbol index and the constraints that govern an edit here

## Subdirectories

- **Op/** - The nine concrete operations, each one structural edit.
