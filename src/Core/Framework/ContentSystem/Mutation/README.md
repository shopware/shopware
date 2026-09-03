# Mutation

Server-side structural edits to a layout tree. Each operation takes a whole tree, applies exactly one structural change, re-resolves the result, and returns the new tree plus a diagnostics report. This is the "assemble" step performed server-side: the admin editor (or an agentic layout builder) sends the current draft and one edit, and gets back the edited, freshly diagnosed layout. The operations run two ways: statelessly over a request draft (`MutationPipeline`, no persistence) and against a stored `content_layout` that the edit is committed to (`PersistedLayoutMutator`).

## Stateless Whole-Tree Model

An operation is a pure transform over the element tree:

1. It receives the entire draft tree (`Layout/StoredTree`).
2. It applies one structural change through that tree's own algebra (`remove`, `insertAtRoot`, `insertIntoSlot`, `replace`), each returning a new `StoredTree`. Every walked node is rebuilt (`withSlots()` never returns `$this`); only a subtree handed in whole is placed by reference.
3. It returns a new tree. The input cannot be mutated at all: `StoredTree` and `StoredElement` are `final readonly`.

Because the operation never mutates shared state, the same draft can be diffed against the result, and the result fed straight back into the next operation.

## Pipeline

`MutationPipeline` is the shared runner every operation goes through, on an **already-decoded** tree. The admin routes decode the request draft upstream through the shared `Api/DraftLayoutDecoder` (the structural pre-gate that fails a malformed or config-defective element with a `400` so the caller never sees a serializer `500`); the pipeline itself is agnostic to whether the tree came from a request draft or a loaded `content_layout`:

1. **Apply** the operation to the decoded tree.
2. **Diagnose** the whole new tree via `Diagnostics/LayoutDiagnostics`. This pass is the authoritative correctness output.
3. **Assemble** a `MutationResult`: the new layout, the resolutions restricted to the affected elements, the diagnostics report, the affected element ids, and the orphaned subtrees, dropped wiring, and dropped property values the operation surfaced.

## Result Channels

Every operation reports four things alongside the new tree:

- **affected** (`list<string>`) - element ids whose resolution may have changed. A conservative highlight hint for the editor, not a correctness claim; the diagnostics pass is the authority.
- **orphaned** (`list<StoredElement>`) - subtrees the operation detached (for example, a replace dropping the children of a slot the new type does not have). Returned so the caller can re-place them; never discarded.
- **droppedWiring** (`list<string>`) - wiring keys the operation could not re-home (for example, a replace to a type without that reference property, or the data-requirement and accepted-context keys an unwrapped container consumed). Reported so the caller can re-wire; never silently re-mapped.
- **droppedProperties** (`array<string, StoredValue>`) - static property values the operation could not carry over (a replace whose new type cannot hold them — key absent, or a value the new type's property type rejects — or an unwrap that removes the container), keyed by property key. Reported so the caller can re-apply them; never silently discarded.

The contract is that no structural edit silently loses content or wiring: anything an operation cannot keep is handed back through `orphaned`, `droppedWiring`, or `droppedProperties`.

## Affected-set rationale

`affected` is a conservative highlight hint, never the correctness output: the diagnostics pass over the whole new tree is the authority. Each operation derives its affected set from how context can flow, not from what structurally moved:

- **RemoveElement reports nothing.** Context flows strictly down the tree, so a provider inside the removed subtree could only feed elements that are themselves inside it. A removed subtree therefore strands no surviving element.
- **MoveElement reports the moved subtree only when the parent changes.** Resolution is candidate selection by type/key, never by sibling index, so a same-parent move (a reorder, or a different slot under the same parent) leaves every element's available providers unchanged and re-resolves nothing. Only a parent change re-scopes the moved subtree.
- **ReplaceElement reports the whole reconstructed subtree.** The new type may provide fewer context providers than the old, so a kept descendant that consumed a now-dropped provider must re-resolve.
- **UnwrapElement reports the whole hoisted forest.** The hoisted subtrees lose the container from their ancestor chain, so any context the container provided is gone.

## Operations

All nine live in `Op/` and extend `AbstractLayoutMutation`:

- **InsertElement** - inserts a fresh element of a given type (primitive defaults seeded from the type, no wiring) into a parent slot at an index, or appended to the root; given a `bindingSpecificationId`, the named specification's wiring is applied onto the fresh element atomically after scaffold (the specification is resolved before any tree change).
- **RemoveElement** - deletes an element and its whole subtree.
- **MoveElement** - relocates an element and its subtree under a new parent slot (or to the root), rejecting a move onto itself or a descendant as a cycle.
- **ReplaceElement** - swaps an element's component to a new type, keeping the same id and carrying over matching properties, wiring, and slot children, then seeding the new type's primitive defaults for any keys it does not carry (a carried or authored value wins); anything the new type cannot hold is surfaced via `orphaned`/`droppedWiring`/`droppedProperties`.
- **DuplicateElement** - deep-clones a subtree with freshly minted ids and splices the clone as the next sibling.
- **WrapElements** - mints a container element and moves a set of sibling elements into it, placing the container where the first target was.
- **UnwrapElement** - replaces a container with its slot children, hoisted into the container's parent at the container's position. The removed container's own static property values and consumed wiring (its data requirements and accepted context) come back through `droppedProperties` / `droppedWiring`, so nothing the container held is lost.
- **AttachElement** - splices a caller-supplied element subtree into a parent slot (or the root), reminting every id. The inverse of the detachment a replace reports through `orphaned`: it re-places a detached subtree (or a copied one) without trusting client ids.
- **BindElement** - applies a `Binding/Specification/BindingSpecification`'s wiring onto one element: each `resolves` entry becomes a data requirement (overwriting an existing key's wiring), each `inputs` entry with a default seeds that primitive property only when the element does not already carry it, and every wired key's attribution is recorded. Adds wiring only — it never detaches or drops anything, so `orphaned`/`droppedWiring`/`droppedProperties` stay empty.

## Key Classes

- `LayoutMutation` - The operation contract: `apply()` returns the new tree; `affected()`, `orphaned()`, `droppedWiring()`, `droppedProperties()` report what changed. Single-use, `apply()` runs before any reporter is read.
- `AbstractLayoutMutation` - What `StoredTree` does not carry: the report stash, the typed view of element location, fresh-element scaffolding, and the uniform `400` for structural impossibilities.
- `MutationPipeline` - Apply, diagnose, assemble. The shared stateless runner for every operation, over an already-decoded tree (`Api/DraftLayoutDecoder` decodes the request draft upstream). Never persists.
- `PersistedLayoutMutator` - The persisted counterpart to `MutationPipeline`: it commits one operation to a stored `content_layout`. Loads by id (404 if absent), guards an optimistic-concurrency token against the row's `updatedAt` (409 on a stale token, without writing), applies the operation, and persists the mutated tree, whose write runs the resolvability gates. The response diagnostics are derived from the loaded layout's single `root_source` (resolved via `Adapter/RootSourceRegistry`), consistent with what the gate enforces. Detached content (`orphaned`), dropped wiring (`droppedWiring`), and dropped property values (`droppedProperties`) are committed-out of the tree but returned in the result so the caller can re-place the subtrees with `AttachElement`, re-wire the keys, or re-apply the values; nothing is silently lost.
- `MutationResult` - The outcome: new layout, per-affected-element resolutions, diagnostics report, affected ids, orphaned subtrees, dropped wiring, and dropped property values.
- `ElementLocation` / `ParentSlot` - Where an element sits: the node, its index in its containing list, and its parent slot coordinates (`null` parent for a root element).

## Subdirectories

- **Op/** - The nine concrete operations, each one structural edit.
