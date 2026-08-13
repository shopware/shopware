# Preview Endpoint

The entity preview action: its route, request envelope, response, and error model.

`POST /api/_action/content-system/preview/entity`

Renders an externally supplied, **unsaved** draft layout against **real** entity data and returns a fully hydrated `ContentPage` in full format. The layout is neither persisted nor required to have an assignment. The target entity only needs to exist. Served by `Api/ContentPreviewController`. Route name: `api.action.content_system.preview.entity`.

## Request

```json
{
  "layout": [
    {
      "id": "element-uuid",
      "component": "shopware/product-heading",
      "properties": { "tag": "h1" },
      "dataRequirements": {
        "product": { "key": "product", "source": "entity", "config": { "entity": "product", "property": "product_id" } }
      },
      "slots": {},
      "providesContext": {},
      "acceptsContext": {}
    }
  ],
  "entityType": "product",
  "entityId": "abc-123-def-456",
  "salesChannelId": "def-456-ghi-789",
  "languageId": null,
  "currencyId": null,
  "domainId": null,
  "customerId": null,
  "queryParameters": { "elementId": "optional-element-id" }
}
```

| Field                                                | Required | Notes                                                                                                                        |
|------------------------------------------------------|----------|------------------------------------------------------------------------------------------------------------------------------|
| `layout`                                             | yes      | Raw element-tree array; decoded through the same path as a stored layout (`ContentElementFieldSerializer::decodeElement()`). |
| `entityType`                                         | yes      | One of the `content-system-entity-types.json` values; selected by exact match, never as a URL segment.                       |
| `entityId`                                           | yes      | Id of the entity to hydrate against; the entity must exist.                                                                  |
| `salesChannelId`                                     | yes      | Sales channel whose context is synthesized for rendering.                                                                    |
| `languageId`, `currencyId`, `domainId`, `customerId` | no       | Override the synthesized sales channel context.                                                                              |
| `queryParameters`                                    | no       | Forwarded as request query; `elementId` selects a single element for partial preview.                                        |

## Response

The full-format `ContentPage`, serialized through the full-format response factory, using the same structure as the Store API full format. No caching. The preview always renders fresh.

## Errors

Envelope and intrinsic-layout failures are rejected with `400 Bad Request` (`ContentSystemException`). Unlike diagnose and mutate, preview renders against real entity data, so a fault raised during hydration keeps its own status instead of collapsing to 400 (see the HTTP column):

| Condition                                                                                                      | HTTP      | Factory / source                                                                                                      |
|----------------------------------------------------------------------------------------------------------------|-----------|-----------------------------------------------------------------------------------------------------------------------|
| Missing/invalid envelope field                                                                                 | 400       | `#[MapRequestPayload]` validation (forced to 400)                                                                     |
| `entityType` matches no specification source                                                                   | 400       | `unknownEntityType`                                                                                                   |
| Layout element missing a non-empty string `id`/`component`; a duplicate element `id`, nesting past the maximum depth, or a non-array nested child; or an element config that is a client defect | 400 | `invalidLayoutStructure` |
| Layout has an intrinsic error the structural decode does not catch: an unregistered `component`, or an element config a bound source rejects (`invalid_config`) | 400 | `elementTypesInvalid` (via `DraftLayoutChecker`, which surfaces every intrinsic-scope error from `LayoutDiagnostics`) |
| Target entity not found / unresolvable data requirement                                                        | 500       | data-loader / hydration exception (e.g. `ContentSystemException::dataLoaderNotRegistered`)                            |
| Invalid sales channel id                                                                                       | 404 / 412 | `SalesChannelException` (not a `ContentSystemException`)                                                              |
