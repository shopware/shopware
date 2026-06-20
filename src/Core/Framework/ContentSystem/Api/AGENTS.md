@README.md

## Source Code References

- `ContentPreviewController` - Route `POST /api/_action/content-system/preview/entity` (name `api.action.content_system.preview.entity`), scope `ApiRouteScope`
- `ContentDiagnoseController` - Route `POST /api/_action/content-system/layout/diagnose` (name `api.action.content_system.layout.diagnose`), scope `ApiRouteScope`; operates only on the draft layout tree from the request — it never reads or writes the stored `content_layout` entity — and returns `{ resolutions, diagnostics }` without persisting. Optional `entityType`/`section` enable the binding-resolvability branch via `SpecificationSourceLocator`
- `ContentPreviewRequest` / `ContentDiagnoseRequest` - envelope DTOs; each controller action binds its DTO via `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` on the **action parameter** (the attribute is on the parameter, not on the DTO class)
- Preview resolution entry: `Adapter/RenderingSpecificationResolver::resolveWithoutLayout(entityType, entityId, request, context)` — assignment-free, selects source by `supportsEntityType()`
- Diagnose source selection: `SpecificationSourceLocator::resolveByEntityType(entityType)` (first match over the tagged entity sources via `supportsEntityType()`) or `resolveBySection(ContentSection)` (a `ContentSection`-keyed `ServiceLocator` populated by the `content_system.specification_source` tag, `index-by="section"`); the controller then calls `->providedRootContext($context)` on the selected source to seed binding-scope resolvability. It does NOT use `resolveWithoutLayout()`
- Check: `DraftLayoutChecker` (module root) — preview-action draft check (intrinsic-subset diagnostics)
- Introspection endpoints the admin UI pairs with this action: `Framework/Api/Controller/InfoController` (`content-system-element-types`, `content-system-data-loader-types`, `content-system-entity-types`)

## Constraints

- All failures are 400 (`ContentSystemException`): `unknownEntityType`, `noSourceForSection` (diagnose `section` branch), `invalidLayoutStructure`, `elementTypesInvalid` (preview), plus hydration and sales-channel-context exceptions (preview)
- Diagnose reports per-element client config defects as `invalid_config` violations in the response body (HTTP 200), not as errors; only non-client-defect faults propagate — see `ContentSystemException::isClientDefect()`
- `layout` is decoded via `ContentElementFieldSerializer::decodeElement()` — do NOT re-model `ContentElement` in the DTO
- Preview: no persistence, no caching — empty `RenderingCacheContext`, `RenderingMode::FULL`. Diagnose: no persistence, no rendering — it resolves and reports against the draft tree only
- OpenAPI: the introspection GET routes carry `AdminApi/paths/` entries; the preview and diagnose POST `_action` routes do not — their request/response contract lives in `../ADMINISTRATION.md`
- Full request/response contract: `../ADMINISTRATION.md`
