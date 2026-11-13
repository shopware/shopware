# Data loading

Data loading turns an element's data requirements into loaded values and publishes the loader configuration that an authoring client needs.

## A loader degrades on a named domain outcome and lets every other fault propagate

Each loader degrades gracefully behind a narrow catch. The loader wraps the collaborator call it delegates to and catches `ShopwareHttpException` there. On that catch, it returns `ContentDataLoaderResult::notFound()`. Every other fault, an infrastructure fault or a type error among them, propagates. Each loader's own call chain determines what the loader wraps and whether its degradation is whole or partial. A loader guards an entity id that it reads from a `PropertyReference` with `Uuid::isValid()`, after its lowercasing or alias resolution and before first use. When the guard fails, the loader takes its own null-input path. A value that the loader never dereferences as an id gets no guard.

Why: A collaborator throws for ordinary domain reasons, such as a deleted category. When an exception reaches `StorefrontController::loadContentPage()`, that controller catches every `\Exception` and renders the page without its layout. A blanket catch in a loader turns a database outage or a type error into an empty element with no error report. The stored map can hold an unsubstituted placeholder such as `{{productId}}`. `LoaderInputResolver` only type-checks it as a string. An unguarded placeholder reaches an id parser and becomes an HTTP 400 from deep in the platform. `Uuid::VALID_PATTERN` accepts lowercase only. `Uuid::fromHexToBytes()` accepts uppercase hex. A guard on the raw value therefore rejects an uppercase configured id that would work.

Decided against: One central wrapper for all loaders. It cannot give each loader its own code after the call, its own whole-or-partial handling and its own catch scope. An enumerated union of exception classes in the catch, because the reachable set is open. `ProductStreamBuilder` throws `EntityNotFoundException` and `NoFilterException` out of `ProductListingRoute` and `ProductCrossSellingRoute`. A decorator can also rewrap a named class into an unnamed one. `AppScriptProductPriceCalculator` hands app-script throwables to `ScriptExecutor`, which rethrows `ScriptExecutionFailedException`.

Exceptions: `EntityCollectionLoader` maps an absent or empty id list to an empty collection, not to not-found.

In code:

- Every built-in loader, `EntityLoader::load()` among them, contains `catch (ShopwareHttpException)`.
- Every built-in loader that reads an id, `EntityLoader::load()` among them, runs the `Uuid::isValid()` guard after its lowercasing or alias resolution.
- See [Hydration/DataLoader/README.md](../../Hydration/DataLoader/README.md#degradation-boundary).

```text
repository throws   UuidException::invalidUuid('not-a-uuid'), a ShopwareHttpException
load() returns      ContentDataLoaderResult::notFound(), data null
repository throws   \TypeError('Argument #1 ($criteria) must be of type Criteria, null given')
load() throws       the same \TypeError instance, unmodified
```

## A loader consumes typed inputs resolved from its own declared specification

Loader configuration follows the principle "parse, do not validate". A loader declares its configuration in `configSpecification()`. It reads every input off the `LoaderInputs` that `LoaderInputResolver` resolves from that declaration, never off the element or a stored value. A static default lives in the key's `ConfigKeySpecification`, never in `load()`. `LoaderInputResolver` applies that default centrally. `LoaderInputResolver` owns the presence and type guards. The loader owns the check for domain emptiness, because an empty string such as an empty `activeId` is a resolved value.

The required keys that a loader declares equal the requirements of the `decode()` method of its config serializer.

Why: The introspection schema lists only declared keys and defaults. A key read off the element, or a default written in `load()`, never reaches it. No build-time check compares the required keys with `decode()`: `ContentSystemDataLoaderCompilerPass::validateConfigSerializerCoverage()` asserts only that a serializer is registered for the source.

Exceptions: A loader declares a sales-channel fallback, such as navigation depth, without a default. The loader applies the fallback in `load()`.

In code:

- `AbstractContentDataLoader::load()` receives the resolved `LoaderInputs`.
- `LoaderInputResolver::resolve()` resolves the inputs and applies each static default.
- `ContentSystemDataLoaderCompilerPass` dry-runs every `configSpecification()` at build time.
- A per-loader test guards the agreement of required keys: `EntityCollectionLoaderTest::testConfigSpecificationRequiredKeysMatchSerializerRequiredKeys` is the pattern.
- See [custom-loaders.md](../../Hydration/DataLoader/docs/custom-loaders.md).

## No request selects what a loader loads

At bind time, `resolvedBy` becomes a stored data requirement, and a config reference names a fixed, gate-validated stored property. An author may put a `{{placeholder}}` token into a `ConfigKeyKind::Literal` loader-config value. `StoredTreePreparer` substitutes that token in `RenderingMode::FULL`, as it substitutes a property string. A substituted value comes only from the page's route-derived entity id and from the query parameters that the specification source declares. No request value overrides a server-derived placeholder. No other render-time step substitutes or overrides any part of the loader configuration. No request selects an entity type, a property name, or a config key that the author did not mark with a token.

Query parameters excluded from the HTTP cache key never become placeholder values.

Why: A request value that reaches a loader through an unmarked key or an undeclared parameter picks a target no write-time gate checked. Limiting where a request value may enter is cheaper than guarding each case.

Exceptions: `StoredTreePreparer` removes a `Literal` loader-config key whose whole value is one unresolved `{{token}}`. The key then resolves as absent and takes its declared default where it declares one. An unresolved ordinary placeholder embedded in other text stays literal; a whole-property unresolved placeholder becomes null. Placeholder keys beginning with `map:` after leading whitespace are reserved, so request values cannot replace mapping syntax. Mapping intent is parsed only from authored spans before substitution; see [data mapping](data-mapping.md#only-authored-token-spans-are-expanded).

In code:

- `BindingApplicator::apply()` writes a typed `DataRequirement`.
- `TypeConsistentBindingSpecificationValidator` checks property references.
- `EntityLayoutResolver::resolvePlaceholders()` excludes query parameters configured in `shopware.http_cache.ignored_url_parameters`, so values absent from the cache key cannot vary rendered content.
- `EntityLayoutResolverTest::testCacheIgnoredQueryParametersCannotBecomePlaceholders` pins that exclusion and preserves the original request.
- `StoredTreePreparer` substitutes a token in a property string and in the string value of a `Literal` loader-config key.
- See [applying.md](../../Binding/docs/applying.md).

## An element receives a sales-channel context value only from a loader that returns that value

The module never hands the `SalesChannelContext` to an element. A loader passes the request's `SalesChannelContext` to every collaborator that accepts one, so prices, translations and availability follow the request's currency, language and rules. An element that needs a context value as data declares a data requirement whose loader returns that value, never the context object and never a customer entity. The [root context](README.md#glossary) carries the root entity's page data only. An element component reads no Twig global `context`, also not as a prop default.

Why: An ambient context invites reads across module boundaries and widens the Store API response, where the `ApiAware` flag protects entity fields only. A headless client has no Twig global, so an element that reads one renders differently there.

In code:

- Every built-in loader passes the `SalesChannelContext` to its Store API route, sales-channel repository or service.
- `EntityLoader` and `EntityCollectionLoader` pass `$context->getContext()` only to the plain repository of an entity without a sales-channel definition.
- `LoaderInputResolver::resolve()` dereferences element properties only.
- `RootContextMapper::map()` builds the root context from the assignable definition's page data requirements.
- `TypeConsistentBindingSpecificationValidator` rejects a `resolves` entry in the `context` form. `TypeConsistentBindingSpecificationValidatorTest::testResolvesEntryContextFormIsViolation` pins the rejection.
- The unit tests of `ProductReviewDataLoader`, `CrossSellingDataLoader`, `ProductListingDataLoader`, `NavigationDataLoader` and `ServiceMenuDataLoader` pin the context their collaborator receives.
- See [Hydration/DataLoader/README.md](../../Hydration/DataLoader/README.md).

## An authoring client reads loader configuration through introspection and never parses it

The introspection endpoints exist so that an authoring client never parses loader configuration or hardcodes root context. The client reads config keys, capabilities, stored keys and each root source's context from the introspection endpoints. A branched loader configuration publishes one flat union, which loses precision but still spares the client from parsing loader configuration. The published union marks a branched key as required only when every branch that declares the key requires it.

Why: A client parsing loader configuration breaks when any loader changes its grammar.

In code:

- `InfoController::contentSystemDataLoaders()` serves the config keys and capabilities that `ContentSystemDataLoaderSchemaGenerator::getSchema()` builds.
- `contentSystemRootSources()` serves each root source's context.
- `StoredSchemaResolver::resolve()` resolves the stored keys of an element type.
- See [introspection.md](../../Hydration/DataLoader/docs/introspection.md).

## Also true by construction

- `StoredTreePreparer` substitutes a placeholder token in one pass at the property's own level, in [`RenderingMode::FULL`](README.md#terms-that-are-easy-to-confuse) only. An embedded unresolved ordinary token stays literal; a standalone unresolved property token becomes null. Placeholder values are otherwise made available to page loaders on the [virtual root](README.md#glossary): [pipeline-steps.md](../pipeline-steps.md)
- A loader-resolved value dedups by its source, configuration and value identity, without a value comparison. The DAL keeps no identity map, so two loads of one row return two instances that no value-level test can collapse. `ElementDataResolver` creates that identity at the load, because the resolved `LoaderInputs` do not outlive the call. `producedFingerprint` describes the value that the loader returned, not the value that the response finally carries. Any other object dedups by instance identity, a non-object by value equality, and every explicit null shares one `data` map entry: [Output/README.md](../../Output/README.md)
