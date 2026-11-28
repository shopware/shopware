# Custom Data Loaders

Data loaders fetch external data—APIs, computed values, aggregations. The built-in `entity` loader handles Shopware entities; other built-in loaders handle known data structures like product listing, navigation, language, currency, payment method, and shipping method.

| Component  | Base Class                                  | Service Tag                        | Purpose                |
|------------|---------------------------------------------|------------------------------------|------------------------|
| Config     | `AbstractContentDataLoaderConfig`           | (none)                             | Hold loader parameters |
| Serializer | `AbstractContentDataLoaderConfigSerializer` | `content_system.config_serializer` | Encode/decode config   |
| Loader     | `AbstractContentDataLoader`                 | `content_system.data_loader`       | Fetch the data         |

Define array shapes with `@phpstan-type ConfigData array{field?: type}` in the Config class, then import with `@phpstan-import-type ConfigData from ConfigClass` in the Serializer. Annotate `encode()` with `@return ConfigData` for type-safe serialization.

## Example: Weather Data Loader

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
/**
 * @extends AbstractContentDataLoader<WeatherStruct>
 */
final class WeatherLoader extends AbstractContentDataLoader
{
    public function __construct(private readonly WeatherApiClient $weatherClient) {}

    public static function getRequirementType(): string
    { return 'weather'; /* Must match serializer's getSource() */ }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('location', ConfigKeyKind::Literal, 'string', required: true),
            new ConfigKeySpecification('units', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'metric'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        $weather = $this->weatherClient->fetch($inputs->string('location'), $inputs->string('units'));
        if ($weather === null) {
            // The lookup ran, and no tag covers its empty result
            return ContentDataLoaderResult::foundNothingUncacheable();
        }
        // External API - cannot track for invalidation
        return ContentDataLoaderResult::uncacheable($weather);
    }
}
```

`LoaderInputResolver` turns the decoded config and the element's stored properties into `LoaderInputs` before the call. Every declared key has an entry, dereferenced and type-checked. Reading a key the loader did not declare throws. See [A loader consumes typed inputs resolved from its own declared specification](../../../docs/principles/data-loading.md#a-loader-consumes-typed-inputs-resolved-from-its-own-declared-specification).

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

A loader whose `PropertyReference` key resolves to an entity id needs a `Uuid::isValid()` guard. One whose service call throws needs a `ShopwareHttpException` catch: [entity-id-guard-example.md](entity-id-guard-example.md).

## Cache Awareness

Every data loader returns a `ContentDataLoaderResult`. Each factory names one fact about how the loader reached its result, and the cache dependency follows from that fact.

| Factory Method               | When a loader returns it                                                                                                                  | Effect on the cache context                                                                   |
|------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| `noInput()`                  | It read nothing outside the stored layout and has no value, for example a guard returned before a lookup.                                  | Adds no tag, and the layout tags of the render cover the result.                              |
| `layoutOnly()`               | It read nothing outside the stored layout, and the value depends only on that layout.                                                      | Adds no tag, and the layout tags of the render cover the result.                              |
| `foundNothing()`             | Its own lookup produced no data, either because it found nothing or because the loader caught a `ShopwareHttpException`, and a tag covers the request. | Adds the tags that cover the requested data.                                                  |
| `foundNothingUncacheable()`  | Its own lookup produced no data, either because it found nothing or because the loader caught a `ShopwareHttpException`, and no tag covers the request. | Disables the cache context, so the render is not cached (`RenderingCacheContext::disable()`). |
| `failedExternally()`         | A delegate that owns the caching of its data, such as a Store API route, threw a `ShopwareHttpException`.                                 | Adds no tag, and the delegate owns the caching of that outcome.                               |
| `cached()`                   | It read its own data, and at least one tag covers that data.                                                                               | Adds the given tags.                                                                          |
| `cachedExternally()`         | A delegate, such as a Store API route, provided the data and adds the cache tags of the complete returned value to the response.                              | Adds no tag, and the delegate owns the caching of that data.                                  |
| `uncacheable($data)`         | It loaded data, and no tag covers that data, for example data from an external API.                                                        | Disables the cache context, so the render is not cached (`RenderingCacheContext::disable()`). |

If any loader returns an `uncacheable($data)` or `foundNothingUncacheable()` result, the entire page becomes uncacheable (`RenderingCacheContext::disable()`).

For entity-based data, provide cache tags that match Shopware's existing invalidation patterns:

```php
// Use tag patterns matching Shopware's cache invalidation system:
// product → 'product-{id}', category → 'category-route-{id}',
// landing_page → 'landing-page-route-{id}', cms_page → 'cms-page-{id}'
return ContentDataLoaderResult::cached($entity, 'product-' . $entityId);
```

Reference: `../EntityLoader/`

## Discoverability

A registered loader's `source` value, its declared config keys (via `configSpecification()`), and the capabilities it produces (via `producibleTypes()`) appear in `GET /api/_info/content-system-data-loaders.json`. The Administration reads it to offer the data source when authoring `dataRequirements`. See [Data Loader Introspection](introspection.md).
