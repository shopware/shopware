# Preview URL

The preview-URL action that mints a short-lived, openable URL for a draft layout instead of rendering it inline.

`POST /api/_action/content-system/preview/entity/url`

Stores the **same envelope** as the entity preview action behind a short-lived token and returns a URL that renders it, so a draft can be opened or embedded (for example in an editor iframe) without re-posting the layout. Served by `Api/ContentPreviewController::previewUrl`. Route name: `api.action.content_system.preview.entity.url`. Requires the `content_layout:read` privilege.

The payload is held by `Api/ContentPreviewPayloadStore` under a 32-character token in the application cache for five minutes (`expiresAfter(300)`), never in the database. The returned URL points at the Storefront render route `GET /content-system/preview/{token}` (`frontend.content-system.preview`), which loads the payload back, builds the page through the same `ContentPreviewPageBuilder`, and serves the full-format `ContentPage` as an embeddable page (its `frame-ancestors` CSP is derived from the request `Referer`). An expired or unknown token renders a `404`.

## Request

Identical to the entity preview action: the same `ContentPreviewRequest` envelope (see the field table in [preview.md](preview.md)).

## Response

`200 OK` with a single field; the layout is stored, not rendered here:

```json
{ "url": "https://<host>/content-system/preview/<token>" }
```

## Errors

| Condition                           | HTTP | Factory / source                                    |
|-------------------------------------|------|-----------------------------------------------------|
| Missing/invalid envelope field      | 400  | `#[MapRequestPayload]` validation (forced to 400)   |
| The cache rejects the payload write | 500  | `ContentSystemException::previewPayloadStoreFailed` |

Entity resolution and hydration do not run at mint time; they happen when the returned URL is opened, so `unknownEntityType` and hydration faults surface on the Storefront render route, not here.
