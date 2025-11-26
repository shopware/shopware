# DataLoader

@README.md

## Source Code References

- **Abstract Class**: `AbstractContentDataLoader`
- **Config Abstract Class**: `AbstractContentDataLoaderConfig`
- **Serializer Abstract Class**: `AbstractContentDataLoaderConfigSerializer`
- **Serializer Provider**: `DataLoaderConfigSerializerProvider`
- **Built-in Loaders**: `EntityLoader/EntityLoader`, `EntityCollectionLoader/EntityCollectionLoader`, `ProductListingLoader/ProductListingDataLoader`
- **Registry**: `DataLoaderProvider`

## Key Conventions

### Return Null, Never Throw

Loaders return `null` for missing data, never throw exceptions. Hydration continues with null value stored in element property.

```php
// Right
return $this->repository->search($criteria, $context)->first();  // Returns null if not found

// Wrong
if ($entity === null) {
    throw new NotFoundException();  // Don't do this
}
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
5. Return `null` for missing data (don't throw)
6. Use `$context->getContext()` for entity queries

See `EntityLoader/` directory for complete example.

## Quick Reference

- **Abstract Class**: `AbstractContentDataLoader::load(ContentElement, DataRequirement, SalesChannelContext, Request): mixed`
- **Registration**: `#[AutoconfigureTag('content_system.data_loader', ['source' => 'id'])]`
- **Built-in sources**: `entity`, `entity_collection`, `product_listing`
- **Config**: Separate config class + serializer
- **Return**: Data or `null` (never throw)
- **Sales channel**: Always use `$context->getContext()` for queries
- **Testing**: `StaticEntityRepository` for isolation
- **Registry**: `DataLoaderProvider` throws if source not found, `DataLoaderConfigSerializerProvider` for serialization
