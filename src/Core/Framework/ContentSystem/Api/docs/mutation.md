# Mutation Endpoints

The nine stateless draft mutation actions and the request envelope they share. Their response body is described in [mutation-response.md](mutation-response.md), their error model in [mutation-errors.md](mutation-errors.md), and applying a binding specification through a mutation in [mutation-binding.md](mutation-binding.md).

```
POST /api/_action/content-system/layout/insert-element
POST /api/_action/content-system/layout/remove-element
POST /api/_action/content-system/layout/move-element
POST /api/_action/content-system/layout/replace-element
POST /api/_action/content-system/layout/duplicate-element
POST /api/_action/content-system/layout/wrap-elements
POST /api/_action/content-system/layout/unwrap-element
POST /api/_action/content-system/layout/attach-element
POST /api/_action/content-system/layout/bind-element
```

Apply exactly one structural edit to an **unsaved** draft layout and return the re-resolved layout plus a diagnostics report, **without** persisting. This is the assemble step done server-side: the caller sends the current draft tree and one edit, and gets back the edited, freshly diagnosed tree, ready to feed straight into the next edit or into preview. Served by `Api/LayoutMutationController`; route names follow `api.action.content_system.layout.<op>`, where `<op>` is `insert_element`, `remove_element`, `move_element`, `replace_element`, `duplicate_element`, `wrap_elements`, `unwrap_element`, `attach_element`, or `bind_element`.

Because each response already carries the diagnostics, a caller editing through these endpoints does not also call the diagnose endpoint. The optional `rootSource` binds that root source's context for binding-scope resolvability, using the same `Adapter/RootSourceRegistry::resolveGated()` selection as the diagnose endpoint (empty or omitted → only intrinsic well-formedness is evaluated).

## Request

Every action shares one envelope and adds its own operation fields. Shared fields: `layout` (raw element-tree array, decoded through the same `Layout/Codec/StoredElementCodec::decode()` path as a stored layout; defaults to an empty tree), `rootSource` (optional).

| Endpoint            | Operation fields                                                                                                                                                                                    |
|---------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `insert-element`    | `type` (required); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); `index` (optional); `bindingSpecificationId` (optional, source-qualified id `source:id` — applies the named specification onto the inserted element atomically after scaffold, see [mutation-binding.md](mutation-binding.md))                                                                    |
| `remove-element`    | `elementId` (required)                                                                                                                                                                              |
| `move-element`      | `elementId` (required); `newParentId` (optional, root when omitted); `newSlot` (required unless a same-parent move reuses the current slot); `index` (optional)                                     |
| `replace-element`   | `elementId` (required); `newType` (required)                                                                                                                                                        |
| `duplicate-element` | `elementId` (required); `index` (optional, next sibling when omitted)                                                                                                                               |
| `wrap-elements`     | `elementIds` (required, a non-empty list of ids that are siblings in one slot, or all roots); `containerType` (required); `slot` (required)                                                         |
| `unwrap-element`    | `containerElementId` (required)                                                                                                                                                                     |
| `attach-element`    | `element` (required, a raw element subtree to splice in; every id in it is reminted); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); `index` (optional) |
| `bind-element`      | `elementId` (required); `bindingSpecificationId` (required, source-qualified id `source:id` from the target element's type entry's [`bindingSpecifications`](../../Binding/docs/introspection.md) map on `content-system-element-types.json`)                                                                                       |

`index` is clamped, never rejected: a null, negative, or out-of-range `index` appends at the end of the target list.

`attach-element` is the inverse of the detachment a `replace` reports: hand its `orphaned` subtrees (or any copied subtree) back to `attach-element` to re-place them. Ids are server-minted, so the placed elements get fresh ids returned in `affectedElementIds`.

Example (`insert-element`):

```json
{
  "layout": [ { "id": "container-uuid", "component": "shopware/container", "slots": { "content": [] } } ],
  "type": "Sw:Content:Text",
  "parentElementId": "container-uuid",
  "slot": "content",
  "index": 0,
  "rootSource": "product"
}
```
