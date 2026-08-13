# Extending the ContentSystem

Plugins extend the ContentSystem through six mechanisms.

## Table of Contents

1. [Extension Model](#extension-model)
2. [Service Tags](#service-tag-reference)
3. [Type Reference](#type-reference)

## Extension Model

| Extension Point           | Purpose                                                                |
|---------------------------|------------------------------------------------------------------------|
| **Element Types**         | New content components with declared properties and slots — authored per [Layout/Type/docs/custom-types.md](Layout/Type/docs/custom-types.md), not covered here |
| **Style Options**         | New universal per-breakpoint presentation attributes for every element — authored per [Layout/Element/Style/docs/custom-options.md](Layout/Element/Style/docs/custom-options.md), not covered here |
| **Binding Specifications** | Pre-validated data wirings for an element type, applied in one action — authored per [Binding/README.md](Binding/README.md), not covered here |
| **Specification Sources** | New URL patterns, entity types — authored per [Adapter/docs/custom-sources.md](Adapter/docs/custom-sources.md), not covered here |
| **Data Loaders**          | External APIs, calculations, aggregated data (with cache control) — authored per [Hydration/DataLoader/docs/custom-loaders.md](Hydration/DataLoader/docs/custom-loaders.md), not covered here |
| **Event Listeners**       | Modify layout structure, enrich data, transform properties, cache tags — authored per [Event/Listener/docs/custom-listeners.md](Event/Listener/docs/custom-listeners.md), not covered here |

---

## Service Tag Reference

| Tag                                   | Index Method           | Attributes                                     |
|---------------------------------------|------------------------|------------------------------------------------|
| `content_system.entity_specification_source` | N/A             | `priority` (optional, default 0)               |
| `content_system.specification_source` | `section` attribute    | `section` (required, e.g. `header` / `footer`) |
| `content_system.data_loader`          | `getRequirementType()` | None                                           |
| `content_system.config_serializer`    | `getSource()`          | None                                           |
| `content_system.section_resolver`     | `section` attribute    | `section` (required, e.g. `main` / `header` / `footer`) |

Full DI configuration: `src/Core/Framework/DependencyInjection/content-system.php`

---

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
| `RenderingMode`  | `FULL`, `SKELETON`         | Controls whether hydration runs |
| `ContentSection` | `MAIN`, `HEADER`, `FOOTER` | Identifies content section      |

### Event Classes

| Class                      | Purpose                     |
|----------------------------|-----------------------------|
| `PreContentHydrationEvent` | Dispatched before hydration |
| `PostHydrationEvent`       | Dispatched after hydration  |

### Layout / Response

| Class                    | Purpose                                         |
|--------------------------|-------------------------------------------------|
| `ContentElement`         | Tree node: properties, slots, data requirements |
| `RenderingCacheContext`  | Cache tag collection + disable flag             |
| `ContentSystemException` | Exception class with error codes                |

All classes above are in the `Shopware\Core\Framework\ContentSystem` namespace (or subnamespaces).
