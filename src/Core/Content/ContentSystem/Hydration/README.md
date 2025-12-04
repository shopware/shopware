# Hydration

Loads data and resolves context for content elements. Processes tree recursively: fetch data per element's requirements, distribute context from providers to consumers.

## Key Class

- `ContentElementHydrator` - Entry point, orchestrates loading + context resolution

## Two-Phase Process

1. **Data Loading**: Walk tree, fetch data per element's DataRequirements (DataLoader/)
2. **Context Resolution**: Distribute context from providers to consumers down tree (DataContext/)

Data loading happens depth-first during tree traversal. Context resolution happens after all data loaded, in separate pass.

## Data Loading

For each element with `dataRequirements`:
- DataLoaderProvider selects loader by requirement source
- Loader fetches data and returns `ContentDataLoaderResult` with cache info
- Data stored in element properties by requirement key
- Cache tags accumulated in `RenderingCacheContext`

Elements declare what they need via DataRequirements. Hydrator satisfies requirements. Elements don't know how data is loaded.

## Context Resolution

After loading, DataContextResolver walks tree:
- Elements with ContextProvider expose data as context
- Context flows to direct children only
- Elements with ContextConsumer receive context in properties

Context scoped to immediate children only - provider only affects direct children. Deeper descendants require explicit re-providing. See DataContext/ for distribution algorithm.

## Cache Integration

`RenderingCacheContext` tracks cache state throughout hydration:

- Routes create context and pass to `ContentElementHydrator::hydrate()`
- Each data loader returns `ContentDataLoaderResult` with cache information
- Cache-aware results add tags to context; uncacheable results disable caching
- After hydration, `CacheFinalizer` applies final state to HTTP response

If any loader returns uncacheable data, the entire page becomes uncacheable. See Cache/ directory for implementation.

## DataRequirement Structure

Elements declare data needs via DataRequirement objects. Each requirement contains:

- `key`: Property key where loaded data is stored
- `source`: Loader identifier ("entity", "product_listing", custom)
- `config`: Loader-specific configuration (AbstractContentDataLoaderConfig, serialized to/from array)

All data requirements invoke their respective data loaders during hydration. The `source` field identifies which loader to use.

### Loader Sources

Available loaders:
- `entity` - EntityLoader (single entity)
- `entity_collection` - EntityCollectionLoader (multiple entities)
- `product_listing` - ProductListingDataLoader (product listings)

See DataLoader/ for loader implementations and config classes.

## Process Order

```
ContentElementHydrator::hydrate()
  → hydrateElement() recursively
    → load data per DataRequirements
  → DataContextResolver::resolve()
    → distribute context down tree
```

Must load data before context resolution because providers may expose loaded data as context.

## Subdirectories

- DataLoader/: Data fetching (AbstractContentDataLoader implementations)
- DataContext/: Context distribution (DataContextResolver)
