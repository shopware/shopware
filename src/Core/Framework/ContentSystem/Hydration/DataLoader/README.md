# DataLoader

Data fetching for content elements. Elements declare `DataRequirement` objects with a `source` identifier. `DataLoaderProvider` dispatches to the registered loader matching that source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class with `load(LoaderInputs, DataRequirement, SalesChannelContext, Request)`, `getRequirementType()`, `producibleTypes()`, and `resolveProducedType()`
- `ContentDataLoaderResult` - Result with data and cache info: `notFound()`, `cached()`, `cachedExternally()`, `uncacheable()`
- `LoaderTypeCapability` - VO describing one type a loader can produce: `producedType`, `configTemplate`, `genericParameters`
- `LoaderConfigSpecification` - VO for a loader's declared config contract: an ordered `list<ConfigKeySpecification>`; `requiredKeys()` and `keysOfKind()` derive from it
- `ConfigKeySpecification` - VO for one declared config key: `name`, `kind`, `type`, `required`, `hasDefault`/`default`, optional `adminUI`, `referencedType` (the type of the value a `PropertyReference` token points at), optional `mergesInto` (another declared key this key's resolved list is unioned into)
- `ConfigKeyKind` - Enum: what a config key's value names — `Literal`, `PropertyReference`, `EntityName`
- `LoaderInputs` - The resolved inputs of one `load()` call: one entry per declared key. `has()`, `get()`, `string()`/`int()`/`bool()`/`stringList()` (throw when unresolved), `stringOrNull()`/`stringListOrNull()`; every accessor throws on an undeclared key
- `LoaderInputResolver` - Builds `LoaderInputs` from a specification, the decoded config, and the element's stored properties: dereferences each `PropertyReference` token (an absent or wrongly typed stored value resolves to null, never throws) and folds each `mergesInto` key into its target, target entries first
- `DataLoaderProvider` - Service locator dispatcher: `get($source)` throws if the source is not registered; `getSources()` lists every registered source identifier (used by the type resolver)

## Built-in Loaders

- [**EntityLoader** (`entity`)](docs/entity.md) — Single entity by ID
- [**EntityCollectionLoader** (`entity_collection`)](docs/entity_collection.md) — Multiple entities by IDs
- [**ProductListingDataLoader** (`product_listing`)](docs/product_listing.md) — Product listings with filters, sorting, pagination
- [**NavigationDataLoader** (`navigation`)](docs/navigation.md) — Navigation tree; aliases: `main-navigation`, `service-navigation`, `footer-navigation`
- **ServiceMenuDataLoader** (`service_menu`) — Service menu navigation
- **CrossSellingDataLoader** (`cross_selling`) — Cross-selling product sets
- **ProductReviewDataLoader** (`product_review`) — Product reviews
- **ProductSearchDataLoader** (`product_search`) — Product search results
- **ProductSuggestDataLoader** (`product_suggest`) — Product search suggestions
- **BreadcrumbDataLoader** (`breadcrumb`) — Breadcrumb trail
- [**LanguageDataLoader** (`language`)](docs/language.md), [**CurrencyDataLoader** (`currency`)](docs/currency.md), [**PaymentMethodDataLoader** (`payment_method`)](docs/payment_method.md), [**ShippingMethodDataLoader** (`shipping_method`)](docs/shipping_method.md)

The six unlinked loaders above — `service_menu`, `cross_selling`, `product_review`, `product_search`, `product_suggest`, `breadcrumb` — have no configuration reference yet.

## Guides

- [docs/data-requirements.md](docs/data-requirements.md) - What a `dataRequirements` entry declares, when to use one, and its fields.
- [docs/custom-loaders.md](docs/custom-loaders.md) - Registering a new data source: config, serializer, loader, and cache behavior.
- [docs/introspection.md](docs/introspection.md) - The Admin API surface clients read to discover available sources and their config keys.

## Extension Point

1. Extend `AbstractContentDataLoader`, implement `getRequirementType()` returning your source identifier
2. Annotate with `@extends AbstractContentDataLoader<YourStruct>` — the default `producibleTypes()`/`resolveProducedType()` derive the produced type from it; a missing or unresolvable annotation fails the build (see Schema/)
3. Create config class extending `AbstractContentDataLoaderConfig` with matching serializer
4. Override `configSpecification()` when the config serializer accepts keys — declares the loader's config contract for the derived completion residue, and is the only place a key's default may live
5. Read every input off the `LoaderInputs` argument (see [AGENTS.md](AGENTS.md) Constraints for what `load()` may and may not touch)
6. Tag with `content_system.data_loader` in the owning domain's DI — service locator uses `getRequirementType()` as key
7. Return `ContentDataLoaderResult` with appropriate cache info — never throw exceptions

Fixed-type loaders need no override: the base `producibleTypes()` returns one `LoaderTypeCapability` derived from `@extends`, and `resolveProducedType()` returns that type ignoring config.

Wildcard loaders that serve multiple concrete types (e.g., the generic `entity`/`entity_collection` loaders) override both `producibleTypes()` and `resolveProducedType()` to enumerate the live definition registry — one capability per registered entity, each carrying the `configTemplate` (`['entity' => <name>]`) needed to produce it; each also declares a `configSpecification()` requiring `entity` and `property`, so the derived residual config key is `property`. Enumeration skips definitions that have no addressable type: `MappingEntityDefinition`s, plus the `entity` loader skips an `ArrayEntity` entity class and the `entity_collection` loader skips a bare `EntityCollection` collection class. `resolveProducedType()` throws `ContentSystemException::unknownLoaderEntity` when the configured entity name is not registered.
