# Resolve-and-Diagnose Endpoint

The diagnose action: its route, request envelope, and error model. The response body is described in [diagnose-response.md](diagnose-response.md).

`POST /api/_action/content-system/layout/diagnose`

Resolves every element property of an **unsaved** draft layout and reports what is structurally broken or still unresolved — **without** persisting. By default it does not render against real entity data. Supply `runtimeContext` to run the draft through the normal Storefront render pipeline and include runtime mapping findings in the diagnostics response. Those findings remain separate from rendered output. Served by `Api/ContentDiagnoseController`. Route name: `api.action.content_system.layout.diagnose`.

The optional `rootSource` binds the draft to that root source's context so binding-scope resolvability can be checked. Without a source from either `rootSource` or `runtimeContext.entityType`, intrinsic well-formedness is still evaluated and explicit inline tokens in opted-in properties produce `invalid_mapping` violations; whole-field mappings and other binding checks remain unjudged. The value is resolved through `Adapter/RootSourceRegistry::resolveGated()` (an entity type, a section id such as `header`/`footer`, or `none`).

`runtimeContext` optionally requests data-aware diagnosis. It supplies an entity type, entity id, and sales-channel id, plus the same optional language, currency, domain, customer, and query parameters accepted by the preview request. Its `entityType` is used as the root source and must match an explicitly supplied `rootSource`. The action renders the draft with the normal pipeline, but returns only the diagnostic report and resolutions. Use this mode to find a catalogued mapping whose source has no value, a provider failure, or a value that cannot be converted for inline text.

## Request

```json
{
  "layout": [
    {
      "id": "element-uuid",
      "component": "shopware/product-heading",
      "properties": { "tag": "h1" },
      "dataRequirements": {},
      "slots": {},
      "providesContext": {},
      "acceptsContext": {}
    }
  ],
  "rootSource": "product"
}
```

| Field        | Required | Notes                                                                                                                                                                                                                         |
|--------------|----------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `layout`     | no       | Raw element-tree array; decoded through the same path as a stored layout (`Layout/Codec/StoredElementCodec::decode()`). Defaults to an empty tree when omitted.                                                          |
| `rootSource` | no       | The root source whose context the draft is checked against: an entity type (`content-system-entity-types.json` value), a section id (`header`, `footer`), or `none`. Resolved through `Adapter/RootSourceRegistry`. An unknown non-empty value is rejected with 400 (`unknownRootSource`). |
| `runtimeContext` | no | Optional render context to diagnose source values against real entity data. Requires `entityType`, `entityId`, and `salesChannelId`; optional context fields match the preview request. When provided, `entityType` must match `rootSource` if that field is also supplied. |

With `rootSource` empty or omitted and no `runtimeContext`, the response reports intrinsic well-formedness and `invalid_mapping` binding violations for explicit inline tokens in opted-in properties that require a bound source. Whole-field mappings and other binding checks remain unjudged.

## Errors

A malformed element **config** is reported as an `invalid_config` violation in the body (HTTP 200), not as an error — that is the point of the endpoint. Only the conditions below abort the request (`ContentSystemException`):

| Condition                                                  | HTTP | Factory                                           |
|------------------------------------------------------------|------|---------------------------------------------------|
| Missing/invalid envelope field                             | 400  | `#[MapRequestPayload]` validation (forced to 400) |
| `rootSource` is a non-empty value not registered in `RootSourceRegistry` | 400  | `unknownRootSource` (the route gates membership against `RootSourceRegistry::knownRootSources()` before resolving, the same as the write validator) |
| Layout element missing a non-empty string `id`/`component` | 400  | `invalidLayoutStructure`                          |

An internal fault during decoding (a non-client-defect `ContentSystemException`, e.g. an unexpected field type) propagates rather than being relabelled as an `invalid_config` violation — see `ContentSystemException::isClientDefect()`.
