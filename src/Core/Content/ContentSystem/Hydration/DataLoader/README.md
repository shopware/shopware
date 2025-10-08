# DataLoader

Data fetching for content elements. Dispatches to loader implementations based on data requirement source.

## Key Classes

- `ContentDataLoaderInterface` - Loader contract
- `DataLoaderProvider` - Dispatcher, selects loader by source identifier
- `EntityLoader` - Single entity loading via EntityRepository
- `EntityCollectionLoader` - Multiple entities loading via EntityRepository
- `ProductListingDataLoader` - Product listing queries

## Loader Selection

Elements declare DataRequirements with `source` identifier (e.g., "entity", "product_listing"). DataLoaderProvider dispatches to registered loader by source. Loaders tagged in DI with source identifier.

Extensions implement ContentDataLoaderInterface, tag with source identifier, handle specific data fetching.

## Built-in Loaders

Core provides these loaders:

**EntityLoader** (`source: "entity"`)
- Loads single entity by ID from element property
- Config: See `@phpstan-type EntityLoaderConfig` in EntityLoader class

**EntityCollectionLoader** (`source: "entity_collection"`)
- Loads multiple entities by IDs from element property as an array
- Config: See `@phpstan-type EntityCollectionLoaderConfig` in EntityCollectionLoader class

**ProductListingDataLoader** (`source: "product_listing"`)
- Product listings with filters, sorting, pagination
- Config: See `@phpstan-type ProductListingLoaderConfig` in ProductListingDataLoader class

## Loader Interface

```php
load(
  ContentElement $element,
  DataRequirement $requirement,
  SalesChannelContext $context
): mixed
```

Loaders receive element (for context), requirement (what to load), context (sales channel). Return data or null. Hydrator stores result in element properties by requirement key.

## Extension Point

Extensions implement ContentDataLoaderInterface for custom data sources (API calls, calculations, etc.). Register via DI tag with unique source identifier. Elements declare requirements with that source.
