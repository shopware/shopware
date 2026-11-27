# Persisted Mutation Endpoints

<!-- size-allowance: lookup - one Request-table entry per persisted mutation action, consulted one at a time -->

The persisted mutation actions, their request envelope, and their response. Their error model is described in [persisted-mutation-errors.md](persisted-mutation-errors.md).

Served by `Api/ContentLayoutMutationController` under the path prefix `/api/_action/content-system/layout/{layoutId}/`. Every route carries the ACL `content_layout:update` except `translate-element`, which carries `content_layout:read` and `content_layout:translate`, because its response contains the whole layout tree. `Mutation/PersistedLayoutMutator` checks the translate privilege and commits the edit in system scope, so the entity ACL does not also demand `content_layout:update` ([translate-element.md](../../Mutation/docs/translate-element.md)).

The persisted counterpart to the stateless mutation endpoints ([mutation.md](mutation.md)), for agents and automation operating on a **stored** layout. Each applies exactly one layout edit to the `content_layout` named in the path and **commits** the result, returning the same re-resolved layout plus diagnostics. The committing write runs the resolvability gates, so a persisted edit that breaks resolvability for a bound source is rejected and nothing is written. Route names follow `api.action.content_system.layout.persisted_<op>`.

Each action delegates to `Mutation/PersistedLayoutMutator::mutate()` (load by id, version-guard, apply, commit through the resolvability gates) and serializes the result into a `MutationResponse` like the stateless routes. `bind-element` and `insert-element` build the same `Mutation/Op/BindElement` and optionally-bound `Mutation/Op/InsertElement` as the stateless routes.

Unlike the stateless mutation endpoints, these load the tree from storage, so the body has no `layout` field. They derive binding-scope diagnostics from the layout's own immutable `root_source`, so the body has no `rootSource` hint.

A fresh insert with a required `resolvedBy` reference and no compatible mapping or context backing can fail with `UnfilledRequiredInput` (400): the request has no `properties` field for its manual picker ID. A compatible default mapping supplies an alternative source and skips that Stored-only picker-input check. Otherwise assemble the element through draft edits before persisting. See [mapping checks](../../Diagnostics/README.md#mapping-checks) and [default mappings](../../Mapping/README.md#stored-mappings-and-rendering).

> **Concurrency:** `expectedVersion` is the row's `updatedAt` at millisecond precision. `PersistedLayoutMutator::mutate()` holds the layout row lock that publishing also takes across load, version check, commit and diagnosis, so a second writer from the same state gets a `409`. Lock mechanism and interim limitations: [Mutation/docs/runners.md](../../Mutation/docs/runners.md#persistedlayoutmutator).

## Drafts

A draft is a named pointer into the layout's revision graph (`Layout/Revision/LayoutRevisionService`); a layout can have several at once.
`Api/ContentLayoutDraftController` manages them; every draft response carries the draft as `{id, name, baseRevisionId, headRevisionId, liveAtOpenRevisionId, createdAt, createdById, updatedAt}`.
`baseRevisionId` is the revision the draft was opened from and `liveAtOpenRevisionId` the revision live at that moment; they differ only for a rollback or a draft branched from another draft's head.
Comparing `liveAtOpenRevisionId` with the live revision is a hint only: the binding answer is the `409` on publish, because a layout write that bypassed publishing moves live only at the next draft write.

| Route                                                    | Body                         | ACL                     | Response                  |
|----------------------------------------------------------|------------------------------|-------------------------|---------------------------|
| `GET /api/_action/content-system/layout/{layoutId}/drafts`           | —                            | `content_layout:read`   | `{drafts}`, newest first  |
| `GET /api/_action/content-system/layout/{layoutId}/drafts/{draftId}` | —                            | `content_layout:read`   | `{draft, layout}`         |
| `GET /api/_action/content-system/layout/{layoutId}/published-revisions` | —                         | `content_layout:read`   | `{revisions}`, most recently live first |
| `POST /api/_action/content-system/layout/{layoutId}/create-draft`    | `name?`, `fromRevisionId?`   | `content_layout:update` | `{draft}`                 |
| `POST /api/_action/content-system/layout/{layoutId}/discard-draft`   | `draftId`                    | `content_layout:update` | `204`                     |
| `POST /api/_action/content-system/layout/{layoutId}/publish-draft`   | `draftId`, `expectedHeadRevisionId`    | `content_layout:update` | `204`                     |

Each `POST` binds its body to a request DTO (`ContentLayoutCreateDraftRequest`, `ContentLayoutDiscardDraftRequest`, `ContentLayoutPublishDraftRequest`) via `#[MapRequestPayload]`, so an unknown field is a `400` `UNKNOWN_REQUEST_FIELD` and a missing or wrongly typed field a `400`.
A `name` is at most 255 characters and not blank; without one the draft is named `Draft`.
A new draft starts at the live revision, or at `fromRevisionId` to roll back.
`published-revisions` lists the revisions a rollback can start from as `{id, live, createdAt, createdById, publishedAt, publishedById, lastLiveAt}`.
Like the draft reads it takes no lock, so a layout write that bypassed publishing is listed only after the next draft write adopts it.
Publishing writes the draft head to the live layout through the DAL, which enforces resolvability, and removes the draft.
If the live revision changed since the draft was opened, publishing is a `409` `LAYOUT_PUBLISH_CONFLICT` unless live only moved forward and the draft contains it.
Another draft or a direct layout write that went live in the meantime therefore conflicts, and so does a rollback, although every newer draft descends from the restored revision.
A draft branched from another draft's head can be published after that draft,
unless the write gate changed that draft's head on publishing it, for example by seeding a type default added since the edit.
The client reopens a draft from the new live state or discards it; publishing never replaces a live state the draft has not seen.
Rejections: [persisted-mutation-errors.md](persisted-mutation-errors.md#draft-routes).

Every draft edit stores the full tree as a new revision, and every edit, publish and discard then compacts the layout's history.
It keeps the live revision, the 20 most recently live revisions and each open draft's base, head and live-at-open revision, and deletes every other.
A revision restored by a rollback counts from the rollback, not from its first publication.
A draft therefore holds only its head, and a published draft leaves only the published state.
Each surviving revision is re-parented onto its nearest surviving ancestor, so the publish rule above judges the same ancestry as before.
A `fromRevisionId` that compaction removed is a `404`, so a draft branches from another draft's current head only, and a rollback reaches the last 20 live states.
Compaction runs under the layout row lock, so no job is needed and an untouched draft costs at most three revisions.

## Mutating a draft

A body with `draftId` edits that draft instead of the live layout.
The action applies the edit to the draft head and appends the result as one revision; the live layout is not written.
It delegates to `PersistedLayoutMutator::mutateDraft()`, which runs the stateless draft pipeline, consumer mirroring included, and admits the tree for well-formedness only, so only publishing enforces resolvability ([Mutation/docs/runners.md](../../Mutation/docs/runners.md#draft-target)).
The body carries `expectedHeadRevisionId`, the draft head revision id as last read, instead of `expectedVersion`.
A stale `expectedHeadRevisionId` is a `409`, a missing one a `400` `MISSING_REQUEST_FIELD`, and nothing is appended in either case.
The controller reads the three fields into an `Api/MutationTarget`, which rejects every other combination.
The response is the mutation response plus `draft`; the next mutation sends `draft.headRevisionId` as its `expectedHeadRevisionId`.
A live mutation with `expectedHeadRevisionId`, or a draft mutation with `expectedVersion`, is a `400` `MUTATION_TOKEN_TARGET_MISMATCH`.

## Request

The layout is named in the path.
Every body carries the operation's fields (identical to the stateless endpoints, minus the shared envelope) plus the token of its target: `expectedVersion` for the live layout, `draftId` and `expectedHeadRevisionId` for a draft.

| Field             | Required       | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
|-------------------|----------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `expectedVersion` | live: yes (nullable); draft: no | Optimistic-concurrency token of a live mutation: the layout's `updatedAt` as last read. `null` for a never-updated layout. A mismatch is a `409`, an unparseable token a `400`, and nothing is written in either case.                                                                                                                                                                                                                                                                                                         |
| `draftId`         | no             | The draft to mutate instead of the live layout. |
| `expectedHeadRevisionId`    | draft: yes; live: no | Optimistic-concurrency token of a draft mutation: the draft head revision id as last read (`draft.headRevisionId`). A stale head is a `409`, a missing one a `400`, and nothing is appended. |
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
