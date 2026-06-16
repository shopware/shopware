# Api

Admin API controllers for the content system. Store API rendering lives in `SalesChannel/`; this directory holds Admin-scoped (`ApiRouteScope`) endpoints.

## Key Classes

- `ContentPreviewController` - Single `POST /api/_action/content-system/preview/entity` action. Synthesizes a `SalesChannelContext`, resolves the entity context by entity type **without** requiring a layout assignment, decodes the draft layout JSON, validates it, runs `ContentPipeline`, and returns a full-format `ContentPage`. No persistence, no caching.
- `ContentPreviewRequest` - `#[MapRequestPayload]` envelope DTO: required `layout` (raw array), `entityType`, `entityId`, `salesChannelId`; optional `languageId`, `currencyId`, `domainId`, `customerId`, `queryParameters`. The `layout` stays raw so `ContentElementFieldSerializer::decodeElement()` remains the single decode path.

## Endpoint Reference

The request/response contract, error model, and the introspection endpoints that feed it are documented in `../ADMINISTRATION.md`.
