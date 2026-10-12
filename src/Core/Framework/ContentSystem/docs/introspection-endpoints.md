# Introspection Endpoints

The registries, compiler passes and `/api/_info/` endpoints that publish the content system's own shape to
the Administration, and the runtime assembly each one reads.

## Registries and the schema bridge

- **Element Type Registry**: `Layout/Type/Registry/ContentSystemElementTypeRegistry`
- **Root Source Registry**: `Adapter/RootSourceRegistry` — the single authority over the valid root-source set and their resolution. `knownRootSources()` (entity types + section keys + `none`, excludes `main`), `entityRootSources()` (entity-type subset, backs `content-system-entity-types.json`), `resolve(rootSource, Context): list<ProvidedContext>` (fail-hard on an unknown id), `sourceFor(rootSource)`. The `none` root source is `Adapter/NoneSpecificationSource`
- **Schema / Type-Loader Bridge**: `Schema/ContentSystemDataLoaderMapResolver`, `Schema/ContentSystemDataLoaderMap`, `Schema/ContentSystemDataLoaderSchemaGenerator`

## Compiler passes

- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentSystemDataLoaderCompilerPass` — build-time validation of every `content_system.data_loader`-tagged service: its class shape, its `@extends` annotation, and a dry-run of its `configSpecification()` on an instance built without the constructor (the runtime resolver assembles the type map). Full rejection catalogue: [Hydration/DataLoader/AGENTS.md](../Hydration/DataLoader/AGENTS.md)
- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentLayoutAssignableCompilerPass` — collects the assignable entity-type id list at build time and bakes it into `Adapter/RootSourceRegistry` (`$entityTypes` argument)

## Endpoints

- **Element Type API**: `GET /api/_info/content-system-element-types.json` (registered in `InfoController`); each type entry folds in its `bindingSpecifications` and its `storageSchema` (`Layout/Type/StoredSchemaResolver`), and the response carries one top-level `styleOptions` catalog
- **Style Option API**: `GET /api/_info/content-system-style-options.json` (`InfoController::getContentSystemStyleOptions()`), plus a folded `styleOptions` key on `content-system-element-types.json`. Wire schema: `style` on the StoreApi `ContentElement` (omitted when empty)
- Schema API endpoint: `GET /api/_info/content-system-data-loaders.json` (registered in `InfoController`)
- Schema API endpoint: `GET /api/_info/content-system-entity-types.json` (registered in `InfoController`)
- Schema API endpoint: `GET /api/_info/content-system-root-sources.json` (registered in `InfoController`)

## What the endpoints derive from

- Data loader type introspection: `ContentSystemDataLoaderMapResolver` assembles the source→capability map at runtime from each loader's `producibleTypes()` (memoized per kernel runtime); `ContentSystemDataLoaderCompilerPass` only validates at build time (dry-runs `AbstractContentDataLoader::extendsDescriptor()`, so loaders MUST have `@extends AbstractContentDataLoader<T>` PHPDoc, plus the `configSpecification()` dry-run under **Compiler Pass** above); wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()`/`resolveProducedType()` to enumerate the live registry (so plugin/app/custom entities appear on the next kernel boot without a container rebuild)
- `ContentSystemDataLoaderMap` lookups are subtype-aware (`is_a`): `getSourcesFor($class)` returns every source whose `producedType` is the class or a subclass; `capabilityFor($source, $class)` returns that source's first matching `LoaderTypeCapability`, or `null` when the source cannot produce the class
- Entity type introspection: `ContentLayoutAssignableCompilerPass` introspects `content_system.entity_specification_source` tagged services for `AbstractContentLayoutAssignableDefinition` arguments — the entity-type id list is baked into `Adapter/RootSourceRegistry` at build time, and `InfoController::contentSystemEntityTypes()` returns `RootSourceRegistry::entityRootSources()`
- Type spec `properties` = hydrated output schema; the storage schema is a separate fold on the element-types endpoint, `storageSchema`, derived per type by `Layout/Type/StoredSchemaResolver` (stored keys, three kinds, precedence `property` > `resolvedByStorage` > `config`). The property key links type spec → dataRequirements → acceptsContext → the key `Rendering/RenderedElementFactory` writes the resolved value under on the rendered element
