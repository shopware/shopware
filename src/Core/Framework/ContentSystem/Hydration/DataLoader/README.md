# DataLoader

Data fetching for content elements. Elements declare `DataRequirement` objects with a `source` identifier. `DataLoaderProvider` dispatches to the registered loader matching that source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class with `load()` and `getRequirementType()`
- `ContentDataLoaderResult` - Result with data and cache info: `notFound()`, `cached()`, `cachedExternally()`, `uncacheable()`
- `DataLoaderProvider` - Service locator dispatcher (throws if source not found)

## Built-in Loaders

- **EntityLoader** (`entity`) — Single entity by ID
- **EntityCollectionLoader** (`entity_collection`) — Multiple entities by IDs
- **ProductListingDataLoader** (`product_listing`) — Product listings with filters, sorting, pagination
- **NavigationDataLoader** (`navigation`) — Navigation tree; aliases: `main-navigation`, `service-navigation`, `footer-navigation`
- **LanguageDataLoader** (`language`), **CurrencyDataLoader** (`currency`), **PaymentMethodDataLoader** (`payment_method`), **ShippingMethodDataLoader** (`shipping_method`)

## Extension Point

1. Extend `AbstractContentDataLoader`, implement `getRequirementType()` returning your source identifier
2. Create config class extending `AbstractContentDataLoaderConfig` with matching serializer
3. Tag with `content_system.data_loader` in DI — service locator uses `getRequirementType()` as key
4. Return `ContentDataLoaderResult` with appropriate cache info — never throw exceptions
