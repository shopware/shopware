# DataLoader

@README.md

## Source Code References

- **Interface**: `ContentDataLoaderInterface`
- **Built-in Loaders**: `EntityLoader`, `EntityCollectionLoader`, `ProductListingDataLoader`
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

Always document config structure with `@phpstan-type` in loader class. See `EntityLoader` @phpstan-type EntityLoaderConfig for example.

## Extension Pattern

1. Implement `ContentDataLoaderInterface`
2. Add DI tag: `#[AutoconfigureTag('content_system.data_loader', ['source' => 'unique_id'])]`
3. Document config with `@phpstan-type`
4. Return `null` for missing data (don't throw)
5. Use `$context->getContext()` for entity queries

See `EntityLoader` implementation for complete example.

## Quick Reference

- **Interface**: `ContentDataLoaderInterface::load(ContentElement, DataRequirement, SalesChannelContext): mixed`
- **Registration**: `#[AutoconfigureTag('content_system.data_loader', ['source' => 'id'])]`
- **Built-in sources**: `entity`, `entity_collection`, `product_listing`
- **Config docs**: Use `@phpstan-type` in loader class
- **Return**: Data or `null` (never throw)
- **Sales channel**: Always use `$context->getContext()` for queries
- **Testing**: `StaticEntityRepository` for isolation
- **Registry**: `DataLoaderProvider` throws if source not found
