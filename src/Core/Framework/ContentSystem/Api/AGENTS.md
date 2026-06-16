@README.md

## Source Code References

- `ContentPreviewController` - Route `POST /api/_action/content-system/preview/entity` (name `api.action.content_system.preview.entity`), scope `ApiRouteScope`
- `ContentPreviewRequest` - `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` envelope DTO
- Resolution entry: `Adapter/RenderingSpecificationResolver::resolveWithoutLayout(entityType, entityId, request, context)` — assignment-free, selects source by `supportsEntityType()`
- Validation: `ContentLayoutValidator` (module root) + `Layout/Element/Visitor/ComponentRegistrationVisitor`
- Introspection endpoints the admin UI pairs with this action: `Framework/Api/Controller/InfoController` (`content-system-element-types`, `content-system-data-loader-types`, `content-system-entity-types`)

## Constraints

- All failures are 400 (`ContentSystemException`): `unknownEntityType`, `invalidLayoutStructure`, `elementTypesInvalid`, plus hydration and sales-channel-context exceptions
- `layout` is decoded via `ContentElementFieldSerializer::decodeElement()` — do NOT re-model `ContentElement` in the DTO
- No persistence, no caching — empty `RenderingCacheContext`, `RenderingMode::FULL`
- OpenAPI: add an `AdminApi/paths/` entry when changing the route surface (see root AGENTS.md)
- Full request/response contract: `../ADMINISTRATION.md`
