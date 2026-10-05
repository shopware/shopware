# Preview

Preview shows an editor an unsaved draft rendered against real data, from the token the Admin API mints to the page the storefront renders. The model is preview as a second entry into the one rendering path.

## Preview is a second entry into the one rendering path, never a second storage path

Admin and storefront preview render a decoded draft through one `ContentPreviewPageBuilder::build()` and through the `ContentPipeline::load()` that serves persisted layouts. The mint route runs that full build as its admission gate and stores only the request. A redeemed token therefore renders. A malformed stored envelope is a server fault, and the token is the credential for redeeming the preview.

Why: A validate-only mint can accept a draft that the render later fails on. Redeeming the token then returns a 500 the editor cannot act on, instead of a rejection at mint time.

In code:

- `ContentPreviewPayloadStore::load()` throws `previewPayloadInvalid` for a malformed stored envelope.
- Only the mint route requires `content_layout:read`.
- `ContentSystemPreviewControllerTest` pins that redemption renders through the pipeline and a malformed envelope is a server fault.
- See [preview-url.md](../../Api/docs/preview-url.md).

## The editor's preview shows what the storefront renders

Preview is the storefront's own render. The preview route runs `ContentPipeline::load()` in full mode with the storefront's templates, as serving does. The admin API sends the preview as a URL, not as inlined markup. Preview is the one place where admin and storefront element-type names must agree.

The preview builds its sales-channel context from the ids in the payload through `SalesChannelContextService`, not from the request's domain. The storefront's domain resolution maps active sales channels only, and a preview must render a sales channel before its activation. The preview therefore does not reproduce the domain-derived request state, the session's customer and cart, or the rule ids that follow from them. A customer is previewed by id, without a cart. A listener or plugin that reads the request sees the request of the administration host, and that is not a defect.

Why: An admin-side approximation renders differently from the storefront, so authors fix what only the approximation shows, not what shoppers see. A real storefront request cannot reach an inactive sales channel.

Not chosen: An admin-side approximation of the storefront render, or a real sub-request against the sales channel's domain, which an inactive channel does not have.

Exceptions: On the preview route, the storefront gives each element a `preview` flag. An element with that flag may show an authoring placeholder that the storefront hides. `Sw:Product:Manufacturer` shows an authoring placeholder that the storefront hides.

In code:

- `ContentPreviewController::previewUrl()` returns only a URL.
- `ContentSystemPreviewController::preview()` renders the storefront content page through `preview.html.twig`.
- See [preview-url.md](../../Api/docs/preview-url.md).

## Preview rejects what it cannot render and reports nothing else

Preview renders a draft or rejects it with a 400. Of the layout diagnostics, only intrinsic errors make preview reject a draft. Warnings and resolvability findings never surface in preview. The layout write may still reject a draft that previews, as [drafts-and-gates.md](drafts-and-gates.md#the-module-holds-a-draft-to-well-formedness) states, and the editor's save path must surface that rejection.

Why: Preview renders or rejects, so a warning would block a renderable draft. The diagnose route reports.

In code:

- `DraftLayoutChecker::check()` keeps only `DiagnosticsReport::intrinsicErrors()`.
- `ContentPreviewPageBuilder::build()` throws those errors as `elementTypesInvalid`.
- See [preview-url.md](../../Api/docs/preview-url.md#errors).
