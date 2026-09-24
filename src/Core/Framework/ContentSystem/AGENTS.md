> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Source Code References

- **Pipeline**: `ContentPipeline` (steps 3-5), `RenderingMode` (FULL vs SKELETON) — the ordered contract is in [docs/pipeline-contract.md](docs/pipeline-contract.md)
- **Store API**: `SalesChannel/ContentRoute` — one class, DI-parameterized per format and section
- **Events**: `Event/ContentTreePreparationEvent`, `Event/RenderedTreeFinalizationEvent`
- **Layout Value Objects**: `RenderableLayout` (reference + stored elements), `LayoutReference` (id, name, version), `ResolvedContentLayout` (layout ID + `RenderingSpecification`)
- **Render Inputs**: `RenderingSpecification` (data requirements, placeholders, request, target element, cache tags), `PlaceholderValues` (immutable placeholder map), `SpecificationData` (the entity definition's data requirements bundled with the request's placeholder values, independent of layout assignment), `ContentSection` (enum: HEADER, FOOTER, MAIN)
- **Resolver**: `Adapter/RenderingSpecificationResolver` — three instances (main, header, footer), see DI config
- **Root Source Registry**: `Adapter/RootSourceRegistry` — the single authority over the valid root-source set: `knownRootSources()`, `entityRootSources()`, `resolve()`, `sourceFor()`. The `none` source is `Adapter/NoneSpecificationSource`
- **Entity Specification Sources**: `Content/{Product,Category,LandingPage}/Aggregate/*ContentLayout/*SpecificationSource`; header and footer live in `Storefront/ContentSystem/`
- **Assignment-free resolution**: `RenderingSpecificationResolver::resolveWithoutLayout()` selects by `supportsEntityType()`; `RenderingSpecificationFactory::createWithoutLayout()` builds a specification with no layout id
- **Resolution & Diagnostics**: `Resolution/ElementResolver`, `Resolution/AvailableContextResolver`, `Diagnostics/LayoutDiagnostics`, `Diagnostics/RootContextMapper`, `Diagnostics/ViolationCode` — see [Diagnostics/AGENTS.md](Diagnostics/AGENTS.md)
- **Write gates**: `Validation/ContentLayoutWriteValidator`, `Validation/ContentLayoutAssignmentWriteValidator`, `Validation/LayoutGate` — see [Validation/AGENTS.md](Validation/AGENTS.md)
- **Write boundary**: `Layout/LayoutWriteBoundary` with `Layout/LayoutDefaultSeeder`, reached from `Layout/Field/StoredElementListFieldSerializer::normalize` — see [Layout/README.md](Layout/README.md)
- **Delete protection**: `RestrictDelete` on the five `content_layout` assignment associations — see [Layout/Entity/AGENTS.md](Layout/Entity/AGENTS.md)
- **Draft Check**: `DraftLayoutChecker` (module root) — the preview action's draft check, an intrinsic-subset diagnostics run
- **Admin API**: `Api/` — preview, resolve-and-diagnose, draft and persisted mutation routes; see [Api/AGENTS.md](Api/AGENTS.md)
- **Layout mutation**: `Mutation/MutationPipeline` over the `Mutation/Op` operations, and `Mutation/PersistedLayoutMutator` for the committed counterpart — see [Mutation/AGENTS.md](Mutation/AGENTS.md)
- **Element Type Registry**: `Layout/Type/Registry/ContentSystemElementTypeRegistry` — see [Layout/Type/AGENTS.md](Layout/Type/AGENTS.md)
- **Style Option Registry**: `Layout/Element/Style/Registry/ContentSystemStyleOptionRegistry` — universal per-breakpoint presentation options; see [Layout/Element/Style/AGENTS.md](Layout/Element/Style/AGENTS.md)
- **Binding Specification System**: `Binding/Specification/BindingSpecification` and its loader/registry trio — see [Binding/AGENTS.md](Binding/AGENTS.md)
- **Schema**: `Schema/ContentSystemDataLoaderMapResolver`, `Schema/ContentSystemDataLoaderMap`, `Schema/ContentSystemDataLoaderSchemaGenerator`
- **Compiler passes**: `ContentSystemDataLoaderCompilerPass`, `ContentSystemCompilerPass`, `ContentSystemStyleOptionCompilerPass`, `ContentLayoutAssignableCompilerPass`, `ContentRouteCompilerPass` — see [docs/service-tags-and-types.md](docs/service-tags-and-types.md)

## Constraints

- Specification resolution and layout-entity loading happen in `ContentRoute`, NOT in `ContentPipeline`
- `RenderingSpecificationResolver` iterates sources via a `supports()` bool check, first match wins — NOT a null-return
- Type spec `properties` is the hydrated output schema; the storage schema is the separate `storageSchema` fold derived by `Layout/Type/StoredSchemaResolver`. The property key links type spec → dataRequirements → acceptsContext → the key `Rendering/RenderedElementFactory` writes the resolved value under
- Primitive property satisfaction is strict: a required primitive with no stored value is `UnresolvedRequired`, and the type default is not consulted — serving renders stored `properties` verbatim. See [Diagnostics/AGENTS.md](Diagnostics/AGENTS.md)
- A writer that bypasses the DAL (raw SQL, migration) seeds a required defaulted primitive itself, or the next resolvability re-check rejects the row
- `ContentSystemDataLoaderMap` lookups are subtype-aware (`is_a`): `getSourcesFor($class)` returns every source whose produced type is the class or a subclass
- Introspection endpoints (`InfoController`): `content-system-{element-types,data-loaders,entity-types,root-sources,style-options}.json`
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints

## Navigation

- [docs/pipeline-contract.md](docs/pipeline-contract.md) - what `ContentPipeline::load()` does, in order, and which step judges which forest
- [docs/client-defects.md](docs/client-defects.md) - which error codes mean the client's input was wrong
- [docs/stored-and-rendered.md](docs/stored-and-rendered.md) - the two element models
- [docs/service-tags-and-types.md](docs/service-tags-and-types.md) - DI tags, registries, compiler passes
- [docs/data-flow.md](docs/data-flow.md) - how a value reaches an element
- [docs/role-suffixes.md](docs/role-suffixes.md) - the naming convention
- [docs/extending.md](docs/extending.md) - the six plugin extension points
- [README.md](README.md) - the mental model

## Quick Reference

- Exception class: `ContentSystemException`
- DI config: `content-system.php` contains only framework-owned infrastructure; domain-specific services are registered in their owning module's DI
