# Custom Data Loaders

The plugin-facing guide to registering a new data source: what to extend, what to tag, the config and serializer pair it needs, and how it declares cache behavior.

Data loaders fetch external data—APIs, computed values, aggregations. The built-in `entity` loader handles Shopware entities; other built-in loaders handle known data structures like product listing, navigation, language, currency, payment method, and shipping method.

A data loader consists of three classes:

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

`load()` never reads the config or the element itself. `LoaderInputResolver` turns the decoded config and the element's stored properties into `LoaderInputs` before the call: every declared key is already present, dereferenced, and type-checked, and reading a key the loader did not declare throws. Declare the fallback in `configSpecification()` — an in-body `??` would drift from what the schema advertises.

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

## Example: Dereferencing an Entity ID

`WeatherLoaderConfig` above holds plain strings, not entity ids, so it needs neither of the checks below. A loader whose `PropertyReference` config key does resolve to an entity id needs both.

A `PropertyReference` value arrives as whatever string the stored map holds, including an unsubstituted template placeholder such as `{{productId}}` left literal on a layout that never bound the property. `LoaderInputResolver::dereference()` only type-checks the value as a string, so a placeholder passes through untouched. Guard the value with `Uuid::isValid()` before using it as an id, and wrap any route call the loader makes in a `try`/`catch` for the domain exceptions that route can throw:

```php
public function configSpecification(): LoaderConfigSpecification
{
    return new LoaderConfigSpecification([
        new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'productId'),
    ]);
}

public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
{
    $productId = $inputs->stringOrNull('property');

    if ($productId === null) {
        return ContentDataLoaderResult::notFound();
    }

    $productId = u($productId)->lower()->toString();

    // Uuid::isValid() runs after the lowercase: Uuid::VALID_PATTERN is lowercase-only, so guarding the
    // raw value would reject a legitimate uppercase id. An unsubstituted placeholder such as
    // "{{productId}}" passes LoaderInputResolver::dereference()'s string type check untouched, so it
    // reaches here as-is and fails this guard instead of the route's own id lookup.
    if (!Uuid::isValid($productId)) {
        return ContentDataLoaderResult::notFound();
    }

    try {
        $product = $this->productRoute->load($productId, $context);
    } catch (EntityNotFoundException) {
        return ContentDataLoaderResult::notFound();
    }

    return ContentDataLoaderResult::cachedExternally($product);
}
```

The catch clause names the exceptions the specific route can throw, found by tracing its call chain, not every exception the route's own file mentions in a `throw`. A route that delegates to a collaborator can throw from inside that collaborator with no `throw` visible in the route file itself.

## Cache Awareness

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

Reference: `../EntityLoader/`

## Discoverability

A registered loader's `source` value, its declared config keys (via `configSpecification()`), and the capabilities it produces (via `producibleTypes()`) appear in `GET /api/_info/content-system-data-loaders.json`, which the Administration reads to offer the data source when authoring `dataRequirements`. Wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()`/`resolveProducedType()` to enumerate the live definition registry. See [Data Loader Introspection](introspection.md).
