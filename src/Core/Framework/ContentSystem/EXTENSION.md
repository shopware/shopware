# Extending the ContentSystem

Plugins extend the ContentSystem through six mechanisms.

## Table of Contents

1. [Extension Model](#extension-model)
2. [Custom Specification Sources](#custom-specification-sources)
3. [Event Listeners](#event-listeners)
4. [Service Tags](#service-tag-reference)
5. [Type Reference](#type-reference)

## Extension Model

| Extension Point           | Purpose                                                                |
|---------------------------|------------------------------------------------------------------------|
| **Element Types**         | New content components with declared properties and slots — authored per [Layout/Type/docs/custom-types.md](Layout/Type/docs/custom-types.md), not covered here |
| **Style Options**         | New universal per-breakpoint presentation attributes for every element — authored per [Layout/Element/Style/docs/custom-options.md](Layout/Element/Style/docs/custom-options.md), not covered here |
| **Binding Specifications** | Pre-validated data wirings for an element type, applied in one action — authored per [Binding/README.md](Binding/README.md), not covered here |
| **Specification Sources** | New URL patterns, entity types                                         |
| **Data Loaders**          | External APIs, calculations, aggregated data (with cache control) — authored per [Hydration/DataLoader/docs/custom-loaders.md](Hydration/DataLoader/docs/custom-loaders.md), not covered here |
| **Event Listeners**       | Modify layout structure, enrich data, transform properties, cache tags |

---

## Custom Specification Sources

Specification sources translate path patterns into rendering specifications via discrete steps. A blog plugin rendering posts at `/store-api/content/blog/{id}` implements a source that recognizes the `blog/` prefix and resolves the corresponding layout.

### Chain of Responsibility

Sources are tried in priority order (highest first). The first source where `supports()` returns true handles the request via `RenderingSpecificationFactory`.

| Priority                     | Behavior                                      |
|------------------------------|-----------------------------------------------|
| Higher values                | Tried first                                   |
| `supports()` returns `false` | Skip to next source                           |
| `supports()` returns `true`  | Handle request via stepped resolution         |
| Throw exception              | Only when your source should handle but fails |

### Example: Blog Post Source

```php
final class BlogPostSpecificationSource extends AbstractSpecificationSource
{
    public function __construct(
        private readonly EntityRepository $blogLayoutAssignmentRepository,
        // Must be an AbstractContentLayoutAssignableDefinition subclass (see note below)
        private readonly BlogPostContentLayoutDefinition $definition,
    ) {}

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    { /* Return true if path starts with 'blog/' */ }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    { /* Extract post ID from path; query blog_post_content_layout with sales channel fallback; return layout ID */ }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    { /* Build data requirements and placeholder values from the path and request — no layout assignment lookup needed; return SpecificationData */ }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    { /* Extract optional elementId from request query parameters */ }

    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    { /* Return cache tags for the blog post entity */ }
}
```

**Service registration:**

```xml
<!-- The assignable-entity definition the compiler pass derives the entity type from -->
<service id="MyPlugin\ContentSystem\BlogPostContentLayoutDefinition">
    <tag name="shopware.entity.definition"/>
</service>

<service id="MyPlugin\ContentSystem\BlogPostSpecificationSource">
    <argument type="service" id="blog_post_content_layout.repository"/>
    <argument type="service" id="MyPlugin\ContentSystem\BlogPostContentLayoutDefinition"/>
    <!-- Higher priority = tried first -->
    <tag name="content_system.entity_specification_source" priority="100"/>
</service>
```

**Required: an assignable-entity definition.** A `content_system.entity_specification_source` source must receive an `AbstractContentLayoutAssignableDefinition` subclass (here `BlogPostContentLayoutDefinition`) as a constructor argument. At container build, `ContentLayoutAssignableCompilerPass` introspects each tagged source's arguments for such a definition and derives the entity type from its `getContentLayoutEntityType()`; a source with no assignable-definition argument fails compilation with `missingAssignableDefinition`. Define the assignment entity and its definition alongside the source — see `Adapter/Entity/AbstractContentLayoutAssignableDefinition`.

Reference: `src/Core/Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource.php` (and `ProductContentLayoutDefinition.php` in the same directory)

### Assignment-Free Resolution (Preview Support)

The steps above resolve a layout from a path for the Store API. To also let the Admin preview and diagnose actions work against an entity that has no assignment yet, an entity-backed source overrides three more methods: `supportsEntityType(string $entityType): bool` (match the source's content-layout entity type), `resolveSpecificationDataForEntity(string $entityId, Request, SalesChannelContext): SpecificationData` (build the spec data from the entity id directly, with no assignment lookup), and `providedRootContext(Context $context): list<ProvidedContext>` (the root-ambient context the source supplies to the layout's top-level elements; default `[]`). The diagnose route (`POST /api/_action/content-system/layout/diagnose`) resolves its request `rootSource` through `Adapter/RootSourceRegistry`, which calls `providedRootContext()` on the matching source to run its binding-resolvability checks — a source that leaves it at the default exposes no root context, so those checks have nothing to bind against. An assignable entity type registered this way also appears in `GET /api/_info/content-system-entity-types.json`. See `ADMINISTRATION.md`.

---

## Event Listeners

Listeners modify elements before or after hydration—computing derived values, transforming structure, resolving custom placeholders.

| Event                      | When             | Purpose                                  |
|----------------------------|------------------|------------------------------------------|
| `PreContentHydrationEvent` | Before hydration | Modify layout tree, resolve placeholders |
| `PostHydrationEvent`       | After hydration  | Enrich data, transform structure         |

Both events expose the same properties. Only `elements` is mutable:

- `elements` — `list<ContentElement>`, mutable
- `layout` — `LayoutReference` exposing `id`, `name`, `version` of the rendered layout (readonly)
- `specification` — `RenderingSpecification` (readonly)
- `mode` — `RenderingMode` (readonly)
- `salesChannelContext` — `SalesChannelContext` (readonly)
- `cacheContext` — `RenderingCacheContext`, for cache tag management (readonly reference, but methods mutate state)

### Working with ContentElement

`ContentElement` is the tree node in the layout. In event listeners, access and modify element data through these methods:

| Method                                         | Purpose                                          |
|------------------------------------------------|--------------------------------------------------|
| `getProperty(string $key): mixed`              | Get property value (returns null if not found)   |
| `setProperty(string $key, mixed $value): void` | Set a property value                             |
| `hasProperty(string $key): bool`               | Check if property exists                         |
| `getProperties(): array`                       | Get all properties                               |
| `getId(): string`                              | Element ID                                       |
| `getComponent(): string`                       | Component type identifier                        |
| `getSlots(): array`                            | Named child slots (`array<string, SlotContent>`) |
| `allSlotElements(): Generator`                 | Generator yielding all direct child elements     |
| `hasSlots(): bool`                             | Whether element has child slots                  |

### Example: Reading Time Listener

```php
#[AsEventListener(event: PostHydrationEvent::class, priority: 500)]
class ReadingTimeSubscriber
{
    private const WORDS_PER_MINUTE = 200;

    public function __invoke(PostHydrationEvent $event): void
    {
        foreach ($event->elements as $element) {
            $content = $element->getProperty('content');
            if (!\is_string($content)) {
                continue;
            }
            $wordCount = str_word_count(strip_tags($content));
            $element->setProperty('readingTimeMinutes', (int) ceil($wordCount / self::WORDS_PER_MINUTE));
        }
    }
}
```

### Cache Context in Subscribers

Subscribers can add cache tags or disable caching via `$event->cacheContext`:

```php
// Add invalidation tags for external data
$event->cacheContext->addTags(['my-plugin-weather-' . $location]);

// Disable caching entirely (use sparingly)
$event->cacheContext->disable();
```

### Priority Guidelines

| Range                | Usage                              |
|----------------------|------------------------------------|
| `>= 6000`            | Run BEFORE core processing         |
| `< 6000 and >= 1000` | **Reserved for core** - do not use |
| `< 1000 and >= 0`    | Run AFTER core processing          |
| `< 0`                | Run after all other subscribers    |

Reference: `Event/Listener/PreHydration/PlaceholderResolutionSubscriber.php`

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
