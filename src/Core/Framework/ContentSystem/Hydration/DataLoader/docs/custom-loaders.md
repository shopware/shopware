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
            return ContentDataLoaderResult::notFound();
        }
        // External API - cannot track for invalidation
        return ContentDataLoaderResult::uncacheable($weather);
    }
}
```

`LoaderInputResolver` turns the decoded config and the element's stored properties into `LoaderInputs` before the call. Every declared key is already present, dereferenced, and type-checked. Reading a key the loader did not declare throws. See [A loader consumes typed inputs resolved from its own declared specification](../../../docs/principles/data-loading.md#a-loader-consumes-typed-inputs-resolved-from-its-own-declared-specification).

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

A loader whose `PropertyReference` config key resolves to an entity id, and whose collaborator throws, additionally needs an id guard and a degradation wrap: [entity-id-guard-example.md](entity-id-guard-example.md).

## Cache Awareness

All data loaders must return `ContentDataLoaderResult` to indicate cache behavior:

| Factory Method            | When to Use                                               |
|---------------------------|-----------------------------------------------------------|
| `notFound()`              | Data not found, page remains cacheable                    |
| `cached($data, ...$tags)` | Data with invalidation tags (e.g., `'product-' . $id`)    |
| `cachedExternally($data)` | Data loaded via delegated route that handles its own tags |
| `uncacheable($data)`      | External APIs or data that cannot be cache-tracked        |

If any loader returns uncacheable data, the entire page becomes uncacheable (`RenderingCacheContext::disable()`).

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
