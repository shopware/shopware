> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Navigation

- Pipeline step order and the preparation passes — [docs/pipeline-steps.md](docs/pipeline-steps.md)
- Write-time gates, default seeding, delete protection — [docs/layout-write-gates.md](docs/layout-write-gates.md)
- Structural layout edits — [docs/layout-mutation.md](docs/layout-mutation.md)
- Binding specifications at the module root — [docs/binding-specifications.md](docs/binding-specifications.md)
- Universal style options and their storage — [docs/element-styles.md](docs/element-styles.md)
- Registries, compiler passes, `_info` endpoints — [docs/introspection-endpoints.md](docs/introspection-endpoints.md)
- Which error codes are a client defect — [docs/client-defect-codes.md](docs/client-defect-codes.md)
- The six extension mechanisms — [docs/extending.md](docs/extending.md)
- DI tags, base classes, value objects, enums, events — [docs/service-tags-and-types.md](docs/service-tags-and-types.md)
- Rendering pipeline data-flow diagram — [docs/data-flow.md](docs/data-flow.md)
- A worked layout combining entity rendering, data loading and context distribution — [docs/product-detail-page.md](docs/product-detail-page.md)
- How classes in this module are named — [NAMING.md](NAMING.md), routing on to [docs/stored-and-rendered.md](docs/stored-and-rendered.md) (which of the two element models a name is about) and [docs/role-suffixes.md](docs/role-suffixes.md) (what each role suffix promises)

## Source Code References

- **Entity Specification Sources**: `Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource`, `Content/Category/Aggregate/CategoryContentLayout/CategorySpecificationSource`, `Content/LandingPage/Aggregate/LandingPageContentLayout/LandingPageSpecificationSource`
- **Header/Footer Sources**: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`
- **Resolver**: `Adapter/RenderingSpecificationResolver` (3 instances: main, header, footer — see DI config)
- **Events**: `Event/ContentTreePreparationEvent`, `Event/RenderedTreeFinalizationEvent`
- **Pipeline**: `ContentPipeline` (steps 3-5), `RenderingMode` (FULL vs SKELETON)
- **Layout Value Objects**: `RenderableLayout` (reference + stored elements), `LayoutReference` (id, name, version), `ResolvedContentLayout` (layout ID + `RenderingSpecification`)
- **Store API**: `SalesChannel/ContentRoute` (single class, DI-parameterized per format + section)
- **Admin Preview API**: `Api/ContentPreviewController` — one route minting a short-lived token for an unsaved draft layout and returning a Storefront preview URL that renders it against real entity data, assignment-free. Route, mint-time admission, the absent direct render route and the payload store's contract are in [Api/AGENTS.md](Api/AGENTS.md); the wire contract in [Api/docs/preview-url.md](Api/docs/preview-url.md)
- **Resolution & Diagnostics**: `Resolution/ElementResolver` + `Resolution/AvailableContextResolver` (kernel), `Diagnostics/LayoutDiagnostics` (`analyze(tree, rootContext)` → `LayoutAnalysis`: per-element `PropertyResolution`s + a `DiagnosticsReport`), `Diagnostics/RootContextMapper` (bound source → root-ambient context). `ViolationCode` resolves a violation's scope (intrinsic/binding) + severity
- **Write-time gates**: the resolvability gate, the write-boundary default seeder and the delete protection — [docs/layout-write-gates.md](docs/layout-write-gates.md)
- **Draft Check**: `DraftLayoutChecker` (module root) — the preview action's draft-layout check; runs the same `LayoutDiagnostics` intrinsic subset as the persistence gate
- **Resolve-and-diagnose route**: `Api/ContentDiagnoseController` (`POST /api/_action/content-system/layout/diagnose`); operates only on a draft layout tree from the request, never the persisted `content_layout` entity; an optional `rootSource` in the request resolves its root-ambient context via `Adapter/RootSourceRegistry::resolveGated()` (empty/absent → intrinsic well-formedness only)
- **Layout mutation**: `Mutation/MutationPipeline`, its `Mutation/Op` operations and `Mutation/PersistedLayoutMutator` — [docs/layout-mutation.md](docs/layout-mutation.md)
- **Assignment-free resolution**: `Adapter/RenderingSpecificationResolver::resolveWithoutLayout()` selects a source via `supportsEntityType()`; `RenderingSpecificationFactory::createWithoutLayout()` assembles a `RenderingSpecification` with no layout id
- **Introspection**: the element-type, style-option and root-source registries, the two compiler passes and the `/api/_info/` endpoints — [docs/introspection-endpoints.md](docs/introspection-endpoints.md)
- **Element style**: `Layout/Element/Style/Registry/ContentSystemStyleOptionRegistry` and where an element's `style` is stored, encoded and served — [docs/element-styles.md](docs/element-styles.md)
- **Binding Specification System**: `Binding/Specification/BindingSpecification`, `Binding/BindingApplicator`, `Binding/AttributionReconciler` — [docs/binding-specifications.md](docs/binding-specifications.md)

## Constraints

- `RenderingSpecificationResolver`: iterates sources via `supports()` bool check, first match wins — NOT null-return
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints
- Constraints owned by a reference document: pipeline step order and language reduction — [docs/pipeline-steps.md](docs/pipeline-steps.md); introspection assembly and the `storageSchema` fold — [docs/introspection-endpoints.md](docs/introspection-endpoints.md); primitive property satisfaction and what each write-time gate admits — [docs/layout-write-gates.md](docs/layout-write-gates.md); which error codes count as a client defect — [docs/client-defect-codes.md](docs/client-defect-codes.md)

## Quick Reference

- Exception class: `ContentSystemException`
- Package: `#[Package('framework')]`
- DAL: Use Criteria API + EntityDefinition, NOT Doctrine ORM
- DI config: `content-system.php` contains only framework-owned infrastructure; domain-specific services are registered in their owning module's DI
