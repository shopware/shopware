# Extending the ContentSystem

Plugins extend the ContentSystem through three mechanisms.

## Table of Contents

1. [Extension Model](#extension-model)
2. [Custom Specification Sources](#custom-specification-sources)
3. [Custom Data Loaders](#custom-data-loaders)
4. [Event Listeners](#event-listeners)
5. [Service Tags](#service-tag-reference)
6. [Type Reference](#type-reference)

## Extension Model

| Extension Point           | Purpose                                                                |
|---------------------------|------------------------------------------------------------------------|
| **Specification Sources** | New URL patterns, entity types                                         |
| **Data Loaders**          | External APIs, calculations, aggregated data (with cache control)      |
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
    ) {}

    public function getDecorated(): AbstractSpecificationSource
    { throw new DecorationPatternException(self::class); }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    { /* Return true if path starts with 'blog/' */ }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    { /* Extract post ID from path; query blog_post_content_layout with sales channel fallback; return layout ID */ }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    { /* Resolve assignment; build data requirements and placeholder values; return SpecificationData */ }

    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    { /* Extract optional elementId from request query parameters */ }

    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    { /* Return cache tags for the blog post entity */ }
}
```

**Service registration:**

```xml
<service id="MyPlugin\ContentSystem\BlogPostSpecificationSource">
    <argument type="service" id="blog_post_content_layout.repository"/>
    <!-- Higher priority = tried first -->
    <tag name="content_system.context_factory" priority="100"/>
</service>
```

Reference: `Content/Product/Aggregate/ProductContentLayout/ProductSpecificationSource.php`

---

## Custom Data Loaders

Data loaders fetch external data—APIs, computed values, aggregations. The built-in `entity` loader handles Shopware entities; other built-in loaders handle known data structures like product listing, navigation, language, currency, payment method, and shipping method.

A data loader consists of three classes:

| Component | Base Class | Service Tag | Purpose |
|-----------|------------|-------------|---------|
| Config | `AbstractContentDataLoaderConfig` | (none) | Hold loader parameters |
| Serializer | `AbstractContentDataLoaderConfigSerializer` | `content_system.config_serializer` | Encode/decode config |
| Loader | `AbstractContentDataLoader` | `content_system.data_loader` | Fetch the data |

Define array shapes with `@phpstan-type ConfigData array{field?: type}` in the Config class, then import with `@phpstan-import-type ConfigData from ConfigClass` in the Serializer. Annotate `encode()` with `@return ConfigData` for type-safe serialization.

### Example: Weather Data Loader

The `source` value (`weather`) links all three components.

**Config:**

```php
final readonly class WeatherLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function __construct(
        public string $location,
        public string $units = 'metric',
    ) {}
}
```

**Serializer:**

```php
final class WeatherLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function getDecorated(): AbstractContentDataLoaderConfigSerializer
    { throw new DecorationPatternException(self::class); }

    public static function getSource(): string
    { return 'weather'; /* Must match loader's getRequirementType() */ }

    public function decode(array $data): AbstractContentDataLoaderConfig
    { /* Convert array to WeatherLoaderConfig */ }

    public function encode(AbstractContentDataLoaderConfig $config): array
    { /* Convert WeatherLoaderConfig to array */ }
}
```

**Loader:**

```php
final class WeatherLoader extends AbstractContentDataLoader
{
    public function __construct(private readonly WeatherApiClient $weatherClient) {}

    public function getDecorated(): AbstractContentDataLoader
    { throw new DecorationPatternException(self::class); }

    public static function getRequirementType(): string
    { return 'weather'; /* Must match serializer's getSource() */ }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        $config = $requirement->config;
        \assert($config instanceof WeatherLoaderConfig);

        $weather = $this->weatherClient->fetch($config->location);
        if ($weather === null) {
            return ContentDataLoaderResult::notFound();
        }
        // External API - cannot track for invalidation
        return ContentDataLoaderResult::uncacheable($weather);
    }
}
```

**Service registration:**

```xml
<service id="MyPlugin\ContentSystem\Weather\WeatherLoaderConfigSerializer">
    <tag name="content_system.config_serializer"/>
</service>

<service id="MyPlugin\ContentSystem\Weather\WeatherLoader">
    <argument type="service" id="MyPlugin\Service\WeatherApiClient"/>
    <tag name="content_system.data_loader"/>
</service>
```

### Cache Awareness

All data loaders must return `ContentDataLoaderResult` to indicate cache behavior:

| Factory Method            | When to Use                                               |
|---------------------------|-----------------------------------------------------------|
| `notFound()`              | Data not found, page remains cacheable                    |
| `cached($data, ...$tags)` | Data with invalidation tags (e.g., `'product-' . $id`)    |
| `cachedExternally($data)` | Data loaded via delegated route that handles its own tags |
| `uncacheable($data)`      | External APIs or data that cannot be cache-tracked        |

If any loader returns uncacheable data, the entire page becomes uncacheable.

For entity-based data, provide cache tags that match Shopware's existing invalidation patterns:

```php
// Use tag patterns matching Shopware's cache invalidation system:
// product → 'product-{id}', category → 'category-route-{id}',
// landing_page → 'landing-page-route-{id}', cms_page → 'cms-page-{id}'
return ContentDataLoaderResult::cached($entity, 'product-' . $entityId);
```

Reference: `Hydration/DataLoader/EntityLoader/`

---

## Event Listeners

Listeners modify elements before or after hydration—computing derived values, transforming structure, resolving custom placeholders.

| Event | When | Purpose |
|-------|------|---------|
| `PreContentHydrationEvent` | Before hydration | Modify layout tree, resolve placeholders |
| `PostHydrationEvent` | After hydration | Enrich data, transform structure |

Both events expose the same properties. Only `elements` is mutable:

- `elements` — `list<ContentElement>`, mutable
- `layoutId`, `layoutName`, `layoutVersionId` — layout metadata (readonly)
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

| Tag                                | Index Method           | Attributes                       |
|------------------------------------|------------------------|----------------------------------|
| `content_system.context_factory`   | N/A                    | `priority` (optional, default 0) |
| `content_system.data_loader`       | `getRequirementType()` | None                             |
| `content_system.config_serializer` | `getSource()`          | None                             |

Full DI configuration: `src/Core/Framework/DependencyInjection/content-system.xml`

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
| `SpecificationData`       | Return type of `resolveSpecificationData()` (bundles data requirements + placeholders) |
| `PlaceholderValues`       | Immutable placeholder map, created via `PlaceholderValues::from(array $values)`        |
| `RenderingSpecification`  | Layout ID, data requirements, placeholders, request, target element, cache tags        |

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
