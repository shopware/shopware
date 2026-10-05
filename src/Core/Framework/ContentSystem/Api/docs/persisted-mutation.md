# Persisted Mutation Endpoints

<!-- size-allowance: lookup - one Request-table entry per persisted mutation action, consulted one at a time -->

The persisted mutation actions, their request envelope, and their response. Their error model is described in [persisted-mutation-errors.md](persisted-mutation-errors.md).

Served by `Api/ContentLayoutMutationController` under the path prefix `/api/_action/content-system/layout/{layoutId}/`.

The persisted counterpart to the stateless mutation endpoints ([mutation.md](mutation.md)), for agents and automation operating on a **stored** layout. Each applies exactly one structural edit to the `content_layout` named in the path and **commits** the result, returning the same re-resolved layout plus diagnostics. The committing write runs the resolvability gates, so a persisted edit that breaks resolvability for a bound source is rejected and nothing is written. Route names follow `api.action.content_system.layout.persisted_<op>`.

Each action delegates to `Mutation/PersistedLayoutMutator::mutate()` (load by id, version-guard, apply, commit through the resolvability gates) and serializes the result into a `MutationResponse` like the stateless routes. `bind-element` and `insert-element` build the same `Mutation/Op/BindElement` and optionally-bound `Mutation/Op/InsertElement` as the stateless routes.

Unlike the stateless mutation endpoints, these load the tree from storage, so the body has no `layout` field. They derive binding-scope diagnostics from the layout's own immutable `root_source`, so the body has no `rootSource` hint.

A persisted `insert-element` of a type with a required `resolvedBy` reference is always rejected, with or without an explicit `bindingSpecificationId`. An example is `Sw:Media:Image`, whose default specification wires `media` from the `mediaId` storage key. The scaffold applies the type's default regardless (see "Automatic default application" in [mutation-binding.md](mutation-binding.md)). The request carries no `properties` field, so the freshly scaffolded element cannot hold the entity id. The committing gate raises `UnfilledRequiredInput` (400) by design. Assemble such an element on the stateless draft route and persist the finished tree once its required references carry values.

> **Concurrency:** `expectedVersion` is the row's `updatedAt` at millisecond precision. `PersistedLayoutMutator::mutate()` holds a named lock keyed by layout id across load, version check and commit, so a second writer from the same revision gets a `409`. Lock mechanism and interim limitations: [Mutation/docs/runners.md](../../Mutation/docs/runners.md#persistedlayoutmutator).

## Request

The layout is named in the path. Every body carries the operation's fields (identical to the stateless endpoints, minus the shared envelope) plus `expectedVersion`.

| Field             | Required       | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
|-------------------|----------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `expectedVersion` | yes (nullable) | Optimistic-concurrency token: the layout's `updatedAt` as last read. `null` for a never-updated layout. A mismatch is a `409`, an unparseable token a `400`, and nothing is written in either case.                                                                                                                                                                                                                                                                                                         |
| operation fields  | per op         | `insert-element` (`ContentLayoutInsertRequest`): `type` (+ `parentElementId`, `slot`, `index`, `bindingSpecificationId`); `remove-element` (`ContentLayoutRemoveRequest`): `elementId`; `move-element` (`ContentLayoutMoveRequest`): `elementId` (+ `newParentId`, `newSlot`, `index`); `replace-element` (`ContentLayoutReplaceRequest`): `elementId`, `newType`; `duplicate-element` (`ContentLayoutDuplicateRequest`): `elementId` (+ `index`); `wrap-elements` (`ContentLayoutWrapElementsRequest`): `elementIds`, `containerType`, `slot`; `unwrap-element` (`ContentLayoutUnwrapRequest`): `containerElementId`; `attach-element` (`ContentLayoutAttachRequest`): `element` (a raw subtree, ids reminted) (+ `parentElementId`, `slot`, `index`); `bind-element` (`ContentLayoutBindRequest`): `elementId`, `bindingSpecificationId`. |

Example (`replace-element`):

```json
{
  "elementId": "block-uuid",
  "newType": "Sw:Content:Text",
  "expectedVersion": "2026-06-22T10:00:00.000+00:00"
}
```

## Response

`200 OK` with the same shape as the stateless endpoints ([mutation-response.md](mutation-response.md)), but the layout is now committed. A `replace` that detaches the children of a slot the new type does not have commits the tree **without** them and returns them in `orphaned`. Static property values the new type cannot hold appear in `droppedProperties`. The caller re-places the children with an `attach-element` call. `diagnostics` reflects the layout's own immutable `root_source`, resolved once: the binding-scope violations are those for that single root source.
