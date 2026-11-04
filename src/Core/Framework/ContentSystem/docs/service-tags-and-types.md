# Service Tags and Types

## Service Tag Reference

| Tag                                   | Index Method           | Attributes                                     |
|---------------------------------------|------------------------|------------------------------------------------|
| `content_system.entity_specification_source` | N/A             | `priority` (optional, default 0)               |
| `content_system.specification_source` | `section` attribute    | `section` (required, e.g. `header` / `footer`) |
| `content_system.data_loader`          | `getRequirementType()` | None                                           |
| `content_system.config_serializer`    | `getSource()`          | None                                           |
| `content_system.mapping_candidate_provider` | `registeredCandidateKeys()`, `supports()` / `provide()` | Stable duplicate keys fail compilation; runtime collisions exclude every owner |
| `content_system.section_resolver`     | `section` attribute    | `section` (required, e.g. `main` / `header` / `footer`) |

Framework-owned DI configuration: `src/Core/Framework/DependencyInjection/content-system.php`. Domain sources register in their owning module's DI instead: `content_system.entity_specification_source` is tagged in `src/Core/Content/DependencyInjection/product.php`, `category.php` and `landing_page.php`, and `content_system.specification_source` is tagged in `src/Storefront/DependencyInjection/content-system.php` for the header and footer sections.

## Type Reference

### Base Classes (extend these)

| Class                                       | Purpose                     |
|---------------------------------------------|-----------------------------|
| `AbstractSpecificationSource`               | Custom specification source |
| `AbstractContentDataLoader`                 | Custom data loader          |
| `AbstractContentDataLoaderConfig`           | Loader configuration DTO    |
| `AbstractContentDataLoaderConfigSerializer` | Config encode/decode        |

| `AbstractMappingCandidateProvider` | Curated sources and runtime provider reads |
| `AbstractContentSystemMappingCandidateRegistry` | Supported guarded decoration of approved sources |

### Result / Value Objects

| Class                     | Purpose                                                                                |
|---------------------------|----------------------------------------------------------------------------------------|
| `ContentDataLoaderResult` | Loader return value with cache info                                                    |
| `LoaderTypeCapability`    | One type a loader can produce, returned by `producibleTypes()` (construct directly when overriding it) |
| `SpecificationData`       | Return type of `resolveSpecificationData()`                                            |
| `MappingSourceReference` | Typed source identity and configuration |
| `MappingSourceResolution` | Resolved value (including null) or missing data |
| `MappingSourceResolutionContext` | Element, root/loader values, and caller-owned cache context |
| `PlaceholderValues`       | Created via `PlaceholderValues::from()`                                                |

The layout value objects a source assembles and the pipeline consumes (`RenderingSpecification`, `ResolvedContentLayout`, `LayoutReference`, `RenderableLayout`) are described in [README.md](../README.md#key-classes).

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
| `StoredElement`          | Stored tree node                                                       |
| `RenderedElement`        | Rendered tree node                                                   |
| `RenderingCacheContext`  | Cache tag collection + disable flag             |
| `ContentSystemException` | Exception class with error codes                |

All classes above are in the `Shopware\Core\Framework\ContentSystem` namespace (or subnamespaces).

Mapping registration, candidate identity, runtime dispatch, and supported overrides: [Mapping/README.md](../Mapping/README.md), [provider guide](../Mapping/docs/source-providers.md), [plugin sources](../Mapping/docs/plugin-sources.md).
