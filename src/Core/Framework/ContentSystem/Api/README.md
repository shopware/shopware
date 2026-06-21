# Api

Admin API controllers for the content system. Store API rendering lives in `SalesChannel/`; this directory holds Admin-scoped (`ApiRouteScope`) endpoints.

## Key Classes

- `ContentPreviewController` - Single `POST /api/_action/content-system/preview/entity` action. Synthesizes a `SalesChannelContext`, resolves the entity context by entity type **without** requiring a layout assignment, decodes the draft layout JSON, validates it, runs `ContentPipeline`, and returns a full-format `ContentPage`. No persistence, no caching.
- `ContentPreviewRequest` - envelope DTO bound via `#[MapRequestPayload]` on the controller action parameter: required `layout` (raw array), `entityType`, `entityId`, `salesChannelId`; optional `languageId`, `currencyId`, `domainId`, `customerId`, `queryParameters`. The `layout` stays raw so `ContentElementFieldSerializer::decodeElement()` remains the single decode path.
- `ContentDiagnoseController` - Single `POST /api/_action/content-system/layout/diagnose` action. Decodes the draft layout from the request — it never reads or writes the stored `content_layout` entity — optionally binds a source's root context (via `SpecificationSourceLocator`), runs `Diagnostics/LayoutDiagnostics`, and returns `{ resolutions, diagnostics }` — per-element property resolutions plus a well-formedness/resolvability report. No persistence, no rendering.
- `ContentDiagnoseRequest` - envelope DTO bound via `#[MapRequestPayload]` on the controller action parameter: optional `layout` (raw array, defaults to an empty tree), optional `entityType`, optional `section`. With neither source field, only intrinsic well-formedness is evaluated.
- `SpecificationSourceLocator` - Request-time source selection for the diagnose route: `resolveByEntityType()` first-matches the tagged entity sources via `supportsEntityType()`; `resolveBySection()` reads a `ContentSection`-keyed `ServiceLocator`. The route calls `providedRootContext()` on the returned source.

## Endpoint Reference

The request/response contract, error model, and the introspection endpoints that feed it are documented in `../ADMINISTRATION.md`.
