[titleEn]: <>(Search)
[hash]: <>(article:dal_search)

# Search

The search is one of the most powerful yet fastest modules in Shopware.

## Fields

Every field in an `EntityDefinition` is searchable unless they are marked with the `WriteOnly` flag. This empowers you to use the search for literally everything that's in the storage.
To get an overview of all available fields, you can open the definitions of the defined entities or open the entity schema provided via API.

### Deep Fields

In addition to that, you can get very specific about the fields you are filter or sort on. That means, that you can get products but filter on any related entity. Here are some examples to show you the idea of deep fields:

**Example 1: Get products which manufacturer's name is "shopware AG"**

```php
$criteria->addFilter(
    new EqualsFilter('product.manufacturer.name', 'shopware AG')
);
```

**Example 2: Get categories which product media have an extension "jpg"**

```php
$criteria->addFilter(
    new EqualsFilter('category.products.media.fileExtension', 'jpg')
);
```

**Example 3: Get customers with an order which has been delivered to "Denmark" and the payment method has been PayPal**

```php
$criteria->addFilter(
    new EqualsFilter('customer.orders.deliveries.shippingOrderAddress.country.name', 'Denmark'),
    new EqualsFilter('customer.orders.paymentMethod.name', 'PayPal')
);
```

## Associations

By default your query will only fetch the main entity you are requesting. You can add associations to your criteria object in order to fetch data for that association.

### Example 1: Simple Association

In the example below, this adds the line item entity to be included with the order entity.

```php
$orderCriteria = new Criteria();
$orderCriteria->addFilter(new EqualsFilter('orderNumber', 'SW10001'));
$orderCriteria->addAssociation('lineItems');
```

When looping through the order entities of a collection, this will then return a `OrderLineItemEntity` object than null when you use the method `$orderEntity->getLineItems()`

### Example 2: Nested Association

You can also add nested associations to your criteria object using the same approach.
In the example below, it will add the payment method entity to the transaction entity. 

```php
$orderCriteria->addAssociation('transactions.paymentMethod');
```

When access the transaction entity within the order entity, it will return a `PaymentMethodEntity` object than null when you use the method `$transactionEntity->getPaymentMethod()`


## Filter

Filters reduce your results to your needs and will be considered when aggregating data. You can filter on every property of an entity, both via code or API.

| Class name | API name | Description |
|------------|----------|------------------------------------------------------------------|
| EqualsFilter  | term     | Exact match for the given value |
| EqualsAnyFilter | terms    | At least one exact match for a value of the given list |
| ContainsFilter | match    | Before and after wildcard search for the given value |
| RangeFilter | range    | For range compatible fields like numbers or dates |
| ScoreQuery | score    | Only usable for fields with a scoring. Filter on a minimum score |

### Combining filters

| Class name | API name | Description |
|---|---|---|
| MultiFilter | nested | Group multiple filters into one filter and concat them using the `AND` or `OR` operator |
| NotFilter | not | A negated MultiFilter |

### Adding Filters

The base for filters is a Criteria object, which is a definition for your search request. This is your base to start with:

```php
$criteria = new Criteria();

// ...filters...

$results = $this->repository->search($criteria, $context);
```

### EqualsFilter

```php
$criteria->addFilter(
    new EqualsFilter('product.name', 'Dagger')
);
```

- The first parameter `$field` is the field selector to filter on.
- The second parameter `$value` is the value for the exact match on the given field.

### EqualsAnyFilter

```php
$criteria->addFilter(
    new EqualsAnyFilter('product.name', ['Dagger', 'Sword', 'Axe'])
);
```

- The first parameter `$field` is the field selector to filter on.
- The second parameter `$values` is a list of values for a possible exact match on the given field.

### ContainsFilter

```php
$criteria->addFilter(
    new ContainsFilter('product.description', 'iPhone')
);
```

- The first parameter `$field` is the field selector to filter on.
- The second parameter `$value` is the value for the wildcard query. In this case, the filter matches all entries where "iPhone" is anywhere in the description.

### RangeFilter

```php
$criteria->addFilter(
    new RangeFilter('product.stock', ['gt' => 10]),
    new RangeFilter('product.stock', ['gte' => 20]),
    new RangeFilter('product.stock', ['lt' => 30]),
    new RangeFilter('product.stock', ['lte' => 40])
);
```

- The first parameter `$field` is the field selector to filter on.
- The second parameter `$range` is an array containing the comparison. There are four comparisons available:
    - **gt** - The value must be **g**reater **t**han the given value
    - **gte** - The value must be **g**reater **t**han or **e**quals the given value
    - **lt** - The value must be **l**ower **t**han the given value
    - **lte** - The value must be **l**ower **t**han or **e**quals the given value

You can even combine multiple comparisons to define a range between a given value. The example below matches products with a stock between 10 and 20:

```php
$criteria->addFilter(
    new RangeFilter('product.stock', ['gt' => 10, 'lt' => 20]),
);
```

### ScoreQuery

```php
$criteria->addFilter(
    new ScoreQuery(new EqualsFilter('product.description', 'Blue'), 10),
    new ScoreQuery(new EqualsFilter('product.description', 'Red'), 100, 'product.stock'),
);
```

- The first parameter `$query` defines the expression for the score query.
- The second parameter `$score` defines the score which should be used if the expression matches. In case that "Blue" is found in `product.description`, it gets an additional score of 100.
- The third parameter `$scoreField` allows defining a multiplier for the score. For example: In case that "Red" is found in `product.description`, the score of 100 is multiplied with the value of `product.stock`.

### MultiFilter

```php
$criteria->addFilter(new MultiFilter(
    MultiFilter::CONNECTION_OR,
    [
        new EqualsFilter('product.name', 'Dagger'),
        new RangeFilter('product.stock', ['gt' => 10, 'lt' => 20]),
    ]
));
```

The nested query groups multiple queries into one and concat them using the `AND` or `OR` operator.

- The first parameter `$operator` defines the operator for the queries. You can choose between `AND` and `OR`.
- The second parameter `$queries` is a list of additional queries to be grouped.

### NotFilter

```php
$criteria->addFilter(new NotFilter(
    NotFilter::CONNECTION_AND,
    [new EqualsAnyFilter('product.name', ['Sword', 'Axe'])]
));
```

The NotFilter is an equivalent to the MultiFilter with the only difference, that the result of the inner queries is negated.

- The first parameter `$operator` defines the operator for the queries. You can choose between `AND` and `OR`.
- The second parameter `$queries` is a list of additional queries to be grouped and negated.

## Post-Filter

Post-Filters work the same way as filters, but they won't be considered when aggregating data.

### When to use Post-Filters?

A common use-case for post filters is to get only active products, but the total of products should be without any filter active:

Given 20 products with 5 of them are active, your filter would be empty and your post-filter contains a `EqualsFilter` on `product.active`.
You will get the 5 active products but the calculated total count of products will still be 20.

```php
$criteria = new Criteria();
$criteria->addPostFilter(new EqualsFilter('product.active', true));

$results = $this->repository->search($criteria, $context);

echo $results->getTotal(); // 20
echo $results->getEntities()->count(); // 5
```

## Sort

The Criteria object supports to sort entities. You can add multiple sorting rules to the criteria object to define the sorting order.

```php
$criteria->addSorting(
    new FieldSorting('product.name')
);
```

- The first parameter `$field` is the field selector to sort on.
- The second parameter `$direction` is the direction to sort. Available options are:
    - `FieldSorting::ASCENDING` for A-Z sorting
    - `FieldSorting::DESCENDING` for Z-A sorting

## Aggregate

Aggregations are a powerful tool which allows you to gather statistical data about your executed query.

| Class name | API name | Type | Return values | Description |
|-----|---|---|---|---|
| AvgAggregation | avg | singe-value | avg | Average of all numeric values for the specified field |
| CountAggregation | count | single-value | count | Number of records for the specified field |
| MaxAggregation | max | single-value | max | Maximum value for the specified field |
| MinAggregation | min | single-value | min | Minimal value for the specified field |
| StatsAggregation | stats | multi-value | count, avg, sum, min, max | Stats overall numeric values for the specified field |
| SumAggregation | sum | single-value | sum | Sum of all numeric values for the specified field |
| FilterAggregation | filter | none-value |  | Allows to filter the aggregation result |
| EntityAggregation | entity | multi-value | entities | Groups the result for each value of the provided field and fetches the entities for this field |
| TermsAggregation | terms | multi-value | buckets,count | Groups the result for each value of the provided field and fetches the count of affected documents |
| DateHistogramAggregation | histogram | multi-value | buckets,count | Groups the result for each value of the provided field and fetches the count of affected documents. Although allows to provide date interval (day, month, ...) |

### AvgAggregation
```php
$criteria->addAggregation(
    new AvgAggregation('avg-price', 'product.price')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var AvgResult $avg */
$avg = $result->get('avg-price');

$avg->getAvg();
```

### CountAggregation
```php
$criteria->addAggregation(
    new CountAggregation('count-manufacturer', 'product.manufacturerId')
);
$result = $this->repository->aggregate($criteria, $context);

/** @var CountResult $count */
$count = $result->get('count-manufacturer');

$count->getCount();
```

### MaxAggregation
```php
$criteria->addAggregation(
    new MaxAggregation('max-price', 'product.price')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var MaxResult $max */
$max = $result->get('max-price');

$max->getMax();
```

### MinAggregation
```php
$criteria->addAggregation(
    new MinAggregation('min-price', 'product.price')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var MinResult $min */
$min = $result->get('min-price');

$min->getMin();
```


### StatsAggregation
```php
$criteria->addAggregation(
    new StatsAggregation('stats-price', 'product.price')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var StatsResult $stats */
$stats = $result->get('stats-price');

$stats->getMin();
$stats->getMax();
$stats->getAvg();
$stats->getSum();
```

### SumAggregation
```php
$criteria->addAggregation(
    new SumAggregation('sum-price', 'product.price')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var SumResult $sum */
$sum = $result->get('sum-price');

$sum->getSum();
```


### FilterAggregation
```php
$criteria->addAggregation(
    new FilterAggregation(
        'filter',
        new AvgAggregation('avg-price', 'product.price'),
        [new EqualsAnyFilter('product.active', true)]
    )
);

$result = $this->repository->aggregate($criteria, $context);

$price = $result->get('avg-price');

$price->getAvg();
```


### TermsAggregation
```php
$criteria->addAggregation(
    new TermsAggregation('category-ids', 'product.categories.id')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var TermsResult $categoryAgg */
$categoryAgg = $result->get('category-ids');

foreach ($categoryAgg->getBuckets() as $bucket) {
    $categoryId = $bucket->getKey();
    $count = $bucket->getCount();
}
```

### DateHistogramAggregation
```php
$criteria->addAggregation(
    new DateHistogramAggregation('release-histogram', 'product.releaseDate', 'month')
);

$result = $this->repository->aggregate($criteria, $context);

/** @var DateHistogramResult $histogram */
$histogram = $result->get('release-histogram');

foreach ($histogram->getBuckets() as $bucket) {
    $releaseDate = $bucket->getKey();
    $count = $bucket->getCount();
}
```


## Total Count Mode

The total count mode allows you to configure the value of the `total` property of the result. This gives you more control of expensive queries. There are three modes available:

| Mode | Performance | Description | Usage |
|---|---|---|---|
| `Criteria::TOTAL_COUNT_MODE_EXACT` | slow | Fetches the exact total count | If an exact pagination is required |
| `Criteria::TOTAL_COUNT_MODE_NEXT_PAGES` | fast | Fetches `limit * 5 + 1` to evaluate if there are more items | If pagination is satisfied with the information that more than 5 pages exist |
| `Criteria::TOTAL_COUNT_MODE_NONE` (default) | fastest | Does not fetch the total count | If **no** pagination required |

The total count mode is set on the Criteria object directly.

```php
$criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);
```
