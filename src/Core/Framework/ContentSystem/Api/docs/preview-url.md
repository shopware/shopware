# Preview URL

The preview action that mints a short-lived, openable URL for a draft layout.

`POST /api/_action/content-system/preview/entity/url`

Stores an externally supplied, **unsaved** draft layout behind a short-lived token and returns a URL that renders it against **real** entity data, so a draft can be opened or embedded (for example in an editor iframe) without re-posting the layout. The layout is neither persisted nor required to have an assignment. The target entity only needs to exist. Served by `Api/ContentPreviewController::previewUrl`. Route name: `api.action.content_system.preview.entity.url`. Requires the `content_layout:read` privilege.

Before anything is stored, the draft is admitted through the same `Api/ContentPreviewPageBuilder::build()` that opening the returned URL runs, and the built page is discarded, so the mint and the render cannot drift apart. A draft that gate refuses is a 400 and never becomes a token. Minting pays one full build.

The payload is held by `Api/ContentPreviewPayloadStore` under a 32-character token in the application cache for five minutes (`expiresAfter(300)`), never in the database. The returned URL points at the Storefront render route `GET /content-system/preview/{token}` (`frontend.content-system.preview`), which loads the payload back, builds the page through the same `ContentPreviewPageBuilder`, and serves the full-format `ContentPage` as an embeddable page (its `frame-ancestors` CSP is derived from the request `Referer`). An expired or unknown token renders a `404`.

## Request

The `ContentPreviewRequest` envelope:

| Field | Required | Notes |
|---|---|---|
| `layout` | yes | Raw element-tree array; decoded through the same path as a stored layout (`Layout/Codec/StoredElementCodec::decode()`). |
| `entityType` | yes | One of the `content-system-entity-types.json` values; selected by exact match, never as a URL segment. |
| `entityId` | yes | Id of the entity to hydrate against; the entity must exist. |
| `salesChannelId` | yes | Sales channel whose context is synthesized for rendering. |
| `languageId`, `currencyId`, `domainId`, `customerId` | no | Override the synthesized sales channel context. |
| `queryParameters` | no | Forwarded as request query; `elementId` selects a single element for partial preview. Member names must not be strings PHP casts to integers (`0`, `12`, `-3`) — the stored envelope is a string-keyed map. |

## Response

`200 OK` with a single field; the layout is stored, not rendered here:

```json
{ "url": "https://<host>/content-system/preview/<token>" }
```

## Errors

Envelope and intrinsic-layout failures are rejected with `400 Bad Request` (`ContentSystemException`). Because the mint runs the one build gate, it renders against real entity data too, so a fault raised during hydration keeps its own status instead of collapsing to 400 (see the HTTP column). The store write adds one further failure:

| Condition | HTTP | Factory / source |
|---|---|---|
| Missing/invalid envelope field | 400 | `#[MapRequestPayload]` validation (forced to 400) |
| A `queryParameters` member name PHP casts to an integer | 400 | `ContentPreviewRequest::rejectNonStringQueryParameterNames()`, through the same `#[MapRequestPayload]` validation |
| An `includes` or `excludes` parameter in any of the attribute, query or request bag | 400 | `fieldSelectionNotSupported` — field selection is refused here as it is on the store-api content routes |
| `entityType` matches no specification source | 400 | `unknownEntityType` |
| Layout element missing a non-empty string `id`/`component`; a duplicate element `id`, nesting past the maximum depth, or a non-array nested child; or an element config that is a client defect | 400 | `invalidLayoutStructure` |
| Layout has any intrinsic-scope error `LayoutDiagnostics` reports | 400 | `elementTypesInvalid` (via `DraftLayoutChecker`, which surfaces every intrinsic-scope error from `LayoutDiagnostics`; the message carries the violation, not its code) |
| Data-loader source not registered | 500 | `ContentSystemException::dataLoaderNotRegistered` — thrown while resolving the loader for a source (`DataLoaderProvider`), outside any loader's `load()` |
| Non-degradable hydration fault (`\TypeError`, a database failure, any exception outside `ShopwareHttpException`) | 500 | propagates through `load()` by design |
| Invalid sales channel id | 404 / 412 | `SalesChannelException` (not a `ContentSystemException`) |
| The cache rejects the payload write | 500 | `ContentSystemException::previewPayloadStoreFailed` |

Entity resolution and hydration run at mint time as well as when the URL is opened, so `unknownEntityType` and hydration faults surface here too. A target entity that does not exist, or an unresolvable data requirement inside a loader, is no failure at all: the loader degrades that element to `notFound()` and the preview renders without it.

The gate is the write's own decoder, `Layout/Codec/StoredElementCodec`, so preview and write refuse the same drafts: a scalar `slots`, `dataRequirements`, context map, `style`, or attribution list is a 400 here exactly as it is on write, rather than being emptied and then passing.
