# Admin Route Index

<!-- size-allowance: lookup - one row per route, consulted one at a time -->

Every route the content system exposes on the Admin API, with its name and the OpenAPI path file that carries its schema. All of them run under `ApiRouteScope`; the `_action` routes are `POST`, the introspection routes `GET`.

## Draft Routes

Operate on a layout tree carried in the request body and persist nothing.

| Path | Route name |
|---|---|
| `/api/_action/content-system/preview/entity/url` | `api.action.content_system.preview.entity.url` |
| `/api/_action/content-system/layout/diagnose` | `api.action.content_system.layout.diagnose` |
| `/api/_action/content-system/layout/insert-element` | `api.action.content_system.layout.insert_element` |
| `/api/_action/content-system/layout/remove-element` | `api.action.content_system.layout.remove_element` |
| `/api/_action/content-system/layout/move-element` | `api.action.content_system.layout.move_element` |
| `/api/_action/content-system/layout/replace-element` | `api.action.content_system.layout.replace_element` |
| `/api/_action/content-system/layout/duplicate-element` | `api.action.content_system.layout.duplicate_element` |
| `/api/_action/content-system/layout/wrap-elements` | `api.action.content_system.layout.wrap_elements` |
| `/api/_action/content-system/layout/unwrap-element` | `api.action.content_system.layout.unwrap_element` |
| `/api/_action/content-system/layout/attach-element` | `api.action.content_system.layout.attach_element` |
| `/api/_action/content-system/layout/insert-preset` | `api.action.content_system.layout.insert_preset` |
| `/api/_action/content-system/layout/bind-element` | `api.action.content_system.layout.bind_element` |

`insert-preset` is the one draft route with no persisted counterpart. It resolves a preset through the preset registry, decodes its payload, and splices the result in as an `AttachElements` mutation.

## Persisted Routes

Load the layout named by `{layoutId}`, apply the operation, and commit through the write gates.

| Path | Route name |
|---|---|
| `/api/_action/content-system/layout/{layoutId}/insert-element` | `api.action.content_system.layout.persisted_insert_element` |
| `/api/_action/content-system/layout/{layoutId}/remove-element` | `api.action.content_system.layout.persisted_remove_element` |
| `/api/_action/content-system/layout/{layoutId}/move-element` | `api.action.content_system.layout.persisted_move_element` |
| `/api/_action/content-system/layout/{layoutId}/replace-element` | `api.action.content_system.layout.persisted_replace_element` |
| `/api/_action/content-system/layout/{layoutId}/duplicate-element` | `api.action.content_system.layout.persisted_duplicate_element` |
| `/api/_action/content-system/layout/{layoutId}/wrap-elements` | `api.action.content_system.layout.persisted_wrap_elements` |
| `/api/_action/content-system/layout/{layoutId}/unwrap-element` | `api.action.content_system.layout.persisted_unwrap_element` |
| `/api/_action/content-system/layout/{layoutId}/attach-element` | `api.action.content_system.layout.persisted_attach_element` |
| `/api/_action/content-system/layout/{layoutId}/bind-element` | `api.action.content_system.layout.persisted_bind_element` |

## Introspection

Served by `Framework/Api/Controller/InfoController`, paired with these actions by the Admin UI: `content-system-element-types`, `content-system-data-loaders`, `content-system-entity-types`, `content-system-style-options`, `content-system-root-sources`.

## OpenAPI

Every route above carries an entry under `AdminApi/paths/`: `content-system-preview.json`, `content-system-diagnose.json`, `content-system-layout-mutation.json`, `content-system-layout-persisted-mutation.json`, plus one file per introspection route (`content-system-{element,data-loader,entity}-types.json`, `content-system-style-options.json`, `content-system-root-sources.json`). The path files carry the request and response schemas; the human contract with its error model and examples is indexed in [../README.md](../README.md#endpoint-reference).
