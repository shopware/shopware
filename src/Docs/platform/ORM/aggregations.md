# Aggregations

Aggregations are a powerful tool which allows you to gather statistic data about your executed query. 


The following aggregations are currently supported:

| Class name | API name | Type | Return values | Description |
|-----|---|---|---|---|
| AvgAggregation | avg | singe-value | avg | Average of all numeric values for the specified field |
| CardinalityAggregation | cardinality | single-value | cardinality | Approximate count of distinct values |
| CountAggregation | count | single-value | count | Number of records for the specified field |
| MaxAggregation | max | single-value | max | Maximum value for the specified field |
| MinAggregation | min | single-value | min | Minimal value for the specified field |
| StatsAggregation | stats | multi-value | count, avg, sum, min, max | Stats over all numeric values for the specified field | 
| SumAggregation | sum | single-value | sum | Sum of all numeric values for the specified field |
| ValueCountAggregation | value_count | single-value | count | Number of unique values for the specified field |

And can be found under: Shopware\Framework\ORM\Search\Aggregation

## Using aggregations with the repository

You can use aggregations directly when working with the repository.

Here is an example how you can find out how many products a category has.

```php
$criteria = new Criteria();
$criteria->addAggregation(
    new CountAggregation('category.products.id', 'product_count')
);
$result = $this->container->get(CategoryRepository::class)
    ->search($criteria, ApplicationContext::createDefaultContext(Defaults::TENANT_ID));

$aggregationResult = $result->getAggregationResult();
```

The first parameter of an aggregation specifies to which field the aggregation will be applied. 
With the second one you give the aggregation an unique name which makes it easier to identify the aggregation result.


The aggregationResult consists of three properties. A AggregationResultCollection, the ApplicationContext and the Criteria object.

We are mostly interested in the AggregationResultCollection. This keys of the collection are  our previously defined aggregation names.


This is our AggregationResult for the example above:
```php
[product_count] => Shopware\Framework\ORM\Search\Aggregation\AggregationResult Object
    (
        [aggregation:protected] => Shopware\Framework\ORM\Search\Aggregation\CountAggregation Object
            (
                [field:protected] => category.products.id
                [name:protected] => product_count
            )

        [result:protected] => Array
            (
                [count] => 1163
            )

    )
``` 

You find the result values in the table above (return values).


##Using aggregations over the API

Aggregations are also available over the search API. The same example as show above would look like this for the API:

POST /api/v1/search/category
```json
{
    "aggregations": {
        "product_count": { "count": { "field": "category.product.id" } }
    }
}
```

The response will now contain an aggregations property:

```json
{
    "aggregations": {
        "product_count": {
            "count": "1163"
        }
    }
}
```

Aggregations works the same for the json:api/the default json return type.