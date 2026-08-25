# Service Tags and Types

The DI tags a plugin registers its content system services under, and the classes an extension developer encounters while writing them.

## Service Tag Reference

| Tag                                   | Index Method           | Attributes                                     |
|---------------------------------------|------------------------|------------------------------------------------|
| `content_system.entity_specification_source` | N/A             | `priority` (optional, default 0)               |
| `content_system.specification_source` | `section` attribute    | `section` (required, e.g. `header` / `footer`) |
| `content_system.data_loader`          | `getRequirementType()` | None                                           |
| `content_system.config_serializer`    | `getSource()`          | None                                           |
| `content_system.section_resolver`     | `section` attribute    | `section` (required, e.g. `main` / `header` / `footer`) |

Framework-owned DI configuration: `src/Core/Framework/DependencyInjection/content-system.php`. Domain sources register in their owning module's DI instead: `content_system.entity_specification_source` is tagged in `src/Core/Content/DependencyInjection/product.php`, `category.php` and `landing_page.php`, and `content_system.specification_source` is tagged in `src/Storefront/DependencyInjection/content-system.php` for the header and footer sections.

## Type Reference

Key types extension developers encounter when working with the ContentSystem:

### Base Classes (extend these)

| Class                                       | Purpose                     |
|---------------------------------------------|-----------------------------|
| `AbstractSpecificationSource`               | Custom specification source |
| `AbstractContentDataLoader`                 | Custom data loader          |
| `AbstractContentDataLoaderConfig`           | Loader configuration DTO    |
| `AbstractContentDataLoaderConfigSerializer` | Config encode/decode        |

### Result / Value Objects

| Class                     | Purpose                                                                                |
|---------------------------|----------------------------------------------------------------------------------------|
| `ContentDataLoaderResult` | Loader return value with cache info                                                    |
| `LoaderTypeCapability`    | One type a loader can produce: `producedType`, `configTemplate`, `genericParameters`; returned by `producibleTypes()` (construct directly when overriding it) |
| `SpecificationData`       | Return type of `resolveSpecificationData()` (bundles data requirements + placeholders) |
| `PlaceholderValues`       | Immutable placeholder map, created via `PlaceholderValues::from(array $values)`        |
| `RenderingSpecification`  | Data requirements, placeholders, request, target element, cache tags                   |
| `ResolvedContentLayout`   | Resolver output: layout ID plus the `RenderingSpecification`                           |
| `LayoutReference`         | Immutable layout identity: `id`, `name`, `version`                                     |
| `RenderableLayout`        | Loaded layout handed to the pipeline: a `LayoutReference` plus its element list        |

### Enums

| Enum             | Values                     | Purpose                         |
|------------------|----------------------------|---------------------------------|
| `RenderingMode`  | `FULL`, `SKELETON`         | Controls whether data and context are resolved |
| `ContentSection` | `MAIN`, `HEADER`, `FOOTER` | Identifies content section      |

### Event Classes

| Class                      | Purpose                     |
|----------------------------|-----------------------------|
| `ContentTreePreparationEvent` | Dispatched over the stored tree before every preparation step |
| `RenderedTreeFinalizationEvent` | Dispatched after the render step and the finishing steps, over the rendered forest |

### Layout / Response

| Class                    | Purpose                                         |
|--------------------------|-------------------------------------------------|
| `StoredElement`          | Stored tree node: properties, slots, data requirements, context wiring |
| `RenderedElement`        | Rendered tree node: `id`, `component`, flat properties, slots, style |
| `RenderingCacheContext`  | Cache tag collection + disable flag             |
| `ContentSystemException` | Exception class with error codes                |

All classes above are in the `Shopware\Core\Framework\ContentSystem` namespace (or subnamespaces).
