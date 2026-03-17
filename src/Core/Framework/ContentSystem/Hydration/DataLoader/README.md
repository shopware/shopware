# DataLoader

Data fetching for content elements. Elements declare `DataRequirement` objects with a `source` identifier. `DataLoaderProvider` dispatches to the registered loader matching that source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class with `load()`, `getRequirementType()`, `getProvidedData()`, and `overrideProvidedTypes()`
- `ContentDataLoaderResult` - Result with data and cache info: `notFound()`, `cached()`, `cachedExternally()`, `uncacheable()`
- `ContentSystemDataLoaderTypeDescriptor` - DTO describing a loader's provided data type (`className` + `genericParameters`)
- `DataLoaderProvider` - Service locator dispatcher (throws if source not found)

## Built-in Loaders

- **EntityLoader** (`entity`) — Single entity by ID
- **EntityCollectionLoader** (`entity_collection`) — Multiple entities by IDs
- **ProductListingDataLoader** (`product_listing`) — Product listings with filters, sorting, pagination
- **NavigationDataLoader** (`navigation`) — Navigation tree; aliases: `main-navigation`, `service-navigation`, `footer-navigation`
- **ServiceMenuDataLoader** (`service_menu`) — Service menu navigation
- **CrossSellingDataLoader** (`cross_selling`) — Cross-selling product sets
- **ProductReviewDataLoader** (`product_review`) — Product reviews
- **ProductSearchDataLoader** (`product_search`) — Product search results
- **ProductSuggestDataLoader** (`product_suggest`) — Product search suggestions
- **BreadcrumbDataLoader** (`breadcrumb`) — Breadcrumb trail
- **LanguageDataLoader** (`language`), **CurrencyDataLoader** (`currency`), **PaymentMethodDataLoader** (`payment_method`), **ShippingMethodDataLoader** (`shipping_method`)

## Extension Point

1. Extend `AbstractContentDataLoader`, implement `getRequirementType()` returning your source identifier
2. Annotate with `@extends AbstractContentDataLoader<YourStruct>` — required for compile-time type introspection (see Schema/)
3. Create config class extending `AbstractContentDataLoaderConfig` with matching serializer
4. Tag with `content_system.data_loader` in the owning domain's DI — service locator uses `getRequirementType()` as key
5. Return `ContentDataLoaderResult` with appropriate cache info — never throw exceptions

Override `getProvidedData()` only for special cases (e.g., wildcard entity loaders). The default implementation extracts the type from `@extends`.

Override `overrideProvidedTypes()` for wildcard loaders that serve multiple concrete types (e.g., generic entity loaders). The method is called at runtime by the resolver, not at build time. Default returns `[]` (no expansion).
