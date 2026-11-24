# Persisted Mutation Endpoints

<!-- size-allowance: lookup - one Request-table entry per persisted mutation action, consulted one at a time -->

The persisted mutation actions, their request envelope, and their response. Their error model is described in [persisted-mutation-errors.md](persisted-mutation-errors.md).

Served by `Api/ContentLayoutMutationController` under the path prefix `/api/_action/content-system/layout/{layoutId}/`. Every route carries the ACL `content_layout:update` except `translate-element`, which carries `content_layout:read` and `content_layout:translate`, because its response contains the whole layout tree. `Mutation/PersistedLayoutMutator` checks the translate privilege and commits the edit in system scope, so the entity ACL does not also demand `content_layout:update` ([translate-element.md](../../Mutation/docs/translate-element.md)).

The persisted counterpart to the stateless mutation endpoints ([mutation.md](mutation.md)), for agents and automation operating on a **stored** layout. Each applies exactly one layout edit to the `content_layout` named in the path and **commits** the result, returning the same re-resolved layout plus diagnostics. The committing write runs the resolvability gates, so a persisted edit that breaks resolvability for a bound source is rejected and nothing is written. Route names follow `api.action.content_system.layout.persisted_<op>`.

Each action delegates to `Mutation/PersistedLayoutMutator::mutate()` (load by id, version-guard, apply, commit through the resolvability gates) and serializes the result into a `MutationResponse` like the stateless routes. `bind-element` and `insert-element` build the same `Mutation/Op/BindElement` and optionally-bound `Mutation/Op/InsertElement` as the stateless routes.

Unlike the stateless mutation endpoints, these load the tree from storage, so the body has no `layout` field. They derive binding-scope diagnostics from the layout's own immutable `root_source`, so the body has no `rootSource` hint.

A fresh insert with a required `resolvedBy` reference and no compatible mapping or context backing can fail with `UnfilledRequiredInput` (400): the request has no `properties` field for its manual picker ID. A compatible default mapping supplies an alternative source and skips that Stored-only picker-input check. Otherwise assemble the element through draft edits before persisting. See [mapping checks](../../Diagnostics/README.md#mapping-checks) and [default mappings](../../Mapping/README.md#stored-mappings-and-rendering).

> **Concurrency:** `expectedVersion` is the row's `updatedAt` at millisecond precision. `PersistedLayoutMutator::mutate()` holds the layout row lock that publishing also takes across load, version check, commit and diagnosis, so a second writer from the same state gets a `409`. Lock mechanism and interim limitations: [Mutation/docs/runners.md](../../Mutation/docs/runners.md#persistedlayoutmutator).

## Branch routes

Every action also has a branch route under `/api/_action/content-system/layout/{layoutId}/branches/{branchId}/`, with the same ACL and the route name `api.action.content_system.layout.branch_<op>`.
A branch route applies the edit to the head of the draft branch and appends the result as one revision; the live layout is not written.
It delegates to `PersistedLayoutMutator::mutateBranch()`, which runs the stateless draft pipeline, consumer mirroring included, and admits the tree like a saved draft, so only publishing enforces resolvability ([Mutation/docs/runners.md](../../Mutation/docs/runners.md#branch-target)).
The body carries `expectedHead`, the branch head revision id as last read, instead of `expectedVersion`.
A stale or missing `expectedHead` is a `409` and nothing is appended.
The response is the mutation response plus `revision` and `branch`, in the shapes the revision routes return; the next mutation sends `branch.head` as its `expectedHead`.
Each route rejects the other route's token with a `400` `UNKNOWN_REQUEST_FIELD`.

## Request

The layout is named in the path.
Every body carries the operation's fields (identical to the stateless endpoints, minus the shared envelope) plus the token of its route: `expectedVersion` on live routes, `expectedHead` on branch routes.

| Field             | Required       | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
|-------------------|----------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `expectedVersion` | live routes: yes (nullable); branch routes: no | Optimistic-concurrency token of the live routes: the layout's `updatedAt` as last read. `null` for a never-updated layout. A mismatch is a `409`, an unparseable token a `400`, and nothing is written in either case.                                                                                                                                                                                                                                                                                                         |
| `expectedHead`    | branch routes: yes; live routes: no | Optimistic-concurrency token of the branch routes: the branch head revision id as last read (`branch.head`). A missing or stale head is a `409` and nothing is appended. |
| operation fields  | per op         | `insert-element` (`ContentLayoutInsertRequest`): `type` (+ `parentElementId`, `slot`, `index`, `bindingSpecificationId`); `remove-element` (`ContentLayoutRemoveRequest`): `elementId`; `move-element` (`ContentLayoutMoveRequest`): `elementId` (+ `newParentId`, `newSlot`, `index`); `replace-element` (`ContentLayoutReplaceRequest`): `elementId`, `newType`; `duplicate-element` (`ContentLayoutDuplicateRequest`): `elementId` (+ `index`); `wrap-elements` (`ContentLayoutWrapElementsRequest`): `elementIds`, `containerType`, `slot`; `unwrap-element` (`ContentLayoutUnwrapRequest`): `containerElementId`; `attach-element` (`ContentLayoutAttachRequest`): `element` (a raw subtree, ids reminted) (+ `parentElementId`, `slot`, `index`); `bind-element` (`ContentLayoutBindRequest`): `elementId`, `bindingSpecificationId`; `map-property` (`ContentLayoutMapPropertyRequest`): `elementId`, `propertyKey`, `source`; `unmap-property` (`ContentLayoutUnmapPropertyRequest`): `elementId`, `propertyKey`; `update-element-properties` (`ContentLayoutUpdateElementPropertiesRequest`): `elementId` (+ `values`, `removeKeys`, at least one non-empty, per [mutation.md](mutation.md#request)); `translate-element` (`ContentLayoutTranslateElementRequest`): `elementId`, `values` (non-empty, per [mutation.md](mutation.md#request)). |

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

Default mappings initialize only explicit fresh scaffolds. Copied or detached authored subtrees attached through `attach-element` preserve their mapping/manual-selection state. Mapping actions use the stored root source; no request `rootSource` field is accepted.
