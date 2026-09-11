# Mutation Endpoints

<!-- size-allowance: lookup - one Request-table entry per mutation action, consulted one at a time -->

The stateless draft mutation actions and the request envelope they share. Their response body is described in [mutation-response.md](mutation-response.md), their error model in [mutation-errors.md](mutation-errors.md), and applying a binding specification through a mutation in [mutation-binding.md](mutation-binding.md).

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
POST /api/_action/content-system/layout/update-element-properties
```

Apply exactly one structural edit to an **unsaved** draft layout and return the re-resolved layout plus a diagnostics report, **without** persisting. This is the assemble step done server-side: the caller sends the current draft tree and one edit, and gets back the edited, freshly diagnosed tree, ready to feed straight into the next edit or into preview. Served by `Api/LayoutMutationController`; route names follow `api.action.content_system.layout.<op>`, where `<op>` is `insert_element`, `remove_element`, `move_element`, `replace_element`, `duplicate_element`, `wrap_elements`, `unwrap_element`, `attach_element`, `bind_element`, or `update_element_properties`.

Because each response already carries the diagnostics, a caller editing through these endpoints does not also call the diagnose endpoint. The optional `rootSource` binds that root source's context for binding-scope resolvability, using the same `Adapter/RootSourceRegistry::resolveGated()` selection as the diagnose endpoint (empty or omitted → only intrinsic well-formedness is evaluated).

Each action builds one `Mutation/Op` value object and calls the controller's private `respond()`, which decodes the draft layout via `DraftLayoutDecoder::decode()` and runs `Mutation/MutationPipeline::run()` on the decoded tree. `attach-element` additionally decodes the supplied `element` via `DraftLayoutDecoder::decodeOne()`. `bind-element` builds a `Mutation/Op/BindElement`; `insert-element` builds a `Mutation/Op/InsertElement` carrying the optional `bindingSpecificationId`; both draw the specification from the injected `Binding/Registry/AbstractContentSystemBindingSpecificationRegistry` and apply it through the injected `Binding/BindingApplicator`. `update-element-properties` builds a `Mutation/Op/UpdateElementProperties` from the request's `values` and `removeKeys`.

## Request

Every action shares one envelope and adds its own operation fields, bound via `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` placed on the controller action **parameter**, never on the DTO class. Shared fields: `layout` (raw element-tree array, decoded through the same `Layout/Codec/StoredElementCodec::decode()` path as a stored layout; defaults to an empty tree), `rootSource` (optional).

| Endpoint            | DTO | Operation fields                                                                                                                                                                                    |
|---------------------|-----|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `insert-element`    | `InsertElementRequest` | `type` (required); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); `index` (optional); `bindingSpecificationId` (optional, source-qualified id `source:id` — applies the named specification onto the inserted element atomically after scaffold, see [mutation-binding.md](mutation-binding.md)); the DTO stays `ALLOW_EXTRA_ATTRIBUTES => false`                                                                    |
| `remove-element`    | `RemoveElementRequest` | `elementId` (required)                                                                                                                                                                              |
| `move-element`      | `MoveElementRequest` | `elementId` (required); `newParentId` (optional, root when omitted); `newSlot` (required unless a same-parent move reuses the current slot); `index` (optional)                                     |
| `replace-element`   | `ReplaceElementRequest` | `elementId` (required); `newType` (required)                                                                                                                                                        |
| `duplicate-element` | `DuplicateElementRequest` | `elementId` (required); `index` (optional, next sibling when omitted)                                                                                                                               |
| `wrap-elements`     | `WrapElementsRequest` | `elementIds` (required, a non-empty list of ids that are siblings in one slot, or all roots; validated `#[Assert\Type('array')]` + `#[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]` + `#[Assert\Unique]`); `containerType` (required); `slot` (required)                                                         |
| `unwrap-element`    | `UnwrapElementRequest` | `containerElementId` (required)                                                                                                                                                                     |
| `attach-element`    | `AttachElementRequest` | `element` (required, a raw element subtree to splice in, decoded via `DraftLayoutDecoder::decodeOne()`; every id in it is reminted); `parentElementId` (optional, root when omitted); `slot` (required when a parent is given); `index` (optional) |
| `bind-element`      | `BindElementRequest` | `elementId` (required); `bindingSpecificationId` (required, source-qualified id `source:id` from the target element's type entry's [`bindingSpecifications`](../../Binding/docs/introspection.md) map on `content-system-element-types.json`)                                                                                       |
| `update-element-properties` | `UpdateElementPropertiesRequest` | `values` (optional, default `[]`, `array<string, mixed>` keyed by property key — each key must name a primitive property the type declares and admit the given value; written as supplied, replacing the current value); `removeKeys` (optional, default `[]`, `list<string>` of declared primitive keys to drop). At least one of `values` or `removeKeys` must be non-empty, enforced by the class-level `Validation/UpdateElementPropertiesNotEmpty` constraint, which the persisted route's DTO shares; the envelope 400 carries no error code, so clients identify the rejection by the `updateElementPropertiesEmpty` token in the message |

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
