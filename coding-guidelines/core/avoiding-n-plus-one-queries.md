# Avoiding N+1 queries

An N+1 query problem is a query that is executed once per record instead of once per request: the number of
database round-trips grows with the number of processed records. It is the single most common performance defect in
indexers, subscribers and import/export code, because it is invisible in a test with three fixtures and only shows
up on a shop with 100,000 products.

The `Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoQueryInLoopRule` PHPStan rule reports queries that are
executed inside a loop body under the `shopware.queryInLoop` identifier.

## Load data for all records before the loop

```php
// bad: one SELECT per parent
foreach ($parentIds as $parentId) {
    $accessors = $this->connection->fetchAllKeyValue(
        'SELECT id, cheapest_price_accessor FROM product WHERE parent_id = :id',
        ['id' => Uuid::fromHexToBytes($parentId)]
    );
    // ...
}
```

```php
// good: one SELECT for all parents, grouped in PHP
$rows = $this->connection->fetchAllAssociative(
    'SELECT LOWER(HEX(parent_id)) AS parentId, id, cheapest_price_accessor AS accessor
     FROM product WHERE parent_id IN (:ids)',
    ['ids' => Uuid::fromHexToBytesList($parentIds)],
    ['ids' => ArrayParameterType::BINARY]
);

$accessorsByParent = [];
foreach ($rows as $row) {
    $accessorsByParent[$row['parentId']][$row['id']] = $row['accessor'];
}

foreach ($parentIds as $parentId) {
    $accessors = $accessorsByParent[$parentId] ?? [];
    // ...
}
```

The same applies to the DAL: `search()`, `searchIds()` and `aggregate()` take a `Criteria` that can cover all ids
at once, and `create()`, `update()`, `upsert()` and `delete()` take a payload with many records.

## Loops the rule accepts

A query in a loop body is fine when a single iteration already processes a whole set of records. The rule does not
report:

* iterating a chunked source, e.g. `foreach (array_chunk($ids, 250) as $chunk)`,
* a `foreach` that binds a **list** per iteration, e.g. `foreach ($chunks as $chunk)` or
  `foreach ($idsByLanguage as $languageId => $ids)` — the query covers the whole list. A record represented as a
  map, e.g. `foreach ($rows as $row)`, is not a batch and is still reported,
* pagination loops driven by an `IterableQuery`, `RepositoryIterator` or `SalesChannelRepositoryIterator`, e.g.
  `while ($ids = $iterator->fetch())`,
* `while`/`do-while` loops that drain a source: a worklist consumed until it is empty
  (`while ($pendingIds !== [])`), or a loop in a function that paginates its query with `LIMIT :limit`,
  `setLimit()` or `setOffset()`,
* loops with a statically fixed iteration count, e.g. `foreach (['de-DE', 'en-GB'] as $locale)` or
  `for ($i = 0; $i < 3; ++$i)`.

It also does not report queries that a loop reaches at most once, however many records it processes:

* a query memoised by a null check, e.g. `if ($snippetSets === null) { $snippetSets = $this->connection->fetchAllAssociative(…); }`,
* a query in a block that ends in `throw` or `return`, because that block leaves the loop — for example the cleanup
  query of an error handler that rethrows. Note that `continue` does not end a block in this sense: it starts the
  next iteration, so a query before it still runs per record.

## Suppressing a report

Some loops process a fixed set of groups rather than records and cannot be collapsed into a single query. Most of
them are recognised by the list-binding rule above, but not when the batch is not visible in the types — for
example when the grouped ids arrive as a `GROUP_CONCAT` string. Suppress those explicitly and state why, so the
next reader does not have to re-derive it:

```php
foreach ($this->getOrdersLanguageId($ids, $versionId, $connection) as ['language_id' => $languageId, 'ids' => $orderIds]) {
    $criteria = OrderDocumentCriteriaFactory::create(explode(',', (string) $orderIds), ...);

    // @phpstan-ignore shopware.queryInLoop (one query per language, each covering all of that language's orders)
    $orders = $this->orderRepository->search($criteria, $context)->getEntities();
}
```
