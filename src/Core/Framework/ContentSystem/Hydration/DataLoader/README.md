# DataLoader

Data fetching for content elements. Elements declare `DataRequirement` objects with a `source` identifier. `DataLoaderProvider` dispatches to the registered loader matching that source.

## Key Classes

- `AbstractContentDataLoader` - Loader base class
- `ContentDataLoaderResult` - Result with data and cache info
- `LoaderTypeCapability` - VO describing one type a loader can produce
- `LoaderConfigSpecification` - VO for a loader's declared config contract: an ordered `list<ConfigKeySpecification>`
- `ConfigKeySpecification` - VO for one declared config key. `referencedType` is the type of the value a `PropertyReference` token points at, `mergesInto` names another declared key this key's resolved list is unioned into
- `ConfigKeyKind` - Enum: what a config key's value names
- `LoaderInputs` - The resolved inputs of one `load()` call: one entry per declared key. `has()`, `get()`, `string()`/`int()`/`bool()`/`stringList()` (throw when unresolved), `stringOrNull()`/`stringListOrNull()`; every accessor throws on an undeclared key
- `LoaderInputResolver` - Builds `LoaderInputs` from a specification, the decoded config, and the element's stored properties: dereferences each `PropertyReference` token (an absent or wrongly typed stored value resolves to null, never throws) and folds each `mergesInto` key into its target, target entries first
- `DataLoaderProvider` - Service locator dispatcher: `get($source)` throws if the source is not registered; `getSources()` lists every registered source identifier (used by the type resolver)

## Built-in Loaders

- [**EntityLoader** (`entity`)](docs/entity.md) — Single entity by ID
- [**EntityCollectionLoader** (`entity_collection`)](docs/entity_collection.md) — Multiple entities by IDs
- [**ProductListingDataLoader** (`product_listing`)](docs/product_listing.md) — Product listings with filters, sorting, pagination
- [**NavigationDataLoader** (`navigation`)](docs/navigation.md) — Navigation tree
- **ServiceMenuDataLoader** (`service_menu`) — Service menu navigation
- **CrossSellingDataLoader** (`cross_selling`) — Cross-selling product sets
- **ProductReviewDataLoader** (`product_review`) — Product reviews
- **ProductSearchDataLoader** (`product_search`) — Product search results
- **ProductSuggestDataLoader** (`product_suggest`) — Product search suggestions
- **BreadcrumbDataLoader** (`breadcrumb`) — Breadcrumb trail
- [**LanguageDataLoader** (`language`)](docs/language.md), [**CurrencyDataLoader** (`currency`)](docs/currency.md), [**PaymentMethodDataLoader** (`payment_method`)](docs/payment_method.md), [**ShippingMethodDataLoader** (`shipping_method`)](docs/shipping_method.md)

## Degradation boundary

A loader degrades a broken element to `notFound()` instead of failing the whole render. Its imperative form lives in [AGENTS.md](AGENTS.md). See [A loader degrades on a named domain outcome and lets every other fault propagate](../../docs/principles/data-loading.md#a-loader-degrades-on-a-named-domain-outcome-and-lets-every-other-fault-propagate).

An entity id read off a `PropertyReference` is guarded with `Uuid::isValid()` after lowercasing and before first use, and `EntityLoader::load()` and every other built-in loader that reads an id run the guard. Rule and reason: [A loader degrades on a named domain outcome and lets every other fault propagate](../../docs/principles/data-loading.md#a-loader-degrades-on-a-named-domain-outcome-and-lets-every-other-fault-propagate).

A collaborator call inside `load()` is wrapped in `catch (ShopwareHttpException)`: a failure Shopware modelled as an HTTP outcome degrades the element. `HttpException` extends `ShopwareHttpException`, so both exception roots are one inheritance line. The boundary is deliberate rather than a proof of exhaustiveness: third-party code can still throw outside it, for example a pre/post/error extension listener, whose throw propagates uncaught out of `ExtensionDispatcher`'s event dispatch (`publish()` rethrows the wrapped call's exception unless an error listener supplies a result). Why the single ancestor and not an enumerated union: [A loader degrades on a named domain outcome and lets every other fault propagate](../../docs/principles/data-loading.md#a-loader-degrades-on-a-named-domain-outcome-and-lets-every-other-fault-propagate).

## Guides

- [docs/data-requirements.md](docs/data-requirements.md) - What a `dataRequirements` entry declares, when to use one, and its fields.
- [docs/custom-loaders.md](docs/custom-loaders.md) - Registering a new data source: config, serializer, loader, and cache behavior.
- [docs/entity-id-guard-example.md](docs/entity-id-guard-example.md) - Worked example: guarding a `PropertyReference` entity id and wrapping a throwing collaborator.
- [docs/introspection.md](docs/introspection.md) - The Admin API surface clients read to discover available sources and their config keys.

## Extension Point

1. Extend `AbstractContentDataLoader`, implement `getRequirementType()` returning your source identifier
2. Annotate with `@extends AbstractContentDataLoader<YourStruct>` — the default `producibleTypes()`/`resolveProducedType()` derive the produced type from it; a missing or unresolvable annotation fails the build (see Schema/)
3. Create config class extending `AbstractContentDataLoaderConfig` with matching serializer
4. Override `configSpecification()` when the config serializer accepts keys; it declares the loader's config contract for the derived completion residue
5. Read every input off the `LoaderInputs` argument, defaults included: [A loader consumes typed inputs resolved from its own declared specification](../../docs/principles/data-loading.md#a-loader-consumes-typed-inputs-resolved-from-its-own-declared-specification)
6. Tag with `content_system.data_loader` in the owning domain's DI — service locator uses `getRequirementType()` as key
7. Return `ContentDataLoaderResult` with appropriate cache info and degrade per [A loader degrades on a named domain outcome and lets every other fault propagate](../../docs/principles/data-loading.md#a-loader-degrades-on-a-named-domain-outcome-and-lets-every-other-fault-propagate)

Fixed-type loaders need no override: `resolveProducedType()` returns the derived type ignoring config.

Wildcard loaders that serve multiple concrete types (e.g., the generic `entity`/`entity_collection` loaders) override both `producibleTypes()` and `resolveProducedType()` to enumerate the live definition registry: one capability per registered entity (the sales-channel class where a sales-channel definition exists, otherwise the base class), each carrying the `configTemplate` (`['entity' => <name>]`) needed to produce it; each also declares a `configSpecification()` requiring `entity` and `property`, so the derived residual config key is `property`. Enumeration skips definitions that have no addressable type: `MappingEntityDefinition`s, plus the `entity` loader skips an `ArrayEntity` entity class and the `entity_collection` loader skips a bare `EntityCollection` collection class. `resolveProducedType()` throws `ContentSystemException::unknownLoaderEntity` when the configured entity name is not registered.

## Build-time validation

`ContentSystemDataLoaderCompilerPass` fails the container build on each case below.

- A tagged service whose class does not extend `AbstractContentDataLoader` (`taggedServiceHasWrongType`) or is PHP-abstract (`dataLoaderClassIsAbstract`). Both are checked before any specification is read.
- A missing or unresolvable `@extends AbstractContentDataLoader<T>` annotation, found by a dry run of `extendsDescriptor()`.
- A source named `loader` or `config`, or two loaders declaring the same source.
- A source with no registered `content_system.config_serializer` (`dataLoaderSourceWithoutConfigSerializer`). This is the only serializer check: the agreement between the required keys of `configSpecification()` and the requirements of the serializer's `decode()` is not verified at build time.
- A dry run of `configSpecification()` on an instance built without the constructor, so the specification cannot depend on injected state. It rejects a duplicate key, a key named `loader` or `config`, a type outside `ConfigKeySpecification::TYPES`, a non-string type on a `propertyReference` or `entityName` key, an incoherent default (including a default on a required key), a `referencedType` outside `ConfigKeySpecification::REFERENCED_TYPES`, a `referencedType` other than `string` on a non-`propertyReference` key and an invalid `mergesInto`.
- `mergesInto` is invalid on a non-`propertyReference` key, on a key whose `referencedType` is not `list<string>`, as a self-merge, on a target that is undeclared or not a literal `list<string>` key, and on a target a second merger key already claims.
