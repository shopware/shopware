# DataLoader

@README.md

## Source Code References

- **Abstract Class**: `AbstractContentDataLoader`
- **Config Abstract Class**: `AbstractContentDataLoaderConfig`
- **Serializer Abstract Class**: `AbstractContentDataLoaderConfigSerializer`
- **Serializer Provider**: `DataLoaderConfigSerializerProvider`
- **Result Class**: `ContentDataLoaderResult`
- **Built-in Loaders**:
  - `EntityLoader/EntityLoader`
  - `EntityCollectionLoader/EntityCollectionLoader`
  - `ProductListingLoader/ProductListingDataLoader`
  - `NavigationLoader/NavigationDataLoader`
  - `LanguageLoader/LanguageDataLoader`
  - `CurrencyLoader/CurrencyDataLoader`
  - `PaymentMethodLoader/PaymentMethodDataLoader`
  - `ShippingMethodLoader/ShippingMethodDataLoader`
- **Registry**: `DataLoaderProvider`

## Key Conventions

### Return ContentDataLoaderResult, Never Throw

Loaders return `ContentDataLoaderResult` for all outcomes, never throw exceptions. Hydration continues with null data stored in element property for not-found cases.

```php
// Not found - page remains cacheable
return ContentDataLoaderResult::notFound();

// Found with cache tags
$entity = $this->repository->search($criteria, $context)->first();
return ContentDataLoaderResult::cached($entity, 'product-' . $entityId);

// Found but cannot be cached
return ContentDataLoaderResult::uncacheable($entity);

// Wrong - never throw
throw new NotFoundException();  // Don't do this
```

### Source Identifier Matching

DI tag `source` attribute must match `DataRequirement->source`:

```php
#[AutoconfigureTag('content_system.data_loader', ['source' => 'weather'])]
class WeatherLoader { }

// Later in layout:
new DataRequirement(key: 'data', source: 'weather', config: [])  // Must match
```

### Config Schema Documentation

Create config class extending `AbstractContentDataLoaderConfig` with `@phpstan-type`. Create serializer extending `AbstractContentDataLoaderConfigSerializer`. See `EntityLoaderConfig` and `EntityLoaderConfigSerializer` for example.

## Extension Pattern

1. Create config class extending `AbstractContentDataLoaderConfig`
2. Create serializer extending `AbstractContentDataLoaderConfigSerializer`
3. Extend `AbstractContentDataLoader`
4. Register serializer in DI with source tag
5. Return `ContentDataLoaderResult` with appropriate cache info (don't throw)
6. Use `$context->getContext()` for entity queries
7. Resolve cache tags via `EntityCacheTagResolver` for entity-based data

See `EntityLoader/` directory for complete example.

## Quick Reference

- **Abstract Class**: `AbstractContentDataLoader::load(ContentElement, DataRequirement, SalesChannelContext, Request): ContentDataLoaderResult`
- **Registration**: `#[AutoconfigureTag('content_system.data_loader', ['source' => 'id'])]`
- **Built-in sources**: `entity`, `entity_collection`, `product_listing`, `navigation`, `language`, `currency`, `payment_method`, `shipping_method`
- **Config**: Separate config class + serializer
- **Return**: `ContentDataLoaderResult` (never throw)
- **Cache results**: `notFound()`, `cached($data, ...$tags)`, `cachedExternally($data)`, `uncacheable($data)`
- **Sales channel**: Always use `$context->getContext()` for queries
- **Testing**: `StaticEntityRepository` for isolation
- **Registry**: `DataLoaderProvider` throws if source not found, `DataLoaderConfigSerializerProvider` for serialization
