# Introspection Endpoints

## Registries and the schema bridge

- **Element Type Registry**: `Layout/Type/Registry/ContentSystemElementTypeRegistry`
- **Root Source Registry**: `Adapter/RootSourceRegistry` — the single authority over the valid root-source set and their resolution. `knownRootSources()` (entity types + section keys + `none`, excludes `main`), `entityRootSources()` (entity-type subset, backs `content-system-entity-types.json`), `resolve(rootSource, Context): list<ProvidedContext>` (fail-hard on an unknown id), `sourceFor(rootSource)`. The `none` root source is `Adapter/NoneSpecificationSource`
- **Schema / Type-Loader Bridge**: `Schema/ContentSystemDataLoaderMapResolver`, `Schema/ContentSystemDataLoaderMap`, `Schema/ContentSystemDataLoaderSchemaGenerator`

## Compiler passes

- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentSystemDataLoaderCompilerPass`: build-time validation of every `content_system.data_loader`-tagged service. It validates the class shape and the `@extends` annotation, and dry-runs `configSpecification()` on an instance built without the constructor. The runtime resolver assembles the type map. Full rejection catalogue: [Hydration/DataLoader/README.md](../Hydration/DataLoader/README.md#build-time-validation)
- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentLayoutAssignableCompilerPass` — collects the assignable entity-type id list at build time and bakes it into `Adapter/RootSourceRegistry` (`$entityTypes` argument)

## Endpoints

- **Element Type API**: `GET /api/_info/content-system-element-types.json`: schema in [Layout/Type/docs/introspection.md](../Layout/Type/docs/introspection.md)
- **Style Option API**: `GET /api/_info/content-system-style-options.json`: schema in [Layout/Element/Style/docs/introspection.md](../Layout/Element/Style/docs/introspection.md)
- `GET /api/_info/content-system-data-loaders.json`: schema in [Hydration/DataLoader/docs/introspection.md](../Hydration/DataLoader/docs/introspection.md)
- `GET /api/_info/content-system-entity-types.json`: schema in [Adapter/docs/introspection.md](../Adapter/docs/introspection.md)
- `GET /api/_info/content-system-root-sources.json`

## What the endpoints derive from

- Data loader type introspection: `ContentSystemDataLoaderMapResolver` assembles the source→capability map at runtime from each loader's `producibleTypes()` (memoized per kernel runtime). `ContentSystemDataLoaderCompilerPass` only validates at build time (dry-runs `AbstractContentDataLoader::extendsDescriptor()`, plus the `configSpecification()` dry-run under **Compiler Pass** above). Wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()`/`resolveProducedType()` to enumerate the live registry, so plugin/app/custom entities appear on the next kernel boot without a container rebuild
- `ContentSystemDataLoaderMap` lookups are subtype-aware (`is_a`): a source matches when its `producedType` is the class or a subclass
- Entity type introspection: `ContentLayoutAssignableCompilerPass` introspects `content_system.entity_specification_source` tagged services for `AbstractContentLayoutAssignableDefinition` arguments
- Type-spec `properties` and the `storageSchema` fold: [Layout/Type/docs/introspection.md](../Layout/Type/docs/introspection.md)
