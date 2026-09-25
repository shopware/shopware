# Behind a Preview Token

The orchestration both preview paths share, and what the token addresses.

## The Shared Page Builder

`ContentPreviewPageBuilder` is the orchestration both preview paths run — the admin `ContentPreviewController` here and the Storefront `frontend.content-system.preview` render route on redemption. `build(ContentPreviewRequest, Context)` synthesizes the sales-channel context, resolves the assignment-free `RenderingSpecification`, decodes and checks the draft layout, runs `ContentPipeline` in `RenderingMode::FULL` without value-index collection, and returns `['result' => RenderResult, 'salesChannelContext' => SalesChannelContext]`.

## The Payload Store

`ContentPreviewPayloadStore` owns both directions of the token-addressed envelope: `store(ContentPreviewRequest): string` serializes and caches for five minutes, `load(string $token): ?ContentPreviewRequest` reads it back.

`load()` returns `null` only for a token with no cache entry — that is the Storefront route's 404. A cache hit that is not exactly what `ContentPreviewRequest` declares — its field set, read off the constructor by reflection, plus its constraint attributes, validated against the rebuilt DTO — is `previewPayloadInvalid` (`CONTENT_SYSTEM__PREVIEW_PAYLOAD_INVALID`, 500) rather than a client error: the store writes only validated envelopes and the mint request rejects a non-string `queryParameters` key, so a malformed hit is server-side state.
