# Api examples

## Routing

* GET /api/product
    * list of products  
* GET /api/product/{id}
    * single product with detail data
* GET /api/product/{id}/context-prices
    * list of context prices of a single product
* GET /api/product/{id}/context-prices/{id}
    * detail data of a single context price 

## Pagination

```
/api/product?offset=0&limit=10
```

## Simple mode

### Filtering in simple mode
The rest api allows a simple filtering which uses an AND condition and supports equals queries:
 
```
/api/product?filter[product.price]=13&filter[product.manfuacturer.name]=shopware
```
Displays only products which has a `price` of `13` and where the `manufacturer name` is equals `shopware`

### Sorting in simple mode

```
/api/product?sort=-product.name,product.price
```
Sorts the list by product name, followed by the product price.
The `-` sign defines the `DESCENDING` direction for the `product.name` field.  

### Search in simple mode
```
/api/product/term=shopware
```
Builds a search query for the product entity. The api uses different fields (which are marked in the entity definition with the \Shopware\Api\Entity\Write\Flag\SearchRanking flag) for the query execution.

## Advanced mode
To send complex conditional queries to the api, it is required to use the `/api/search/{entity}` endpoint.
This endpoint allows to receive a entity list even by sending a post request. All previous mentioned filter and pagination parameter have to be provided by post data.

```json
{
	"offset": 0,
	"limit": 2,
	"fetch-count": 1, 
	"filter": [
		{
			"type": "range",
			"field": "product.price",
			"parameters": {
				"gte": 100
			}
		}
	],
	"post-filter": [
		{
			"type": "range",
			"field": "product.price",
			"parameters": {
				"gte": 200
			}
		}
	],
	"query": [
		{ 
			"score": 1,
			"scoreField": "product.stock",
			"query": {
				"type": "match",
				"field": "product.name",
				"value": "awesome"
			}
		}
	],
	"sort": [
		{ "field": "product.price", "order": "ASC"  },
		{ "field": "product.name", "order": "DESC"  }
	]
}
```

* `fetch-count` allows to configure the total property of the response
    * `0` => no total count will be selected. Should be used if no pagination required (**fastest**)
    * `1` => exact total count will be selected. Should be used if an exact pagination is required (**slow**)
    * `2` => fetches `limit * 5 + 1`. Should be used if pagination can work with "next page exists" (**fast**) 
* `filter` filters the result, considered in aggregation calculation
* `post-filter` filters the result, not considered in aggregation calculation
* `query` allows a search with a scored query
* `sort` sorts the result

## Query types

### Term Query
Defines an exact match of the expression. Sql example `WHERE product.name = awesome`
```json
{
    "type": "term",
    "field": "product.name",
    "value": "awesome"
}
```

### Match Query
Defines an inexact match of the expression. Sql example `WHERE product.name LIKE %awesome%`
```json
{
    "type": "match",
    "field": "product.name",
    "value": "awesome"
}
```

### Terms Query
Defines a multi value term query. Sql example `WHERE product.name IN (awesome, shopware)`
```json
{
    "type": "terms",
    "field": "product.name",
    "value": "awesome|shopware"
}
```

### Range Query
Allows to query a range value of a field by using **G**reater **T**han **E**quals (GTE), **G**reater **T**han (GT), **L**ess **T**han **E**quals (LTE), **L**ess **T**han (LT) . Sql example: `WHERE (product.price >= 100 AND product.price <= 200)`
```json
{
    "type": "range",
    "field": "product.stock",
    "parameters": {
      "lte": 200,
      "gte": 100
    }
}
```

### Nested query
A nested query allows to group multiple queries in a clip and control the AND behavior. Sql example `WHERE (product.name LIKE %name% AND product.price = 100 AND (product.stock = 0 OR product.minStock = 0))`
```json
{
    "type": "nested",
    "operator": "AND",
    "queries": [
        {
            "type": "match",
            "field": "product.name",
            "value": "awesome"
        },
        {
            "type": "term",
            "field": "product.price",
            "value": 100
        },
        {
            "type": "nested",
            "operator": "OR",
            "queries": [
                {
                    "type": "term",
                    "field": "product.stock",
                    "value": 0
                },
                {
                    "type": "term",
                    "field": "product.minStock",
                    "value": 0
                }
            ]
        }
    ]
}
```

### Not Query
The not query allows to negate a collection of queries. Sql example `WHERE !(product.name = awesome AND product.price = 100)` 
```json
{
    "type": "not",
    "operator": "AND",
    "queries": [
        {
            "type": "term",
            "field": "product.name",
            "value": "awesome"
        },
        {
            "type": "term",
            "field": "product.price",
            "value": 100
        }
    ]
}
```

### ScoreQuery
A score query can only be used in `advanced mode` and only in the `query` context. This query allows to build a query which is used for ranking calculation in the query.
```json
{
    "offset": 0,
    "limit": 10,
    "query": [
        { 
			"score": 100,
			"scoreField": "product.stock",
			"query": {
				"type": "match",
				"field": "product.name",
				"value": "awesome"
			}
		}
    ]
}
```
Parameters of the score query are used as follow:
* `score` defines the score which should be used if the expression match, in this case if `product.name LIKE %awesome%` the hit gets an additional ranking of 100
* `scoreField` allows to define a multiplier for the score. In this case the score of 100 is multiplied with the `product.stock` if the expression match
* `query` defines the expression for the score query. All above queries are supported here.