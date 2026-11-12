# Resolve-and-Diagnose Endpoint

The diagnose action: its route, request envelope, and error model. The response body is described in [diagnose-response.md](diagnose-response.md).

`POST /api/_action/content-system/layout/diagnose`

Resolves every element property of an **unsaved** draft layout and reports what is structurally broken or still unresolved — **without** persisting and **without** rendering against a real entity. It answers the editor's after-local-edit "what is broken / still unresolved" question and backs agent layout linting. Served by `Api/ContentDiagnoseController`. Route name: `api.action.content_system.layout.diagnose`.

The optional `rootSource` selects a registered source through `Adapter/RootSourceRegistry::resolveGated()` for static binding checks. Omitted, null, or empty sources select no context; non-empty names are checked without trimming. See [mapping findings](diagnose-response.md#mapping-findings) for the checks available without a bound source.

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

## Errors

A malformed element **config** is reported as an `invalid_config` violation in the body (HTTP 200), not as an error. Only the conditions below abort the request (`ContentSystemException`):

| Condition                                                  | HTTP | Factory                                           |
|------------------------------------------------------------|------|---------------------------------------------------|
| Missing/invalid request field                              | 400  | `#[MapRequestPayload]` validation (forced to 400) |
| `rootSource` is a non-empty value not registered in `RootSourceRegistry` | 400  | `unknownRootSource` (the route gates membership against `RootSourceRegistry::knownRootSources()` before resolving, the same as the write validator) |
| Layout element missing a non-empty string `id`/`component` | 400  | `invalidLayoutStructure`                          |
| Collective validation fails or a per-file/per-row load step raises the matching `*_LOAD_FAILED` code for the element-type or style-option registry | 500 | `ELEMENT_TYPE_LOAD_FAILED` or `STYLE_OPTION_LOAD_FAILED` propagates from the registry read |

An internal fault during decoding (a non-[client-defect](../../docs/principles/README.md#glossary) `ContentSystemException`, e.g. an unexpected field type) propagates.

Runtime definition failures also include invalid filenames and binding canonicalization/type errors (500 with their specific codes). Preset-loading operations additionally expose preset load and validation errors. See [definition-load errors](../../docs/definition-load-errors.md) for the code/status mapping and app-install distinction.
