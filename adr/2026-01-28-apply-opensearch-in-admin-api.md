---
title: Apply OpenSearch globally for admin-api
date: 2026-01-28
area: inventory
tags: [admin-api, search, opensearch, admin-search, inventory, performance]
---

# Apply OpenSearch globally

## Context

The administration already indexes entities in OpenSearch to power the global search, yet detail listings, filters, and component-level lookups (e.g. `sw-search-bar`, `sw-entity-single-select`, `sw-data-grid`) still rely on classic MySQL queries. When a merchant clicks "Show all matching results" the experience drops back to slow SQL queries because the current implementation does not support the listing, defeating the purpose of keeping an admin index up to date.
As catalog sizes continue to grow, this discrepancy adds latency, produces inconsistent search quality, and increases load on the primary database even though the data is already available in OpenSearch.

We therefore need an approach that allows administration modules/Admin API searches to reuse the OpenSearch index wherever supported while still keeping a safe fallback path for definitions that are not indexed.

## Decision

- Introduce `Shopware\Elasticsearch\Admin\AdminElasticsearchEntitySearcher` to decorate the DAL `EntitySearcherInterface`. Whenever the `AdminSearchRegistry` signals that an entity supports OpenSearch and the request context opts in, the decorator forwards the criteria to OpenSearch instead of querying MySQL. Unsupported entities continue to use the previous MySQL behaviour automatically.
  
### Conditions to forward a search request to opensearch

```php
  function allowAdminEsSearch(EntityDefinition $definition, Context $context, Criteria $criteria): bool
  {
      if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
        return false; // the feature flag is turning off
      }

      if (!$this->helper->getEnabled()) {
        return false; // admin es search is turning off
      }

      if (!$context->getSource() instanceof AdminApiSource) {
          return false; // only Admin API requests can use admin ES
      }

      if (!$criteria->getTerm()) {
          return false; // debatable? less performance gains when not search by term but more traffic to opensearch server
      }

      if (!empty($criteria->getIds())) {
          return false; // explicit ID filters stay on SQL
      }

      if (!$this->registry->hasIndexer($definition->getEntityName())) {
          return false; // entity lacks an admin indexer
      }

      if (empty($criteria->getAllFields())) {
          return true; // no filters -> safe to use ES
      }

      $indexer = $this->registry->getIndexer($definition->getEntityName());

      // use opensearch if all querying fields are supported
      return empty(array_diff(
          $criteria->getAllFields(),
          $indexer->getSupportedSearchFields()
      ));
  }
```

In practice, any Admin API search without explicit ID or term filters whose filters or sorts exist in the indexer's supported field list automatically hits OpenSearch (e.g. product listings filtering by `active`, `manufacturerId`, or `stock`), while every other query falls back to the decorated MySQL searcher.

- Keep the functionality behind the explicit feature flag `ENABLE_OPENSEARCH_FOR_ADMIN_API`. Only when the feature flag is active and admin OpenSearch itself is configured/enabled do we forward DAL searches to OpenSearch. This lets us roll out the change gradually, collect feedback, and still unblock projects that need the old behaviour.
- Extend the admin indexes with the fields that are actually used for searching or filtering via standard Shopware criteria. To keep index size and performance in check we only add the commonly used admin fields and store them as keyword fields. Projects that require additional fields must extend the mapping and indexing on their own.

For example, `src/Elasticsearch/Admin/Indexer/ProductAdminSearchIndexer.php` defines the following mapping overrides:

```php
  public function mapping(array $mapping): array
  {
      $override = [
          'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
          'available' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
          'releaseDate' => ElasticsearchFieldBuilder::datetime(),
          'categories' => ElasticsearchFieldBuilder::nested([
              'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
          ]),
          // ...
      ];

      $mapping['properties'] ??= [];
      $mapping['properties'] = array_merge($mapping['properties'], $override);

      return $mapping;
  }
```

The same indexer exposes the corresponding fetch logic that selects the fields from MySQL and structures them before they are sent to OpenSearch:

```php
  public function fetch(array $ids): array
  {
      $baseSql = <<<'SQL'
          SELECT LOWER(HEX(product.id)) as id,
             GROUP_CONCAT(DISTINCT translation.name SEPARATOR " ") as name,
             product.product_number as productNumber,
             product.stock as stock,
             IFNULL(product.release_date, parent.release_date) AS releaseDate,
             #visibilities#
          FROM product
            LEFT JOIN product parent ON (product.parent_id = parent.id AND parent.version_id = :versionId)
            LEFT JOIN product_visibility ON (...)
          WHERE product.id IN (:ids)
          GROUP BY product.id
      SQL;

      // hydrate rows into the structure expected by AdminSearcher
  }
```

External extensions that require more fields should decorate the concrete indexer service (e.g. `Shopware\\Elasticsearch\\Admin\\Indexer\\ProductAdminSearchIndexer`) and append their own mapping/fetch logic:

```php
  class CustomProductAdminSearchIndexer
  {
      public function __construct(private readonly ProductAdminSearchIndexer $inner) {}

      public function mapping(array $mapping): array
      {
          $mapping = $this->inner->mapping($mapping);
          $mapping['properties']['fooField'] = AbstractElasticsearchDefinition::KEYWORD_FIELD;

          return $mapping;
      }

      public function fetch(array $ids): array
      {
          $fooFieldsData = $this->loadFooFields($ids)
          $data = $this->inner->fetch($ids);
          foreach ($data as &$row) {
              $row['fooField'] = $fooFieldsData[$row['id'])];
          }

          return $data;
      }
  }
```

The decorator is then registered via Symfony service decoration so that it wraps the core indexer without modifying Shopware code.

### Troubleshooting

- The search result from `\Shopware\Elasticsearch\Admin\AdminElasticsearchEntitySearcher::search` is tagged with the state `loaded-by-opensearch`. When the Admin API returns JSON, this state is exposed inside the response `meta`, e.g.

  ```json
  {
      "data": [ /* entities */ ],
      "meta": {
          "loaded-by-opensearch": true,
          "total": 200
      }
  }
  ```

With this metadata we can immediately see if a slow request bypassed OpenSearch (flag missing or false) and focus our diagnostics accordingly.

## Consequences

- When `ENABLE_OPENSEARCH_FOR_ADMIN_API` is on, DAL searches inside supported admin modules reuse the OpenSearch index, which reduces MySQL pressure and significantly boosts the performance of listings/filtering/searching for large catalogs.
- Developers must ensure that any new filters or admin modules declare their requirements in `AdminSearchRegistry` and index the needed fields, otherwise they will still fall back to SQL and lose the performance improvements.
- After enable the flag, shoppers have to reindex the admin ES indexes to make the changes applied by running: `bin/console es:admin:index`, the same applied when you modified a field's mapping or adding/removing more fields
- Index size of the admin indexes will increase because more data is stored, so hostings need to monitor the admin ES index size.
