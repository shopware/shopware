# Api

Admin API controllers for the content system. Store API rendering lives in `SalesChannel/`; this directory holds Admin-scoped (`ApiRouteScope`) endpoints: layout preview, resolve-and-diagnose, and the seven layout mutation actions.

## Key Classes

- `ContentPreviewController` - Single `POST /api/_action/content-system/preview/entity` action. Synthesizes a `SalesChannelContext`, resolves the entity context by entity type **without** requiring a layout assignment, decodes the draft layout JSON, validates it, runs `ContentPipeline`, and returns a full-format `ContentPage`. No persistence, no caching.
- `ContentPreviewRequest` - envelope DTO bound via `#[MapRequestPayload]` on the controller action parameter: required `layout` (raw array), `entityType`, `entityId`, `salesChannelId`; optional `languageId`, `currencyId`, `domainId`, `customerId`, `queryParameters`. The `layout` stays raw so `ContentElementFieldSerializer::decodeElement()` remains the single decode path.
- `ContentDiagnoseController` - Single `POST /api/_action/content-system/layout/diagnose` action. Decodes the draft layout from the request — it never reads or writes the stored `content_layout` entity — optionally binds a source's root context (via `SpecificationSourceLocator`), runs `Diagnostics/LayoutDiagnostics`, and returns `{ resolutions, diagnostics }` — per-element property resolutions plus a well-formedness/resolvability report. No persistence, no rendering.
- `ContentDiagnoseRequest` - envelope DTO bound via `#[MapRequestPayload]` on the controller action parameter: optional `layout` (raw array, defaults to an empty tree), optional `entityType`, optional `section`. With neither source field, only intrinsic well-formedness is evaluated.
- `SpecificationSourceLocator` - Request-time source selection shared by the diagnose and mutation routes: `resolveByEntityType()` first-matches the tagged entity sources via `supportsEntityType()`; `resolveBySection()` reads a `ContentSection`-keyed `ServiceLocator`. The route calls `providedRootContext()` on the returned source.
- `LayoutMutationController` - Seven `POST /api/_action/content-system/layout/{insert,remove,move,replace,duplicate}-element`, `.../wrap-elements`, `.../unwrap-element` actions. Each binds its request DTO, builds one `Mutation/` operation, and runs it through `Mutation/MutationPipeline`, returning the re-resolved layout plus a diagnostics report. No persistence. The optional `entityType`/`section` binds a source's root context (via `SpecificationSourceLocator`) for binding-scope resolvability.
- Mutation request DTOs - One per action (`InsertElementRequest`, `RemoveElementRequest`, `MoveElementRequest`, `ReplaceElementRequest`, `DuplicateElementRequest`, `WrapElementsRequest`, `UnwrapElementRequest`), each bound via `#[MapRequestPayload]` on the controller action parameter. Each carries the raw `layout` (decoded through `ContentElementFieldSerializer`) plus the operation's parameters and the optional `entityType`/`section`.
- `LayoutDiagnosticsResultNormalizer` - Zero-dependency normalizer for the diagnostics half of an admin content response (the resolutions map and the diagnostics report). Shared by the diagnose and mutation controllers so both emit the same wire shape.

## Endpoint Reference

The request/response contract, error model, and the introspection endpoints that feed it are documented in `../ADMINISTRATION.md`.
