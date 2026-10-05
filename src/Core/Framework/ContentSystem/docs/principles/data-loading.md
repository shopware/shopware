# Data loading

Data loading turns an element's data requirements into loaded values and publishes the loader configuration that an authoring client needs.

## A loader degrades on a named domain outcome and lets every other fault propagate

Each loader degrades gracefully behind a narrow catch. The loader wraps the collaborator call it delegates to and catches `ShopwareHttpException` there. On that catch, it returns `ContentDataLoaderResult::notFound()`. Every other fault, an infrastructure fault or a type error among them, propagates. Each loader's own call chain determines what the loader wraps and whether its degradation is whole or partial.

Why: A collaborator throws for ordinary domain reasons, such as a deleted category. When an exception reaches `StorefrontController::loadContentPage()`, that controller catches every `\Exception` and renders the page without its layout. A blanket catch in a loader turns a database outage or a type error into an empty element with no error report.

Not chosen: A loader that throws on every failure, which turns one failing element into a page without its layout. Catching everything in a loader. One central wrapper for all loaders, which cannot give each loader its own code after the call, its own whole-or-partial handling and its own catch scope.

Exceptions: `EntityCollectionLoader` maps an absent or empty id list to an empty collection, not to not-found.

In code:

- Every built-in loader, `EntityLoader::load()` among them, contains `catch (ShopwareHttpException)`.
- See [Hydration/DataLoader/README.md](../../Hydration/DataLoader/README.md#degradation-boundary).

```text
repository throws   UuidException::invalidUuid('not-a-uuid'), a ShopwareHttpException
load() returns      ContentDataLoaderResult::notFound(), data null
repository throws   \TypeError('Argument #1 ($criteria) must be of type Criteria, null given')
load() throws       the same \TypeError instance, unmodified
```

## A loader consumes typed inputs resolved from its own declared specification

Loader configuration follows the principle "parse, do not validate". A loader declares its configuration in `configSpecification()`. It reads every input off the `LoaderInputs` that `LoaderInputResolver` resolves from that declaration, never off the element or a stored value. A static default lives in the key's `ConfigKeySpecification`, never in `load()`. `LoaderInputResolver` applies that default centrally. `LoaderInputResolver` owns the presence and type guards. The loader owns the check for domain emptiness.

Why: The introspection schema lists only declared keys and defaults. A key read off the element, or a default written in `load()`, never reaches it.

Exceptions: A loader declares a sales-channel fallback, such as navigation depth, without a default. The loader applies the fallback in `load()`.

In code:

- `AbstractContentDataLoader::load()` receives the resolved `LoaderInputs`.
- `LoaderInputResolver::resolve()` resolves the inputs and applies each static default.
- `ContentSystemDataLoaderCompilerPass` dry-runs every `configSpecification()` at build time.
- See [custom-loaders.md](../../Hydration/DataLoader/docs/custom-loaders.md).

## Loader configuration takes its final form at write time, and no request selects what loads

No render-time step substitutes or overrides any part of the loader configuration. At bind time, `resolvedBy` becomes a stored data requirement, and a config reference names a fixed, gate-validated stored property. A per-request value must reach loader inputs only through a declared property. No request parameter may select or override which entity a loader loads or which property the loader reads.

Why: A request that picks a loader's target loads something no write-time gate checked. Removing that ability is cheaper than guarding each case.

In code:

- `BindingApplicator::apply()` writes a typed `DataRequirement`.
- `TypeConsistentBindingSpecificationValidator` checks property references.
- `StoredTreePreparer` substitutes property strings only.
- See [applying.md](../../Binding/docs/applying.md).

## An element receives a sales-channel context value only from a loader that returns that value

The module never hands the `SalesChannelContext` to an element. Every loader loads with the context, so prices, translations and availability already follow the request's currency, language and rules. An element that needs a context value as data declares a data requirement whose loader returns that value, never the context object and never a customer entity. The root context carries the root entity's page data only. A `resolves` entry in the `context` form stays rejected. An element component reads no Twig global `context`, also not as a prop default.

Why: An ambient context invites reads across module boundaries and widens the Store API response, where the `ApiAware` flag protects entity fields only. A headless client has no Twig global, so an element that reads one renders differently there. A headless client reads its own context from `/store-api/context`.

In code:

- `LoaderInputResolver::resolve()` dereferences element properties only.
- `RootContextMapper::map()` builds the root context from the assignable definition's page data requirements.
- `TypeConsistentBindingSpecificationValidator` rejects a `resolves` entry in the `context` form.
- No test pins the rule.
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

- `StoredTreePreparer` substitutes a placeholder token in one pass at the property's own level, in full rendering only. An unresolved token stays literal, and placeholder values become properties only on the page-level virtual root: [pipeline-steps.md](../pipeline-steps.md)
- A loader-resolved value dedups by its source, configuration and value identity. Any other object dedups by instance identity, a non-object by value equality, and every explicit null shares one ref: [Output/README.md](../../Output/README.md)
