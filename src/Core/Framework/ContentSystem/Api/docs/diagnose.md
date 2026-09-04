# Resolve-and-Diagnose Endpoint

The diagnose action: its route, request envelope, and error model. The response body is described in [diagnose-response.md](diagnose-response.md).

`POST /api/_action/content-system/layout/diagnose`

Resolves every element property of an **unsaved** draft layout and reports what is structurally broken or still unresolved — **without** persisting and **without** rendering against a real entity. It answers the editor's after-local-edit "what is broken / still unresolved" question and backs agent layout linting. Served by `Api/ContentDiagnoseController`. Route name: `api.action.content_system.layout.diagnose`.

The optional `rootSource` binds the draft to that root source's context so binding-scope resolvability can be checked. Without it, only intrinsic well-formedness is evaluated. The value is resolved through `Adapter/RootSourceRegistry::resolveGated()` (an entity type, a section id such as `header`/`footer`, or `none`).

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

With `rootSource` empty or omitted, the response still reports intrinsic well-formedness; binding-scope violations are not evaluated because no root source is bound.

## Errors

A malformed element **config** is reported as an `invalid_config` violation in the body (HTTP 200), not as an error — that is the point of the endpoint. Only the conditions below abort the request (`ContentSystemException`):

| Condition                                                  | HTTP | Factory                                           |
|------------------------------------------------------------|------|---------------------------------------------------|
| Missing/invalid envelope field                             | 400  | `#[MapRequestPayload]` validation (forced to 400) |
| `rootSource` is a non-empty value not registered in `RootSourceRegistry` | 400  | `unknownRootSource` (the route gates membership against `RootSourceRegistry::knownRootSources()` before resolving, the same as the write validator) |
| Layout element missing a non-empty string `id`/`component` | 400  | `invalidLayoutStructure`                          |

An internal fault during decoding (a non-client-defect `ContentSystemException`, e.g. an unexpected field type) propagates rather than being relabelled as an `invalid_config` violation — see `ContentSystemException::isClientDefect()`.
