# Persisted Mutation Endpoints

<!-- size-allowance: lookup - one Request-table entry per persisted mutation action, consulted one at a time -->

The persisted mutation actions, their request envelope, and their response. Their error model is described in [persisted-mutation-errors.md](persisted-mutation-errors.md).

```
POST /api/_action/content-system/layout/{layoutId}/insert-element
POST /api/_action/content-system/layout/{layoutId}/remove-element
POST /api/_action/content-system/layout/{layoutId}/move-element
POST /api/_action/content-system/layout/{layoutId}/replace-element
POST /api/_action/content-system/layout/{layoutId}/duplicate-element
POST /api/_action/content-system/layout/{layoutId}/wrap-elements
POST /api/_action/content-system/layout/{layoutId}/unwrap-element
POST /api/_action/content-system/layout/{layoutId}/attach-element
POST /api/_action/content-system/layout/{layoutId}/bind-element
POST /api/_action/content-system/layout/{layoutId}/update-element-properties
```

The persisted counterpart to the stateless mutation endpoints ([mutation.md](mutation.md)), for agents and automation operating on a **stored** layout. Each applies exactly one structural edit to the `content_layout` named in the path and **commits** the result, returning the same re-resolved layout plus diagnostics. The committing write runs the resolvability gates, so a persisted edit that breaks resolvability for a bound source is rejected and nothing is written. Served by `Api/ContentLayoutMutationController`; route names follow `api.action.content_system.layout.persisted_<op>`.

Each action takes the `{layoutId}` path argument plus its request DTO, builds one `Mutation/Op`, and calls the controller's private `respond()`, which delegates to `Mutation/PersistedLayoutMutator::mutate()` (load by id, version-guard, apply, commit through the resolvability gates) and serializes the result into a `MutationResponse` like the stateless routes. `attach-element` decodes the supplied `element` via the injected `DraftLayoutDecoder::decodeOne()`; `bind-element` and `insert-element` build the same `Mutation/Op/BindElement` and optionally-bound `Mutation/Op/InsertElement` as the stateless routes, both from the injected `Binding/Registry/AbstractContentSystemBindingSpecificationRegistry` and `Binding/BindingApplicator`; `update-element-properties` builds the same `Mutation/Op/UpdateElementProperties` as the stateless routes.

Unlike the stateless mutation endpoints, these load the tree from storage (so there is no `layout` field in the body) and derive binding-scope diagnostics from the layout's own immutable `root_source` (so there is no `rootSource` hint in the body).

A persisted `insert-element` of a type with a required `resolvedBy` reference — for example `Sw:Media:Image`, whose default specification wires `media` from the `mediaId` storage key — is always rejected, with or without an explicit `bindingSpecificationId`: the type's default is fill-applied at scaffold regardless (see "Automatic default application" in [mutation-binding.md](mutation-binding.md)), the request carries no `properties` field, so the freshly scaffolded element cannot hold the entity id, and the committing gate raises `UnfilledRequiredInput` (400). This is served-implies-resolvable by design — assemble such an element on the stateless draft route and persist the finished tree once its required references carry values.

> **Concurrency:** `expectedVersion` is a pragmatic interim token built on the row's `updatedAt`, compared at millisecond precision (the storage precision). On its own it is not a compare-and-swap, so the lost-update window it would otherwise leave open is closed by serializing concurrent writers: `PersistedLayoutMutator::mutate()` holds a named lock keyed by layout id across the load → version-check → commit span. A second writer that started from the same revision blocks on that lock, then re-reads the now-bumped `updatedAt`, fails the version check, and gets a `409` instead of clobbering the first edit. A real layout versioning system (draft/published revisions with explicit version identifiers) is still planned and will supersede this interim token with richer version identifiers.

## Request

The layout is named in the path. Every body carries the operation's fields (identical to the stateless endpoints, minus the shared envelope) plus `expectedVersion`.

| Field             | Required       | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
|-------------------|----------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `expectedVersion` | yes (nullable) | Optimistic-concurrency token: the layout's `updatedAt` as last read. `null` for a never-updated layout. A mismatch is a `409`, an unparseable token a `400`, and nothing is written in either case.                                                                                                                                                                                                                                                                                                         |
| operation fields  | per op         | `insert-element` (`ContentLayoutInsertRequest`): `type` (+ `parentElementId`, `slot`, `index`, `bindingSpecificationId`); `remove-element` (`ContentLayoutRemoveRequest`): `elementId`; `move-element` (`ContentLayoutMoveRequest`): `elementId` (+ `newParentId`, `newSlot`, `index`); `replace-element` (`ContentLayoutReplaceRequest`): `elementId`, `newType`; `duplicate-element` (`ContentLayoutDuplicateRequest`): `elementId` (+ `index`); `wrap-elements` (`ContentLayoutWrapElementsRequest`): `elementIds`, `containerType`, `slot`; `unwrap-element` (`ContentLayoutUnwrapRequest`): `containerElementId`; `attach-element` (`ContentLayoutAttachRequest`): `element` (a raw subtree, ids reminted) (+ `parentElementId`, `slot`, `index`); `bind-element` (`ContentLayoutBindRequest`): `elementId`, `bindingSpecificationId`; `update-element-properties` (`ContentLayoutUpdateElementPropertiesRequest`): `elementId` (+ `values`, `removeKeys`, both optional with at least one non-empty, per [mutation.md](mutation.md)). |

Example (`replace-element`):

```json
{
  "elementId": "block-uuid",
  "newType": "Sw:Content:Text",
  "expectedVersion": "2026-06-22T10:00:00.000+00:00"
}
```

## Response

`200 OK` with the same `{ layout, resolutions, diagnostics, affectedElementIds, orphaned, droppedWiring, droppedProperties }` shape as the stateless endpoints — but the layout is now committed. A `replace` that detaches the children of a slot the new type does not have commits the tree **without** them and returns them in `orphaned` (and any static property values the new type cannot hold in `droppedProperties`); nothing is silently lost, and the caller re-places them with an `attach-element` call. `diagnostics` reflects the layout's own immutable `root_source`, resolved once: the binding-scope violations are those for that single root source.
