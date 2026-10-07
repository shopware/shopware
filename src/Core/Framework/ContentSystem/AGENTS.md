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
- Data-flow diagram: [docs/data-flow.md](docs/data-flow.md)
- A worked layout (entity rendering, data loading, context distribution): [docs/product-detail-page.md](docs/product-detail-page.md)
- Class naming: [NAMING.md](NAMING.md), then [docs/stored-and-rendered.md](docs/stored-and-rendered.md) (which element model a name is about) and [docs/role-suffixes.md](docs/role-suffixes.md) (what a role suffix promises)

## Source Code References

- **Pipeline and Store API**: `ContentPipeline`, `RenderingMode` (FULL vs SKELETON); ordered steps: [docs/pipeline-steps.md](docs/pipeline-steps.md). `SalesChannel/ContentRoute` — one class, DI-parameterized per format and section
- **Specification Sources**: entity: `Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource`, `Content/Category/Aggregate/CategoryContentLayout/CategorySpecificationSource`, `Content/LandingPage/Aggregate/LandingPageContentLayout/LandingPageSpecificationSource`; header/footer: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`
- **Resolver**: `Adapter/RenderingSpecificationResolver` (3 instances: main, header, footer — see DI config); assignment-free `resolveWithoutLayout()` selects via `supportsEntityType()`; `RenderingSpecificationFactory::createWithoutLayout()` builds a `RenderingSpecification` with no layout id
- **Root Source Registry**: `Adapter/RootSourceRegistry` (`knownRootSources()`, `entityRootSources()`, `resolve()`, `sourceFor()`), `Adapter/NoneSpecificationSource`
- **Events and value objects**: `Event/ContentTreePreparationEvent`, `Event/RenderedTreeFinalizationEvent`; `RenderableLayout` (reference + stored elements), `LayoutReference` (id, name, version), `ResolvedContentLayout` (layout ID + `RenderingSpecification`)
- **Admin Preview API**: `Api/ContentPreviewController` — one route that mints a short-lived token for an unsaved draft and returns a Storefront preview URL rendering it against real entity data, assignment-free. Route, mint-time admission, the absent direct render route, payload store: [Api/AGENTS.md](Api/AGENTS.md); wire contract: [Api/docs/preview-url.md](Api/docs/preview-url.md). Its draft check, `DraftLayoutChecker` (module root), runs the persistence gate's `LayoutDiagnostics` intrinsic subset
- **Resolution & Diagnostics**: `Resolution/ElementResolver` + `Resolution/AvailableContextResolver` (kernel), `Diagnostics/LayoutDiagnostics` (`analyze(tree, rootContext)` → `LayoutAnalysis`: per-element `PropertyResolution`s + a `DiagnosticsReport`), `Diagnostics/RootContextMapper` (bound source → root-ambient context). `ViolationCode` resolves a violation's scope (intrinsic/binding) + severity
- **Write-time gates**: the resolvability gate, the write-boundary default seeder, the delete protection — [docs/layout-write-gates.md](docs/layout-write-gates.md); the default-layout gate `Validation/ContentLayoutDefaultValidator` guards the system-config default layout of product and category and refuses deleting a default — [Validation/AGENTS.md](Validation/AGENTS.md)
- **Resolve-and-diagnose route**: `Api/ContentDiagnoseController` (`POST /api/_action/content-system/layout/diagnose`): draft tree only, never the persisted `content_layout`; an optional `rootSource` resolves root-ambient context via `Adapter/RootSourceRegistry::resolveGated()` (absent or empty → intrinsic well-formedness only)
- **Layout mutation**: `Mutation/MutationPipeline`, its `Mutation/Op` operations and `Mutation/PersistedLayoutMutator` — [docs/layout-mutation.md](docs/layout-mutation.md)
- **Introspection and compiler passes**: the element-type, style-option and root-source registries and the `/api/_info/` endpoints — [docs/introspection-endpoints.md](docs/introspection-endpoints.md); passes: `ContentSystemDataLoaderCompilerPass`, `ContentSystemCompilerPass`, `ContentSystemStyleOptionCompilerPass`, `ContentLayoutAssignableCompilerPass`, `ContentRouteCompilerPass`
- **Element style**: `Layout/Element/Style/Registry/ContentSystemStyleOptionRegistry` and where an element's `style` is stored, encoded and served — [docs/element-styles.md](docs/element-styles.md)
- **Binding Specification System**: `Binding/Specification/BindingSpecification`, `Binding/BindingApplicator`, `Binding/AttributionReconciler` — [docs/binding-specifications.md](docs/binding-specifications.md)

## Constraints

- `RenderingSpecificationResolver`: iterates sources via `supports()` bool check, first match wins — NOT null-return
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints
- Constraints owned elsewhere: pipeline step order and language reduction — [docs/pipeline-steps.md](docs/pipeline-steps.md); introspection assembly and the `storageSchema` fold — [docs/introspection-endpoints.md](docs/introspection-endpoints.md); primitive property satisfaction, the translatable anchor rule and what each write-time gate admits — [docs/layout-write-gates.md](docs/layout-write-gates.md); client-defect error codes — [docs/client-defect-codes.md](docs/client-defect-codes.md)

## Quick Reference

- Exception class: `ContentSystemException`
- DI config: `content-system.php` contains only framework-owned infrastructure; domain services register in their owning module's DI
