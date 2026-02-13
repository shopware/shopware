@README.md

## Source Code References

- **Specification Sources**: `Adapter/ProductSpecificationSource`, `Adapter/CategorySpecificationSource`, `Adapter/LandingPageSpecificationSource`
- **Header/Footer Sources**: `Adapter/HeaderSpecificationSource`, `Adapter/FooterSpecificationSource`
- **Resolver**: `Adapter/RenderingSpecificationResolver` (3 instances: main, header, footer — see DI config)
- **Events**: `Event/PreContentHydrationEvent`, `Event/PostHydrationEvent`
- **Pipeline**: `ContentPipeline` (steps 2-5), `RenderingMode` (FULL vs SKELETON)
- **Store API**: `SalesChannel/ContentRoute` (single class, DI-parameterized per format + section)

## Constraints

- `RenderingSpecificationResolver`: iterates sources via `supports()` bool check, first match wins — NOT null-return
- `ContentPipeline::load()`: layout load → PreHydration events → hydration (FULL mode only) → PostHydration events
- Specification resolution happens in `ContentRoute`, NOT in `ContentPipeline`
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints

## Quick Reference

- Exception class: `ContentSystemException`
- Package: `#[Package('discovery')]`
- DAL: Use Criteria API + EntityDefinition, NOT Doctrine ORM
- DI config: `src/Core/Content/DependencyInjection/content_system.xml`
