# Operations

<!-- size-allowance: lookup - one entry per structural operation, consulted one at a time -->

A [result channel](../README.md#result-channels) an operation's section omits stays empty.

## InsertElement

Inserts a fresh element of `$type` (stored defaults seeded from the type, no wiring) into a parent slot at an
index, or appended to the root. `requireRegistered`; scaffolds via `scaffoldElement`, then always fill-applies the
type's default binding specification regardless of `$bindingSpecificationId` (`resolveDefaultSpecification()`,
`byType(type)` filtered by `isDefault()`). Zero is a no-op, one is fill-applied and attributed to its own qualified
id via `BindingApplicator::applyFillOnly()`, more than one throws `bindingSpecificationDefaultAmbiguous` `409`.
With no parent it inserts at root; otherwise `$slot` is required (`mutationSlotRequired`), the parent must exist
(`mutationTargetNotFound`), then it inserts into the slot. `affected = [newId]`; `created = [newId]`; `freshlyScaffolded = [newId]`.

When `$bindingSpecificationId` is also given, the named specification is resolved **first**, before any tree change
(unregistered → `bindingSpecificationNotFound`; `type()` ≠ `$type` → `bindingTypeMismatch`; both `400`, neither a
client-defect code). It is then applied
over the fill-applied default via `BindingApplicator::apply()` (overwrite), so shared keys belong to the
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
**post-removal** list. The element is lifted out before it is spliced back, so a same-parent "move to index N"
counts the siblings that remain after removal, not their original positions. `spliceList` then clamps a
null/negative/out-of-range index to append.

## ReplaceElement

Swaps an element's component to a new type, keeping the same id. Carry-over rules, the default overlay, and its
three drop channels: [replace-element.md](replace-element.md).

## DuplicateElement

Deep-clones the subtree with new ids and splices the clone as the next sibling. Target must exist
(`mutationTargetNotFound`); `affected = subtreeIds($clone)` and `created` the same full clone set, never the
original; splices at `$index ?? location.index + 1`. It reports no fresh scaffolds, preserving existing mapping choices and deliberate unmapping. Context wiring is key-based and position-based, never id-based,
so it carries over unchanged with no internal id references to rewrite.

## WrapElements

Adds a container element and moves a set of sibling elements into it. `requireRegistered`; slot required
(`mutationSlotRequired`); each target must exist (`mutationTargetNotFound`); the targets must all be siblings in one
slot (or all roots), else `mutationInvalidWrapTargets`. An empty id list and a list with a repeated id both throw
`mutationInvalidWrapTargets` too. Scaffolds the container with the targets (in original order) in `$slot` and places
it at the lowest target index. The container becomes the wrapped elements' parent, changing the context they consume.
`affected = [containerId, ...elementIds]`. `created = [containerId]` only, because the wrapped targets are
moved, not created. Only the new container is also `freshlyScaffolded()` and eligible for default mappings. The container provides no context of its own, so wrapping adds no provider.

## UnwrapElement

Replaces a container with its slot children, hoisted into the container's parent slot at the container's index and
flattened across all slots in slot order. The container must exist (`mutationTargetNotFound`); `affected` = the
union of `subtreeIds` over each child, while `created` stays the empty default, the hoisted children keeping their
nodes.

Reports the removed container's own static property values via `droppedProperties` and the wiring it consumed, its
data requirement keys plus accepted-context keys, de-duplicated, via `droppedWiring`.
The container's provided context is not in that set, so it is never a `droppedWiring` entry.
A hoisted descendant that depended on it gets a `ViolationCode::BrokenRequiredChain` binding violation when a source
is bound.

## AttachElement

Splices a caller-supplied element subtree into a parent slot (or the root), replacing every id, then fill-applies the
type default binding to every element of the subtree (`applyDefaultBindingToSubtree()`; wiring the subtree already
carries wins, more than one default throws `bindingSpecificationDefaultAmbiguous` `409`). It inverts the
detachment a replace reports through `orphaned`: it re-places a detached subtree, or a copied one, without trusting
client ids. The supplied root's component must be a registered type, else `mutationUnknownType`, matching the check
insert/replace/wrap run. Clients never supply ids. The server-issued ids come back in `affected = subtreeIds($clone)`,
and `created` carries the same full new-id set: every node in the spliced subtree is new to the layout. Placement
mirrors `Op/InsertElement` (slot required with a parent → `mutationSlotRequired`; parent must exist →
`mutationTargetNotFound`). Detaches nothing: `orphaned`/`droppedWiring`/`droppedProperties` stay empty.

Ordinary attachment preserves authored mapping state, including a selected entity and deliberate unmapping. New IDs appear in `created()` but not `freshlyScaffolded()`, so mapping defaults and mirrored consumers are not recreated. Preset insertion passes the internal `isNewScaffold: true` option to initialize newly inserted content.

## AttachElements

Attaches a list of caller-supplied element subtrees by running `AttachElement` over each one in list order, into the same parent slot (or the root). Every rule, error and result of `AttachElement` applies per element, so the first element that fails aborts the whole operation. With an `$index`, element `n` of the list is placed at `$index + n`, keeping list order; with none, each is appended. `affected` and `created` are the unions of the per-element sets. An empty list changes nothing. The preset-insert route passes `isNewScaffold: true`; that intent is forwarded to each attachment, whose IDs then also appear in `freshlyScaffolded()`.

## BindElement

Applies a `Binding/Specification/BindingSpecification`'s wiring onto one element. Looks up the specification
(`bindingSpecificationNotFound` if the id is not registered). The target element must exist
(`mutationTargetNotFound`). The specification's declared `type` must equal the target's `component`
(`bindingTypeMismatch` otherwise, for example a specification authored for one element type applied to another).

The merge is delegated to `Binding/BindingApplicator`, shared with `Op/InsertElement`. Every `resolves` entry
becomes a concrete `DataRequirement`, merged into the element's existing data requirements and **overwriting** the
same key. Re-applying a binding over an already-bound key replaces its wiring rather than failing. Every `inputs`
entry with a default seeds that primitive property, but only into a key the element does not already carry.
The presence gate is `StoredElement::property()`: `null` there means absence, because an authored `null` is a
present `StoredValue`. Every wired key's attribution is recorded into `attributedSpecifications`, also merged and overwriting.

Keeps the same id. `affected = [elementId]`. `created` stays the empty default, because the element node is wired,
not built. `orphaned`/`droppedWiring`/`droppedProperties` stay empty, because binding only adds wiring.

## MapProperty

Maps one declared property to an exactly catalogued typed source, deriving cardinality through `StoredMappingInspector`. Reads the source from `StoredTree::$rootSource`; a missing source is an internal precondition fault (`MAPPING_ROOT_SOURCE_MISSING`, 500), while request validation rejects missing/blank or unknown sources with 400. The target must exist and its type be registered. Ordinary destination/base-key collisions are rejected; an existing typed mapping at that property may be replaced. Authored properties and loader wiring remain available for fallback. `affected = [elementId]`; no fresh scaffold is reported.

## UnmapProperty

Removes only the source-bearing consumer at the destination property, preserving authored values and ordinary wiring. A missing target throws `mutationTargetNotFound`; an absent mapping returns the input tree unchanged. An actual removal reports `affected = [elementId]`. Neither saving nor ordinary attachment restores the removed mapping.
