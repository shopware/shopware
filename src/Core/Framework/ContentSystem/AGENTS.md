@README.md

## Source Code References

- **Entity Specification Sources**: `Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource`, `Content/Category/Aggregate/CategoryContentLayout/CategorySpecificationSource`, `Content/LandingPage/Aggregate/LandingPageContentLayout/LandingPageSpecificationSource`
- **Header/Footer Sources**: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`
- **Resolver**: `Adapter/RenderingSpecificationResolver` (3 instances: main, header, footer — see DI config)
- **Events**: `Event/PreContentHydrationEvent`, `Event/PostHydrationEvent`
- **Pipeline**: `ContentPipeline` (steps 3-5), `RenderingMode` (FULL vs SKELETON)
- **Layout Value Objects**: `RenderableLayout` (reference + elements), `LayoutReference` (id, name, version), `ResolvedContentLayout` (layout ID + `RenderingSpecification`)
- **Store API**: `SalesChannel/ContentRoute` (single class, DI-parameterized per format + section)
- **Admin Preview API**: `Api/ContentPreviewController` — `POST /api/_action/content-system/preview/entity`; renders an unsaved draft layout against real entity data (assignment-free). See `ADMINISTRATION.md`
- **Draft Validation**: `ContentLayoutValidator` (module root) + `Layout/Element/Visitor/ComponentRegistrationVisitor` — collects a violation per unregistered component
- **Assignment-free resolution**: `Adapter/RenderingSpecificationResolver::resolveWithoutLayout()` selects a source via `supportsEntityType()`; `RenderingSpecificationFactory::createWithoutLayout()` assembles a `RenderingSpecification` with no layout id
- **Schema**: `Schema/ContentSystemDataLoaderTypeResolver`, `Schema/ContentSystemDataLoaderTypeMap`, `Schema/ContentSystemDataLoaderTypeSchemaGenerator`
- **Entity Type Schema**: `Schema/ContentLayoutAssignableEntitySchemaGenerator`
- **Compiler Pass**: `DependencyInjection/CompilerPass/ContentSystemDataLoaderTypeCompilerPass` — validates loader `@extends` annotations at build time (the runtime resolver assembles the type map)
- **Element Type Registry**: `Layout/Type/Registry/ContentSystemElementTypeRegistry`
- **Element Type API**: `GET /api/_info/content-system-element-types.json` (registered in `InfoController`)
- **Type-Loader Bridge**: `Schema/ContentSystemDataLoaderTypeMap`, `Schema/ContentSystemDataLoaderTypeResolver`
- **Compiler Pass**: `DependencyInjection/CompilerPass/ContentLayoutAssignableCompilerPass` — collects assignable entity types at build time

## Constraints

- `RenderingSpecificationResolver`: iterates sources via `supports()` bool check, first match wins — NOT null-return
- `ContentPipeline::load()`: receives a loaded `RenderableLayout` → PreHydration events → hydration (FULL mode only) → PostHydration events
- Specification resolution and layout-entity loading happen in `ContentRoute`, NOT in `ContentPipeline`
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints
- Data loader type introspection: `ContentSystemDataLoaderTypeResolver` assembles the source→capability map at runtime from each loader's `producibleTypes()` (memoized per kernel runtime); `ContentSystemDataLoaderTypeCompilerPass` only validates at build time (dry-runs `AbstractContentDataLoader::extendsDescriptor()`, so loaders MUST have `@extends AbstractContentDataLoader<T>` PHPDoc); wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()`/`resolveProducedType()` to enumerate the live registry (so plugin/app/custom entities appear on the next kernel boot without a container rebuild)
- Schema API endpoint: `GET /api/_info/content-system-data-loader-types.json` (registered in `InfoController`)
- `ContentSystemDataLoaderTypeMap` lookups are subtype-aware (`is_a`): `getSourcesFor($class)` returns every source whose `producedType` is the class or a subclass; `capabilityFor($source, $class)` returns that source's first matching `LoaderTypeCapability`, or `null` when the source cannot produce the class
- Entity type introspection: `ContentLayoutAssignableCompilerPass` introspects `content_system.context_factory` tagged services for `AbstractContentLayoutAssignableDefinition` arguments — entity types baked into schema generator at build time
- Schema API endpoint: `GET /api/_info/content-system-entity-types.json` (registered in `InfoController`)
- Type spec `properties` = hydrated output schema, NOT storage schema; property key links type spec → data_requirements → accepts_context → setProperty()

## Quick Reference

- Exception class: `ContentSystemException`
- Package: `#[Package('framework')]`
- DAL: Use Criteria API + EntityDefinition, NOT Doctrine ORM
- DI config: `content-system.xml` contains only framework-owned infrastructure; domain-specific services are registered in their owning module's DI
