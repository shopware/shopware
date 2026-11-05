# Mutation Endpoints

<!-- size-allowance: lookup - one Request-table entry per mutation action, consulted one at a time -->

The stateless draft mutation actions and the request envelope they share. Their response body is described in [mutation-response.md](mutation-response.md), their error model in [mutation-errors.md](mutation-errors.md), and applying a binding specification through a mutation in [mutation-binding.md](mutation-binding.md).

Served by `Api/LayoutMutationController` under the path prefix `/api/_action/content-system/layout/`.

Apply exactly one layout edit to an **unsaved** draft layout and return the re-resolved layout plus a diagnostics report, **without** persisting. This is the assemble step done server-side. The caller sends the current draft tree and one edit, and gets back the edited, freshly diagnosed tree, ready to feed straight into the next edit or into preview. Route names follow `api.action.content_system.layout.<op>`.

Each response already carries diagnostics, so a caller need not diagnose again. `rootSource` selects context through `Adapter/RootSourceRegistry::resolveGated()` and is required for `map-property`. Other actions accept an omitted, null, or empty source for an unbound draft. See [mapping findings](diagnose-response.md#mapping-findings) for the checks available without a source. Non-empty names are checked without trimming.

`bind-element` and `insert-element` draw the specification from `Binding/Registry/AbstractContentSystemBindingSpecificationRegistry` and apply it through `Binding/BindingApplicator`.

## Request

Every action shares one envelope and adds its own operation fields, bound via `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` placed on the controller action **parameter**, never on the DTO class. Shared fields: `layout` (raw element-tree array, decoded through the same `Layout/Codec/StoredElementCodec::decode()` path as a stored layout; defaults to an empty tree), `rootSource` (required and non-blank for `map-property`, optional otherwise).

| Endpoint            | DTO | Operation fields                                                                                                                                                                                    |
|---------------------|-----|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `insert-element`    | `InsertElementRequest` | `type` (required); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); `index` (optional); `bindingSpecificationId` (optional, source-qualified id `source:id`, applies the named specification onto the inserted element atomically after scaffold, see [mutation-binding.md](mutation-binding.md)); no extra attributes ([why](../../docs/principles/failure-and-loss.md#the-module-drops-nothing-silently-and-reports-every-loss)) |
| `remove-element`    | `RemoveElementRequest` | `elementId` (required)                                                                                                                                                                              |
| `move-element`      | `MoveElementRequest` | `elementId` (required); `newParentId` (optional, root when omitted); `newSlot` (required unless a same-parent move reuses the current slot); `index` (optional)                                     |
| `replace-element`   | `ReplaceElementRequest` | `elementId` (required); `newType` (required)                                                                                                                                                        |
| `duplicate-element` | `DuplicateElementRequest` | `elementId` (required); `index` (optional, next sibling when omitted)                                                                                                                               |
| `wrap-elements`     | `WrapElementsRequest` | `elementIds` (required, a non-empty list of unique ids that are siblings in one slot, or all roots); `containerType` (required); `slot` (required)                                                         |
| `unwrap-element`    | `UnwrapElementRequest` | `containerElementId` (required)                                                                                                                                                                     |
| `attach-element`    | `AttachElementRequest` | `element` (required, a raw element subtree to splice in, decoded via `DraftLayoutDecoder::decodeOne()`; every id in it is regenerated); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); `index` (optional) |
| `insert-preset`     | `InsertPresetRequest` | `presetId` (required, the id of a registered layout preset); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); no `index`, so the preset's root elements append in payload order |
| `bind-element`      | `BindElementRequest` | `elementId` (required); `bindingSpecificationId` (required, source-qualified id `source:id` from the target element's type entry's [`bindingSpecifications`](../../Binding/docs/introspection.md) map on `content-system-element-types.json`)                                                                                       |
| `map-property` | `MapPropertyRequest` | `elementId`, `propertyKey`, and `source` (required typed source reference); `rootSource` (required, non-blank registered source). The server derives cardinality and validates admission. |
| `unmap-property` | `UnmapPropertyRequest` | `elementId`, `propertyKey` (required). Removes the typed mapping while preserving ordinary wiring. |

`index` is clamped, never rejected: a null, negative, or out-of-range `index` appends at the end of the target list.

`attach-element` is the inverse of the detachment a `replace` reports: hand its `orphaned` subtrees (or any copied subtree) back to `attach-element` to re-place them. Ids are server-generated, so the placed elements get fresh ids returned in `affectedElementIds`. Copied or detached authored elements preserve their mappings, selected entities, and deliberate unmapping; new ids alone do not trigger default mappings or consumer mirroring.

`insert-preset` reads the named preset from the layout preset registry, decodes its payload through `DraftLayoutDecoder::decode()`, and places every root element the way `attach-element` places one subtree: ids are regenerated and each element's type default binding is fill-applied. The preset comes from the registry, never from the request, so the client names a preset and cannot alter its content. An unregistered `presetId` is a `404` (`layoutPresetNotFound`, see [mutation-errors.md](mutation-errors.md)).

Default mappings initialize only the explicit `freshlyScaffolded()` set: new elements, preset insertions, replacement scaffolds, and wrapping containers. Ordinary copies and reattachments preserve authored choices. Direct DAL/Admin CRUD/Sync writes do not seed mapping consumers. See [default mapping rules](../../Mapping/README.md#stored-mappings-and-rendering).

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
