[titleEn]: <>(Filter, search, limit and sorting)
[hash]: <>(article:api_filter_search_limit)

## Introduction

The whole Admin API and some endpoints of the SalesChannel-API support various operations like filtering, searching and limiting the result set.

# Simple search
## Limit and page

You can limit the number of results by setting the *limit* parameter.

Please be aware that not all limits are supported. The followinglimits are currently allowed:
**1, 5, 10, 25, 50, 75, 100, 500**

Instead of an offset, we provide a page parameter. If you increase the page by one you are effectively increasing the offset by your
defined limit.

**Examples:**

    /api/v3/product?limit=10 => Entities 1-10 are returned
    /api/v3/product?limit=10&page=5 => Entities 41-50

## Filter

The list routes supports both data filtering via GET parameter and POST parameter for more complex queries.
Simple queries can be made via GET parameters.

**Examples for simple queries:**

    /api/v3/product?filter[product.active]=1
    /api/v3/product?filter[product.active]=1&filter[product.name]=Test

## Search

The list routes support both, searching data via GET parameter and POST parameter, for more complex queries.
Simple searches can be made via GET parameters.

**Examples for simple queries:**

    /api/v3/product?term=searchterm

## Sort

The list routes support both, sorting data via GET parameter and POST parameter, for more complex queries.
Simple sorting can be made via GET parameters.

    /api/v3/product?sort=name => Ascending sort by name
    /api/v3/product?sort=-name => Descending sort by name

# Advanced search
The advanced search endpoint allows you to query by complex conditions and aggregate at the same time.

    POST /api/v3/search/{entityName}
    
The following options are possible in the POST request body:

| Name             | Type   | Notes                                                                                          |
| ---------------- | ------ | ---------------------------------------------------------------------------------------------- |
| page             | string | Defines the page to be fetched, starting from `1`                                              |
| limit            | string | Defines the number of results to be returned for each page                                     |
| total-count-mode | int    | Allows to fetch additional data for the entities                                               |
| ids              | array  | Restrict the result to these ids                                                               |
| term             | string | Allows you to perform a full text search                                                       |
| filter           | object | Allows to filter the results by certain conditions                                             |
| query            | object | Allows a granular full text search by fields and scorings |
| post-filter      | object | Same as filter but does not affect aggregations                                                |
| sort             | mixed  | Specifies how the results are to be sorted                                                     |
| aggregations     | object | Allows aggregated data to be determined for the entire result                                  |
| associations     | object | Allows to fetch additional data for the entities                                               |

All following examples were executed on the API resource `POST http://localhost/api/v3/search/product`.

## Page & Limit
```json
{
    "page": 1,
    "limit": 5
}
```

## Total-Count-Mode
By default, the exact total for a search request is always determined. 
However, determining this is very performance intensive and should not be done if the total is not needed. 
This can therefore be controlled via the parameter `total-count-mode` as follows:
* `0` => No total is determined
* `1` => The exact total is determined.
* `2` => It is determined if there are 5 more pages.
```json
{ 
    "total-count-mode": 0 
}
```

## Ids
The search endpoint can also be used to load entities directly if the ids are already known. 
This also offers the possibility to load aggregations or associations for a pre defined collection of ids. 
The result can therefore be restricted to certain ids using the parameter `ids`.
```json
{
    "ids": [
        "0d6a4d1ab1a54159ab1f31a3a6b7d4d5",
        "18795313b67a48e9abd3f2f109eaee8a",
        "286bfb1871044e6f83cfb2f8b81b04a8",
        "2a27d339a10346d3a2d58c5583cdb19f",
        "393433e2325d47afa0117ff59d1a5c97"    
    ]
}
```


## Source - @deprecated tag:v6.3.0
This feature is deprecated with 6.2.0 and will be removed in 6.3.0, use `includes` instead.

In the response of a search or get request, all fields of an entity are returned by default. 
However, it is also possible to minimize the response size by only returning certain fields via Api.

```json
{
    "limit": 1,
    "source": [
    	"id", 
    	"name", 
    	"tax", 
    	"prices.quantityStart",
    	"prices.price.gross",
    	"manufacturer.id", 
    	"manufacturer.name"
    ],
    "associations": {
    	"prices": {}
    }
}
```

```json
{
    "total": 60,
    "data": [
        {
            "id": "077383cc3c88489a834bca551441ff82",
            "name": "Small Copper Information Plantation",
            "tax": {
                "taxRate": 19,
                "name": "19%",
                "products": null,
                "customFields": null,
                "rules": null,
                "_uniqueIdentifier": "7f33ed96986f4169ac542c3bd9b4da6f",
                "versionId": null,
                "translated": [],
                "createdAt": "2019-12-03T09:41:17.490+00:00",
                "updatedAt": null,
                "extensions": {},
                "id": "7f33ed96986f4169ac542c3bd9b4da6f"
            },
            "prices": [
                {
                    "price": [
                        {
                            "gross": 511
                        }
                    ],
                    "quantityStart": 1
                }
            ]
        }
    ]
}
```

## Includes
In the response of a search or get request, all fields of an entity are returned by default. 
However, it is also possible to minimize the response size by only returning certain fields via Api.
Every object that is returned via api has an 'apiAlias'. This can be used to limit the properties of this object in the response:

```json
{
    "limit": 1,
    "includes": {
        "product": ["id", "name", "tax", "price"],
        "tax": ["id", "name"],
        "price": ["gross"]
    }
}
```

Response:

```json
{
    "total": 60,
    "data": [
        {
            "price": [
                {
                    "gross": 71,
                    "apiAlias": "price"
                }
            ],
            "name": "Synergistic Leather Wannabeans",
            "tax": {
                "name": "7%",
                "id": "4f76966be2d14367ac209763c870005c",
                "apiAlias": "tax"
            },
            "id": "09522bceae50470db5f10faf96daf023",
            "apiAlias": "product"
        }
    ],
    "aggregations": []
}
```

## Term
The API provides an integrated full text search that is pre-configured per entity. 
To filter the result by a search term the `term` parameter can be used. 
In addition to filtering, a score is calculated for each entry to determine how well the entity matches the search term.
```json
{
    "term": "Heavy Duty Granite"
}
```

```json
{
    "total": 8,
    "data": [
        {
            "id": "04673e2ec8824f57b92b5720e28e5a82",
            "name": "Heavy Duty Granite Smart Cream",
            "extensions": {
                "search": {
                    "_score": "472.222222222222"
                }
            }
        },
        {
            "id": "3d8bd96a89634252958fd5b54a53dc97",
            "name": "Heavy Duty Concrete Musix",
            "extensions": {
                "search": {
                    "_score": "125"
                }
            }
        },
        {
            "id": "a262879fa0e44abdad773973aca227da",
            "name": "Synergistic Granite Lancelot Boil Cream",
            "extensions": {
                "search": {
                    "_score": "97.222222222222"
                }
            }
        }
    ],
    "aggregations": []
}
```

## Filter
| Name        | Notes                                                                                          |
| ----------- | ---------------------------------------------------------------------------------------------- |
| equals      | Exact match for the given value  |
| equalsAny   | At least one exact match for a value of the given list |
| contains    | Before and after wildcard search for the given value |
| range       | For range compatible fields like numbers or dates |
| not         | Allows to negate a filter |
| multi       | Allows to combine different filters |


### Equals
```json
{
    "filter": [
        { 
            "type": "equals", 
            "field": "stock", 
            "value": 1 
        }    
    ]
}
```

```json
{
    "total": 2,
    "data": [
        {
            "id": "04673e2ec8824f57b92b5720e28e5a82",
            "name": "Heavy Duty Granite Smart Cream",
            "stock": 1
        },
        {
            "id": "414ecd3b8ed542c89308a4a47881a54f",
            "name": "Lightweight Cotton Stretch-O-Rama",
            "stock": 1
        }
    ],
    "aggregations": []
}
```

### EqualsAny
```json
{
    "filter": [
        { 
            "type": "equalsAny", 
            "field": "productNumber", 
            "value": [
                "3fed029475fa4d4585f3a119886e0eb1", 
                "77d26d011d914c3aa2c197c81241a45b"
            ] 
        }    
    ]
}
```

```json
{
    "total": 2,
    "data": [
        {
            "id": "04673e2ec8824f57b92b5720e28e5a82",
            "name": "Heavy Duty Granite Smart Cream",
            "productNumber": "3fed029475fa4d4585f3a119886e0eb1"
        },
        {
            "id": "414ecd3b8ed542c89308a4a47881a54f",
            "name": "Lightweight Cotton Stretch-O-Rama",
            "productNumber": "77d26d011d914c3aa2c197c81241a45b"
        }
    ],
    "aggregations": []
}
```

### Contains
```json
{
    "filter": [
        { 
            "type": "contains", 
            "field": "name", 
            "value": "Lightweight"
        }    
    ]
}    
```

```json
{
    "total": 4,
    "data": [
        {
            "id": "3b53445e5ef74016adba87147b2adc72",
            "name": "Lightweight Granite Auto Labotomy"
        },
        {
            "id": "414ecd3b8ed542c89308a4a47881a54f",
            "name": "Lightweight Cotton Stretch-O-Rama"
        },
        {
            "id": "9eab9064a71844dca3cfc3c898cb81aa",
            "name": "Lightweight Cotton Zensor"
        },
        {
            "id": "fb5f917d225c4515aeb72c7f5e54cb99",
            "name": "Lightweight Iron New Record"
        }
    ],
    "aggregations": []
}
```

### Range
```json
{
    "filter": [
        { 
            "type": "range", 
            "field": "stock", 
            "parameters": {
                "gte": 20,      
                "gt": 19,
                "lte": 30,
                "lt": 31
            }
        }    
    ]
}     
```

```json
{
    "total": 12,
    "data": [
        {
            "id": "0e63b34b76f842c4bdd8331fa47de6a3",
            "name": "Awesome Plastic Stretch n’ Kvetch",
            "stock": 28
        },
        {
            "id": "26a6c64e14ec4b0b858e6f1a6798bbc1",
            "name": "Gorgeous Wool Click Fix",
            "stock": 23
        },
        {
            "id": "3b53445e5ef74016adba87147b2adc72",
            "name": "Lightweight Granite Auto Labotomy",
            "stock": 26
        },
        {
            "id": "3d94e872d7234dc291512c81f147bec1",
            "name": "Practical Rubber Diamantra",
            "stock": 30
        }
    ],
    "aggregations": []
}
```

### Not
```json
{
    "filter": [
        { 
            "type": "not", 
            "operator": "or",
            "queries": [
                {
                    "type": "equals",
                    "field": "stock",
                    "value": 1
                },
                {
                    "type": "equals",
                    "field": "availableStock",
                    "value": 1
                }    
            ]
        }    
    ]
}
```

```json
{
    "total": 58,
    "data": [
        {
            "id": "0cfd2345287b43e28af0e698820798de",
            "name": "Sleek Steel Black Vinyl",
            "stock": 11,
            "availableStock": 11
        },
        {
            "id": "0e63b34b76f842c4bdd8331fa47de6a3",
            "name": "Awesome Plastic Stretch n’ Kvetch",
            "stock": 28,
            "availableStock": -4
        },
        {
            "id": "0f57d848810a487da304d6a3853f41d6",
            "name": "Gorgeous Leather Standing Chocolate",
            "stock": 48,
            "availableStock": 48
        }
    ],
    "aggregations": []
}
```

### Multi
```json
{
    "filter": [
        { 
            "type": "multi", 
            "queries": [
                {
                    "type": "equals",
                    "field": "stock",
                    "value": 1
                },
                {
                    "type": "equals",
                    "field": "active",
                    "value": true
                }    
            ]
        }    
    ]
}
```

```json
{
    "total": 2,
    "data": [
        {
            "id": "04673e2ec8824f57b92b5720e28e5a82",
            "name": "Heavy Duty Granite Smart Cream",
            "stock": 1,
            "availableStock": 1
        },
        {
            "id": "414ecd3b8ed542c89308a4a47881a54f",
            "name": "Lightweight Cotton Stretch-O-Rama",
            "stock": 1,
            "availableStock": 1
        }
    ],
    "aggregations": []
}
```

## Query
The `query` parameter allows you to define which fields are taken into account for determining the score and how the search term is to be interpreted.
Each filter that is passed must be provided with an additional parameter `score`. This allows you to define a score in case the entity applies to the filter.
At the end, the scores of the matching filters for the entity are calculated together and used for sorting. However, if a `sort` has been defined, the results are no longer sorted according to the calculated score.

```json
{
    "limit": 5,
    "query": [
        {
            "score": 50,
            "query": {
                "type": "contains",
                "field": "name",
                "value": "Heavy"    
            }
        },
        {
            "score": 20,
            "query": {
                "type": "equals",
                "field": "markAsTopseller",
                "value": true    
            }
        },
        {
            "score": 15,
            "query": {
                "type": "range",
                "field": "price",
                "parameters": {
                    "gte": 200
                }    
            }
        }    
    ]
}
```

```json
{
    "total": 48,
    "data": [
        {
            "id": "04673e2ec8824f57b92b5720e28e5a82",
            "name": "Heavy Duty Granite Smart Cream",
            "stock": 1,
            "price": [
                {
                    "net": 590.7563025210085,
                    "gross": 703
                }
            ],
            "markAsTopseller": true,
            "extensions": {
                "search": {
                    "_score": "85" 
                }
            }
        },
        {
            "id": "b6d887e0e298486789174b6cf4f63cf6",
            "name": "Heavy Duty Paper Cotton Mouth Samples",
            "stock": 10,
            "price": [
                {
                    "net": 814.018691588785,
                    "gross": 871
                }
            ],
            "markAsTopseller": null,
            "extensions": {
                "search": {
                    "_score": "65"
                }
            }
        }
    ]
}
```

## Sort
The `sort` parameter controls the sorting of the result. Several sorts can be passed. 

```json
{
    "sort": [
        { "field": "stock", "order": "DESC" },
        { "field": "price", "order": "ASC" },
        { "field": "productNumber", "order": "asc", "naturalSorting":  true}
    ]
}
```

```json
{
    "total": 60,
    "data": [
        {
            "id": "04673e2ec8824f57b92b5720e28e5a82",
            "name": "Heavy Duty Granite Smart Cream",
            "stock": 1,
            "productNumber": "3fed029475fa4d4585f3a119886e0eb1"
        },
        {
            "id": "414ecd3b8ed542c89308a4a47881a54f",
            "name": "Lightweight Cotton Stretch-O-Rama",
            "stock": 1,
            "productNumber": "77d26d011d914c3aa2c197c81241a45b"
        },
        {
            "id": "ca3dc71752684c8b804c06432dace364",
            "name": "Mediocre Steel Zensor",
            "stock": 4,
            "productNumber": "ddb370cb3f5e430d948aa27d652fb556"
        },
        {
            "id": "1d61c75801974b98af8c558ea4cc44d2",
            "name": "Practical Rubber Dishytalk",
            "stock": 5,
            "productNumber": "e8acc48add924d00be79320ae915d8ab"
        }
    ],
    "aggregations": []
}
```

## Aggregations
Aggregations allow you to determine further information about the overall result in addition to the actual search results. These include totals, unique values, or the average of a field.

| Name      | Type          | Description |
| ---       |---            |---|
| avg       | metric | Average of all numeric values for the specified field |
| count     | metric | Number of records for the specified field |
| max       | metric | Maximum value for the specified field |
| min       | metric | Minimal value for the specified field |
| stats     | metric | Stats overall numeric values for the specified field |
| sum       | metric | Sum of all numeric values for the specified field |
| entity    | bucket | Groups the result for each value of the provided field and fetches the entities for this field |
| filter    | bucket | Allows to filter the aggregation result |
| terms     | bucket | Groups the result for each value of the provided field and fetches the count of affected documents |
| histogram | bucket | Groups the result for each value of the provided field and fetches the count of affected documents. Although allows to provide date interval (day, month, ...) |

### Avg aggregation
In the following example, the average price of all products is determined
```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "avg-price",
            "type": "avg",
            "field": "price"
        }
    ]
}
```

```json
{
    "aggregations": {
        "avg-price": {
            "avg": 514.3333333333334    
        }
    }
}
```


### Count aggregation
The following example determines the number of products that have been assigned to a manufacturer.
```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "count-manufacturers",
            "type": "count",
            "field": "manufacturerId"
        }
    ]
}
```

```json
{
    "aggregations": {
        "count-manufacturers": {
            "count": 38    
        }
    }
}
```


### Max aggregation
In the following example, the highest product price is determined

```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "max-price",
            "type": "max",
            "field": "price"
        }
    ]
}
```

```json
{
    "aggregations": {
        "max-price": {
            "max": "998"    
        }
    }
}
```


### Min aggregation
In the following example, the lowest product price is determined
```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "min-price",
            "type": "min",
            "field": "price"
        }
    ]
}
```

```json
{
    "aggregations": {
        "min-price": {
            "min": "16"    
        }
    }
}
```

### Sum aggregation
In the following example, the sum of all product prices is determined

```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "sum-price",
            "type": "sum",
            "field": "price"
        }
    ]
}
```
```json
{
    "aggregations": {
        "sum-price": {
            "sum": 30860    
        }
    }
}
```


### Stats aggregation
The `stats` aggregation allows to calculate the `min`, `max`, `avg`, `sum` and `count` within a single aggregation.

```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "stats-price",
            "type": "stats",
            "field": "price"
        }
    ]
}
```
```json
{
    "aggregations": {
        "stats-price": {
            "min": "16",
            "max": "998",
            "avg": 514.3333333333334,
            "sum": 30860    
        }
    }
}
```


### Filter aggregation
You can use the Aggregation filter to further restrict the data that is to be used to determine the aggregation.
```json
{
    "limit": 1,
    "aggregations": [
        {
            "name": "my-filter",
            "type": "filter",
            "filter": [
                { 
                    "type": "equals", 
                    "field": "stock", 
                    "value": 1
                }
            ],
            "aggregation": {  
                "name": "sum-price",
                "type": "sum",
                "field": "price"
            }
        }
    ]
}
```

```json
{
    "aggregations": {
        "sum-price": {
            "sum": 1539    
        }
    }
}
```


### Entity aggregation
Entity aggregation allows to fetch all assigned entities of an association in a request. As an example, all manufacturers of the products that are present in the entire search result are selected.

```json
{
    "limit": 1,
    "aggregations": [
        {
            "name": "manufacturers",
            "type": "entity",
            "definition": "product_manufacturer",
            "field": "manufacturerId"
        }
    ]
}
```

```json
{
    "aggregations": {
        "manufacturers": {
            "entities": [
                {
                    "id": "9983b3ea8eb7466a8fc855355de7861c",
                    "name": "Orn, Bartoletti and Block"
                },
                {
                    "id": "9ab54bccd82344d393c1eeb8fe994252",
                    "name": "Bernhard-Reichert"
                },
                {
                    "id": "df0036b9b0114b78a80cde6193a49e04",
                    "name": "Mueller-Collier"
                }
            ]    
        }
    }
}
```


### Terms aggregation
Terms aggregation can be used to determine the unique values for a field and how often they occur

```json
{
    "limit": 1,
    "aggregations": [
        {
            "name": "manufacturer-ids",
            "type": "terms",
            "field": "manufacturerId"
        }
    ]
}
```

```json
{
    "aggregations": {
        "manufacturer-ids": {
            "buckets": [
                {
                    "key": "9983b3ea8eb7466a8fc855355de7861c",
                    "count": 2    
                },
                {
                    "key": "9ab54bccd82344d393c1eeb8fe994252",
                    "count": 1    
                },
                {
                    "key": "df0036b9b0114b78a80cde6193a49e04",
                    "count": 1    
                }
            ]    
        }
    }
}
```

### Histogram aggregation
The histogram aggregation is used as soon as the data to be determined refers to a date field.
With the histogram aggregation one of the following Date Intervals can be given: 
`minute`, `hour`, `day`, `week`, `month`, `quarter`, `year`, `day`.
This interval groups the result according to the corresponding interval.
 
```json
{
    "limit": 1,
    "aggregations": [
        {
            "name": "release-dates",
            "type": "histogram",
            "field": "releaseDate",
            "interval": "month"
        }
    ]
}
```

```json
{
    "aggregations": {
        "release-dates": {
            "buckets": [
                {
                    "key": "2019-03-01 00:00:00",
                    "count": 2    
                },
                {
                    "key": "2019-04-01 00:00:00",
                    "count": 5    
                },
                {
                    "key": "2019-05-01 00:00:00",
                    "count": 6    
                }
            ]    
        }
    }
}
```

### Difference between bucket and metric aggregations
The aggregations differ in two categories:

* Metric aggregation
* Bucket aggregation

A metric aggregation calculates the value for a specific field. This can be a total or, for example, a minimum or maximum value of the field.
Bucket aggregation is different. This determines how often a value occurs in a search result and returns it together with the count. The special thing about bucket aggregation is that it can contain further aggregations. 

This allows the API to enable complex queries like for example:

* Calculate the number of manufacturers per category that have a price over 500 Euro. * 

```json
{
    "limit": 1,
    "aggregations": [
        {
            "name": "my-filter",
            "type": "filter",
            "filter": [
                { 
                    "type": "range", 
                    "field": "price", 
                    "parameters": {
                        "gte": 500
                    }
                }
            ],
            "aggregation": {  
                "name": "per-category",
                "type": "terms",
                "field": "categories.id",
                "aggregation": {
                    "name": "manufacturer-ids",
                    "type": "terms", 
                    "field": "manufacturerId"
                }
            }
        }
    ]
}
```

```json
{
    "aggregations": {
        "per-category": {
            "buckets": [
                {
                    "key": "733358746689482fb32def4a8e17e863",
                    "count": 2,
                    "manufacturer-ids": {
                        "buckets": [
                            {
                                "key": "9ab54bccd82344d393c1eeb8fe994252",
                                "count": 1
                            },
                            {
                                "key": "f5b253f5d1674f8d83666cdd9367ab25",
                                "count": 1    
                            }
                        ]
                    }
                },
                {
                    "key": "6f21370b4f244e4286efef30d6860cd1",
                    "count": 2,
                    "manufacturer-ids": {
                        "buckets": [
                            {
                                "key": "df0036b9b0114b78a80cde6193a49e04",
                                "count": 1
                            },
                            {
                                "key": "879ce97946d741f98aba789fbf780206",
                                "count": 1
                            }
                        ]
                    }
                }
            ]
        }
    }
}
```

## Post-Filter
Post filters can be used to reduce the search results but leave the aggregation values unaffected by the filters. All filters listed above can be used as post filters.
The following example performs three searches, each with the same aggregation:

### Without any filter
```json
{
    "limit": 1,
    "aggregations": [
        {  
            "name": "max-price",
            "type": "max",
            "field": "price"
        }
    ]
}
```

```json
{
    "aggregations": {
        "max-price": {
            "max": "998",
            "extensions": []
        }
    }
}
```

### Equals filter stored in `filter`
```json
{
    "limit": 1,
    "filter": [
        {
            "type": "equals", 
            "field": "manufacturerId",
            "value": "9983b3ea8eb7466a8fc855355de7861c"
        }
    ],
    "aggregations": [
        {  
            "name": "max-price",
            "type": "max",
            "field": "price"
        }
    ]
}
```

```json
{
    "total": 60,
    "data": [
        {
            "id": "0841096fc89142bca04e59da32cd7b07"
        },
        {
            "id": "0aae08f457a84a268eb1c8a2398f2561"
        },
        {
            "id": "100fd5cf3bbf44c0b43940d205b43aa6"
        },
        {
            "id": "10eac97dc2a64ed6b81ba40287eae396"
        },
        {
            "id": "1314ccfed2844e2fbd7691fc476b65bb"
        },
        {
            "id": "1ada61aca38f4988b99209e2c1b64a9a"
        },
        {
            "id": "2026fdd89f8c432eb0248cd10e876568"
        },
        {
            "id": "24f9d523e4e34870a40cf1c06ec9e009"
        },
        {
            "id": "3134f47cd85f4f689c888bbfeae254d2"
        },
        {
            "id": "338b532c0bf14db1ba34a92081ceca51"
        }
    ],
    "aggregations": {
        "max-price": {
            "max": "998",
            "extensions": []
        }
    }
}
```

### Same filter, but as `post-filter`
```json
{
    "total": 2,
    "data": [
        {
            "id": "0841096fc89142bca04e59da32cd7b07"
        },
        {
            "id": "b524eb59730b44c9a310e53c6ca4dcc0"
        }
    ],
    "aggregations": {
        "max-price": {
            "max": "184",
            "extensions": []
        }
    }
}
```

```json
{
    "total": 2,
    "data": [
        {
            "id": "0841096fc89142bca04e59da32cd7b07"
        },
        {
            "id": "b524eb59730b44c9a310e53c6ca4dcc0"
        }
    ],
    "aggregations": {
        "max-price": {
            "max": "998",
            "extensions": []
        }
    }
}
```

## Associations
When entities are queried via the API, the entities contain only a minimal data set. This includes all data stored in the table of the entity itself, the translation of the current language and all many to one associations marked with `autoload = true`.
For example, the manufacturer of a product is not loaded by default:

```json
{
    "limit": 10
}
```

In the response every `manufacturer` is `null`:

```

{
    "total": 61,
    "data": [
        {
            "id": "03b4223a16ef459f90549627795548d4",
            "name": "Synergistic Plastic Tea Bones",
            "productNumber": "1814a14c82254f9782d50bf0158871b4",
            "manufacturer": null
        },
        {
            "id": "03e18dbd164e4e32825c0dce0af28756",
            "name": "Mediocre Aluminum Technoracks",
            "productNumber": "bd7b08d07688483bbee5fc4bbd34e338",
            "manufacturer": null
        }
    ],
    "aggregations": []
}
```

To load further associations of an entity, the `associations` parameter can be passed in the request body.
This is an object where the object key is the name of the association:

```json
{
    "limit": 5,
    "associations": {
        "manufacturer": {}
    }
}
```

Through the above mentioned `manufacturer` association the manufacturer is now loaded with each product.

```json
{
    "total": 61,
    "data": [
        {
            "id": "03b4223a16ef459f90549627795548d4",
            "name": "Synergistic Plastic Tea Bones",
            "productNumber": "1814a14c82254f9782d50bf0158871b4",
            "manufacturer": {
                "id": "da3ee9a1358b4469a93d9e4bd383a20a",
                "name": "Batz-Weber",
                "media": null
            }
        }
    ],
    "aggregations": []
}
```

This can also be applied to nested associations. In the above example, each manufacturer now lacks the `media` association. In order to load this with the `manufacturer` association the `media` association can be given:

```json
{
    "limit": 5,
    "associations": {
        "manufacturer": {
            "associations": {
                "media": {}
            }
        }
    }
}
```

```json
{
    "total": 61,
    "data": [
        {
            "id": "03b4223a16ef459f90549627795548d4",
            "name": "Synergistic Plastic Tea Bones",
            "productNumber": "1814a14c82254f9782d50bf0158871b4",
            "manufacturer": {
                "id": "da3ee9a1358b4469a93d9e4bd383a20a",
                "name": "Batz-Weber",
                "media": {
                    "id": "17e1693447f944749bd3a4f649f59baf",
                    "fileName": "175fa5f1e40fe23aa83dcb6863839425"
                }
            }
        }
    ],
    "aggregations": []
}
```
