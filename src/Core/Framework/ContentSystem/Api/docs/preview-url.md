# Preview URL

The preview-URL action that mints a short-lived, openable URL for a draft layout instead of rendering it inline.

`POST /api/_action/content-system/preview/entity/url`

Stores the **same envelope** as the entity preview action behind a short-lived token and returns a URL that renders it, so a draft can be opened or embedded (for example in an editor iframe) without re-posting the layout. Served by `Api/ContentPreviewController::previewUrl`. Route name: `api.action.content_system.preview.entity.url`. Requires the `content_layout:read` privilege.

Before anything is stored, the draft is admitted through the same `Api/ContentPreviewPageBuilder::build()` the entity preview action runs, and the built page is discarded, so the two POST routes cannot drift apart. A draft that gate refuses is a 400 and never becomes a token. Minting pays one full build.

The payload is held by `Api/ContentPreviewPayloadStore` under a 32-character token in the application cache for five minutes (`expiresAfter(300)`), never in the database. The returned URL points at the Storefront render route `GET /content-system/preview/{token}` (`frontend.content-system.preview`), which loads the payload back, builds the page through the same `ContentPreviewPageBuilder`, and serves the full-format `ContentPage` as an embeddable page (its `frame-ancestors` CSP is derived from the request `Referer`). An expired or unknown token renders a `404`.

## Request

Identical to the entity preview action: the same `ContentPreviewRequest` envelope (see the field table in [preview.md](preview.md)).

## Response

`200 OK` with a single field; the layout is stored, not rendered here:

```json
{ "url": "https://<host>/content-system/preview/<token>" }
```

## Errors

The mint runs the one build gate, so its failure surface is the entity preview action's (see [preview.md](preview.md)) plus the store write:

| Condition                                                                                                      | HTTP      | Factory / source                                                                                                      |
|----------------------------------------------------------------------------------------------------------------|-----------|-----------------------------------------------------------------------------------------------------------------------|
| Missing/invalid envelope field                                                                                 | 400       | `#[MapRequestPayload]` validation (forced to 400)                                                                     |
| `entityType` matches no specification source                                                                   | 400       | `unknownEntityType`                                                                                                   |
| Layout element missing a non-empty string `id`/`component`; a duplicate element `id`, nesting past the maximum depth, or a non-array nested child; or an element config that is a client defect | 400 | `invalidLayoutStructure` |
| Layout has an intrinsic error the structural decode does not catch: an unregistered `component`, an unknown style option, or an element config a bound source rejects (`invalid_config`) | 400 | `elementTypesInvalid` (via `DraftLayoutChecker`) |
| Target entity not found / unresolvable data requirement                                                        | 500       | data-loader / hydration exception (e.g. `ContentSystemException::dataLoaderNotRegistered`)                            |
| Invalid sales channel id                                                                                       | 404 / 412 | `SalesChannelException` (not a `ContentSystemException`)                                                              |
| The cache rejects the payload write                                                                            | 500       | `ContentSystemException::previewPayloadStoreFailed`                                                                   |

Entity resolution and hydration run at mint time as well as when the URL is opened, so `unknownEntityType` and hydration faults surface here too.

The gate is the write's own decoder, `Layout/Codec/StoredElementCodec`, so preview and write refuse the same drafts: a scalar `slots`, `dataRequirements`, context map, `style`, or attribution list is a 400 here exactly as it is on write, rather than being emptied and then passing.
