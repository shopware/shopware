# DataLoader

Data fetching for content elements. Elements declare `DataRequirement` objects with a `source` identifier. `DataLoaderProvider` dispatches to the registered loader matching that source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class with `load()`, `getRequirementType()`, `producibleTypes()`, and `resolveProducedType()`
- `ContentDataLoaderResult` - Result with data and cache info: `notFound()`, `cached()`, `cachedExternally()`, `uncacheable()`
- `LoaderTypeCapability` - VO describing one type a loader can produce: `producedType`, `configTemplate`, `requiredConfigKeys`, `genericParameters`
- `DataLoaderProvider` - Service locator dispatcher: `get($source)` throws if the source is not registered; `getSources()` lists every registered source identifier (used by the type resolver)

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
2. Annotate with `@extends AbstractContentDataLoader<YourStruct>` — the default `producibleTypes()`/`resolveProducedType()` derive the produced type from it; a missing or unresolvable annotation fails the build (see Schema/)
3. Create config class extending `AbstractContentDataLoaderConfig` with matching serializer
4. Tag with `content_system.data_loader` in the owning domain's DI — service locator uses `getRequirementType()` as key
5. Return `ContentDataLoaderResult` with appropriate cache info — never throw exceptions

Fixed-type loaders need no override: the base `producibleTypes()` returns one `LoaderTypeCapability` derived from `@extends`, and `resolveProducedType()` returns that type ignoring config.

Wildcard loaders that serve multiple concrete types (e.g., the generic `entity`/`entity_collection` loaders) override both `producibleTypes()` and `resolveProducedType()` to enumerate the live definition registry — one capability per registered entity, each carrying the `configTemplate` (`['entity' => <name>]`) and `requiredConfigKeys` (`['property']`) needed to produce it. Enumeration skips definitions that have no addressable type: `MappingEntityDefinition`s, plus the `entity` loader skips an `ArrayEntity` entity class and the `entity_collection` loader skips a bare `EntityCollection` collection class. `resolveProducedType()` throws `ContentSystemException::unknownLoaderEntity` when the configured entity name is not registered.
