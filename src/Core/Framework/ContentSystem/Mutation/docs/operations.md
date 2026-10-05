# Operations

<!-- size-allowance: lookup - one entry per structural operation, consulted one at a time -->

A channel an operation is not named for stays empty.

## InsertElement

Inserts a fresh element of `$type` (primitive defaults seeded from the type, no wiring) into a parent slot at an
index, or appended to the root. `requireRegistered`; scaffolds via `scaffoldElement`, then always fill-applies the
type's default binding specification regardless of `$bindingSpecificationId` (`resolveDefaultSpecification()`,
`byType(type)` filtered by `isDefault()`: zero is a no-op, one is fill-applied and attributed to its own qualified
id via `BindingApplicator::applyFillOnly()`, more than one throws `bindingSpecificationDefaultAmbiguous` `409`).
With no parent it inserts at root; otherwise `$slot` is required (`mutationSlotRequired`), the parent must exist
(`mutationTargetNotFound`), then it inserts into the slot. `affected = [newId]`; `created = [newId]`.

When `$bindingSpecificationId` is also given, the named specification is resolved **first**, before any tree change
(unregistered → `bindingSpecificationNotFound`; `type()` ≠ `$type` → `bindingTypeMismatch`; both `400`), then applied
on top of the fill-applied default via `BindingApplicator::apply()` (overwrite), so shared keys belong to the
explicit choice. Both steps precede insertion, so a bound insert is atomic: nothing is inserted on a `400`.

## RemoveElement

Deletes an element and its whole subtree. Target must exist (`mutationTargetNotFound`). `affected = []`; `created`
stays the empty default.

## MoveElement

Relocates the element and its subtree under a new parent slot, or to the root. The element must exist
(`mutationTargetNotFound`); a move into itself or a descendant throws `mutationCycle`; a non-null new parent must
also exist (`mutationTargetNotFound`).
`affected = $newParentId === $oldParentId ? [] : subtreeIds($node)`; `created` stays the empty default, because a
move relocates existing nodes.

A move to the root (no `$newParentId`) needs no slot; a same-parent move may omit `$newSlot` and reuses the current
slot; a move under a different parent requires `$newSlot` (`mutationSlotRequired`). `$index` is applied to the
**post-removal** list: the element is lifted out before it is spliced back, so a same-parent "move to index N"
counts the siblings that remain after removal, not their original positions (`spliceList` then clamps a
null/negative/out-of-range index to append).

## ReplaceElement

Swaps an element's component to a new type, keeping the same id. Carry-over rules, the default overlay, and its
three drop channels: [replace-element.md](replace-element.md).

## DuplicateElement

Deep-clones the subtree with new ids and splices the clone as the next sibling. Target must exist
(`mutationTargetNotFound`); `affected = subtreeIds($clone)` and `created` the same full clone set, never the
original; splices at `$index ?? location.index + 1`. Context wiring is key-based and position-based, never id-based,
so it carries over unchanged with no internal id references to rewrite.

## WrapElements

Adds a container element and moves a set of sibling elements into it. `requireRegistered`; slot required
(`mutationSlotRequired`); each target must exist (`mutationTargetNotFound`); the targets must all be siblings in one
slot (or all roots), else `mutationInvalidWrapTargets`. An empty id list and a list with a repeated id both throw
`mutationInvalidWrapTargets` too. Scaffolds the container with the targets (in original order) in `$slot`, places it
at the lowest target index. `affected = [containerId, ...elementIds]`; `created = [containerId]` only, because the
wrapped targets are moved, not created.

## UnwrapElement

Replaces a container with its slot children, hoisted into the container's parent slot at the container's index and
flattened across all slots in slot order. The container must exist (`mutationTargetNotFound`); `affected` = the
union of `subtreeIds` over each child, while `created` stays the empty default, the hoisted children keeping their
nodes.

Reports the removed container's own static property values via `droppedProperties` and the wiring it consumed, its
data requirement keys plus accepted-context keys, de-duplicated, via `droppedWiring`.
The context the container *provided* is not reported, a carve-out stated with the other result-channel rules in
[../AGENTS.md](../AGENTS.md).

## AttachElement

Splices a caller-supplied element subtree into a parent slot (or the root), replacing every id. The inverse of the
detachment a replace reports through `orphaned`: it re-places a detached subtree, or a copied one, without trusting
client ids. The supplied root's component must be a registered type,
else `mutationUnknownType`, matching the check insert/replace/wrap run. Clients never supply ids; the server-issued
ids come back in `affected = subtreeIds($clone)`, and `created` carries the same full new-id set, every node in
the spliced subtree being new to the layout. Placement mirrors `Op/InsertElement` (slot required with a parent →
`mutationSlotRequired`; parent must exist → `mutationTargetNotFound`). Detaches nothing:
`orphaned`/`droppedWiring`/`droppedProperties` stay empty.

## BindElement

Applies a `Binding/Specification/BindingSpecification`'s wiring onto one element. Looks up the specification
(`bindingSpecificationNotFound` if the id is not registered); the target element must exist
(`mutationTargetNotFound`); the specification's declared `type` must equal the target's `component`
(`bindingTypeMismatch` otherwise, for example a specification authored for one element type applied to another).

The merge is delegated to `Binding/BindingApplicator`, shared with `Op/InsertElement`. Every `resolves` entry
becomes a concrete `DataRequirement`, merged into the element's existing data requirements and **overwriting** the
same key, so re-applying a binding over an already-bound key replaces its wiring rather than failing. Every `inputs`
entry with a default seeds that primitive property, but only into a key the element does not already carry
(`StoredElement::property()` presence gate: `null` there means the key is absent, because an authored `null` is a
present `StoredValue`). Every wired key's
attribution is recorded into `attributedSpecifications`, also merged and overwriting.

Keeps the same id. `affected = [elementId]`; `created` stays the empty default (the element node is wired, not
built); `orphaned`/`droppedWiring`/`droppedProperties` stay empty, because binding only adds wiring.
