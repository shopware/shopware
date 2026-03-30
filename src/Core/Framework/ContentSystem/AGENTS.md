@README.md

## Source Code References

- **Entity Specification Sources**: `Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource`, `Content/Category/Aggregate/CategoryContentLayout/CategorySpecificationSource`, `Content/LandingPage/Aggregate/LandingPageContentLayout/LandingPageSpecificationSource`
- **Header/Footer Sources**: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`
- **Resolver**: `Adapter/RenderingSpecificationResolver` (3 instances: main, header, footer — see DI config)
- **Events**: `Event/PreContentHydrationEvent`, `Event/PostHydrationEvent`
- **Pipeline**: `ContentPipeline` (steps 2-5), `RenderingMode` (FULL vs SKELETON)
- **Store API**: `SalesChannel/ContentRoute` (single class, DI-parameterized per format + section)
- **Schema**: `Schema/ContentSystemDataLoaderTypeResolver`, `Schema/ContentSystemDataLoaderTypeMap`, `Schema/ContentSystemDataLoaderTypeSchemaGenerator`
- **Compiler Pass**: `DependencyInjection/CompilerPass/ContentSystemDataLoaderTypeCompilerPass` — collects loader type info at build time

## Constraints

- `RenderingSpecificationResolver`: iterates sources via `supports()` bool check, first match wins — NOT null-return
- `ContentPipeline::load()`: layout load → PreHydration events → hydration (FULL mode only) → PostHydration events
- Specification resolution happens in `ContentRoute`, NOT in `ContentPipeline`
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints
- Data loader type introspection: `ContentSystemDataLoaderTypeCompilerPass` calls `getProvidedData()` on all tagged loaders at build time — loaders MUST have `@extends AbstractContentDataLoader<T>` PHPDoc; wildcard loaders override `overrideProvidedTypes()` for runtime expansion
- Schema API endpoint: `GET /api/_info/content-system-data-loader-types.json` (registered in `InfoController`)

## Quick Reference

- Exception class: `ContentSystemException`
- Package: `#[Package('framework')]`
- DAL: Use Criteria API + EntityDefinition, NOT Doctrine ORM
- DI config: `content-system.xml` contains only framework-owned infrastructure; domain-specific services are registered in their owning module's DI
