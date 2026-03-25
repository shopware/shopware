# DataLoader

Data fetching for content elements. Dispatches to loader implementations based on data requirement source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class
- `AbstractContentDataLoaderConfig` - Config base class
- `AbstractContentDataLoaderConfigSerializer` - Serializer base class
- `ContentDataLoaderResult` - Result object with data and cache tag information
- `DataLoaderProvider` - Dispatcher, selects loader by source identifier
- `DataLoaderConfigSerializerProvider` - Serializer registry
- `EntityLoader` - Single entity loading via EntityRepository
- `EntityCollectionLoader` - Multiple entities loading via EntityRepository
- `ProductListingDataLoader` - Product listing queries

## Loader Selection

Elements declare DataRequirements with `source` identifier (e.g., "entity", "product_listing"). DataLoaderProvider dispatches to registered loader by source. Loaders tagged in DI with source identifier.

Extensions extend AbstractContentDataLoader, tag with source identifier, handle specific data fetching.

## Built-in Loaders

Core provides these loaders:

**EntityLoader** (`source: "entity"`)
- Loads single entity by ID from element property
- Config: See `EntityLoaderConfig` class

**EntityCollectionLoader** (`source: "entity_collection"`)
- Loads multiple entities by IDs from element property as an array
- Config: See `EntityLoaderConfig` class (shared with EntityLoader)

**ProductListingDataLoader** (`source: "product_listing"`)
- Product listings with filters, sorting, pagination
- Config: See `ProductListingLoaderConfig` class

**NavigationDataLoader** (`source: "navigation"`)
- Loads navigation tree via NavigationLoaderInterface
- Config: `rootId` (alias or UUID), `depth`
- Supports aliases: `main-navigation`, `service-navigation`, `footer-navigation`

**LanguageDataLoader** (`source: "language"`)
- Loads available languages via AbstractLanguageRoute

**CurrencyDataLoader** (`source: "currency"`)
- Loads available currencies via AbstractCurrencyRoute

**PaymentMethodDataLoader** (`source: "payment_method"`)
- Loads payment methods via AbstractPaymentMethodRoute

**ShippingMethodDataLoader** (`source: "shipping_method"`)
- Loads shipping methods via AbstractShippingMethodRoute

## Loader Interface

```php
load(
  ContentElement $element,
  DataRequirement $requirement,
  SalesChannelContext $context,
  Request $request
): ContentDataLoaderResult
```

Loaders receive element (for context), requirement (what to load), context (sales channel), request. Return `ContentDataLoaderResult` with data and cache information. Hydrator stores data in element properties by requirement key and accumulates cache tags.

## Cache Awareness

Loaders must return `ContentDataLoaderResult` to indicate cache behavior:

| Factory Method | Behavior |
|----------------|----------|
| `notFound()` | No data found, page remains cacheable |
| `cached($data, ...$tags)` | Data with specific cache invalidation tags |
| `cachedExternally($data)` | Data loaded, tags handled by delegated route |
| `uncacheable($data)` | Data cannot be cache-tracked, disables page caching |

Cache tags propagate to `RenderingCacheContext`. If any loader returns uncacheable data, the entire page becomes uncacheable.

## Extension Point

Extensions extend AbstractContentDataLoader for custom data sources (API calls, calculations, etc.). Register via DI tag with unique source identifier. Elements declare requirements with that source. Must return appropriate `ContentDataLoaderResult` for cache awareness.
