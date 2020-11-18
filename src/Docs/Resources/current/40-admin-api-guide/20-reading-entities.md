[titleEn]: <>(Reading entities)
[hash]: <>(article:admin_api_read)

## Reading entities
The Admin API is designed so that all entities of the system can be read in the same way. 
Once an entity is registered in the system, it can be written and read via API.
The appropriate routes for the entity are generated automatically and follow the rest pattern. 

The entity object `customer_group` is available under the endpoint `api/v1/customer-group`.
If an entity is addressed via the relation of a main route, the corresponding property name of the association is used here.

An example: 
* The `ManufacturerEntity` is registered as `product_manufacturer` in the system and can be read `api/v1/product-manufacturer`.
* The `ProductEntity` has an association with the property name `manufacturer`, which refers to the `ManufacturerEntity`. 
* The manufacturer of a product can then be read over `api/v1/product/{productId}/manufacturer`.

For an entity object, the system automatically creates the following routes through which the entity object can be read:

| Name  | Method   | Route                   | Usage |
| ----- | ------ | ----------------------- | ------ |
| api.customer_group.list       | GET  | /api/v{version}/customer-group             | Allows to read a list of this entity |
| api.customer_group.detail     | GET  | /api/v{version}/customer-group/{id}        | Allows to fetch a single entity |
| api.customer_group.search     | POST | /api/v{version}/search/customer-group      | Allows to perform a complex search |
| api.customer_group.search-ids | POST | /api/v{version}/search-ids/customer-group  | Allows to perform a complex search and fetching only matching ids |

A list of all routes and registered entities in the system can be read via the `/api/v3/_info/*` routes:
* `/api/v3/_info/openapi3.json`
* `/api/v3/_info/open-api-schema.json`
* `/api/v3/_info/entity-schema.json`

### Search endpoint
The Admin API supports a wide range of filtering, aggregation and sorting capabilities. However, according to the REST definition, data should only be read via GET,
we have provided the `/api/v3/search/*` route for this. This working with complex filters or other components that can be mapped via DAL. 

### Parameter overview
When reading the data, the following parameters are available to influence the result:

| Parameter           | Usage                                                                     | 
| ------------------- | ------------------------------------------------------------------------- |
| `includes`          | Restricts the output to the defined fields                                |
| `ids`               | Limits the search to a list of Ids                                        |
| `total-count-mode`  | Defines whether a total must be determined                                |
| `page`              | Defines at which page the search result should start                      |
| `limit`             | Defines the number of entries to be determined                            |
| `filter`            | Allows you to filter the result and aggregations                          |
| `post-filter`       | Allows you to filter the result, but not the aggregations                 |
| `query`             | Enables you to determine a ranking for the search result                  |
| `term`              | Enables you to determine a ranking for the search result                  |
| `sort`              | Defines the sorting of the search result                                  |
| `grouping`          | Lets you group records by fields                                          |
| `associations`      | Allows to load additional data to the standard data of an entity          |

#### includes parameter
The `includes` parameter allows you to restrict the returned fields. 

* Purpose: The response size is reduced - mandatory for mobile applications
* Purpose: When debugging, the response is smaller and you can concentrate on the essential fields

Endpoint: 
```
POST /api/v3/search/product
{
    "includes": {
        "product": ["id", "name"]
    }
}


{
    "total": 120,
    "data": [
        {
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        },
        {
            "name": "Mediocre Plastic Ticket Lift",
            "id": "075fb241b769444bb72431f797fd5776",
            "apiAlias": "product"
        }
  ]
}
```

##### api alias
The `includes` parameter is defined as an object. The key is always defined as the api alias of an object. The alias of an object is unique in the system.
For entities, this is the entity name: `product`, `product_manufacturer`, `order_line_item`, ...
This pattern applies not only to simple fields but also to associations:

Endpoint: 
```
POST /api/v3/search/product
{
    "includes": {
        "product": ["id", "name", "manufacturer", "tax"],
        "product_manufacturer": ["id", "name"],
        "tax": ["id", "name"]
    }
}

{
    "total": 120,
    "data": [
        {
            "name": "Synergistic Rubber Fish Soda",
            "tax": {
                "name": "7%",
                "id": "f054f15262004922a2ef70f0dcff66dc",
                "apiAlias": "tax"
            },
            "manufacturer": {
                "name": "shopware AG",
                "id": "6b6a8e965c5b48249400d0562ff8a6de",
                "apiAlias": "product_manufacturer"
            },
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        }
  ]
}
```

#### ids parameter
The `ids` parameter allows you to limit the search to a list of Ids.

* Purpose: Selectively read out several records. Works as a multi get request

```
POST /api/v3/search/product
{
    "ids": [
        "012cd563cf8e4f0384eed93b5201cc98", 
        "075fb241b769444bb72431f797fd5776",
        "090fcc2099794771935acf814e3fdb24"
    ],
    "includes": {
        "product": ["id", "name"]
    }
}


{
    "total": 3,
    "data": [
        {
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        },
        {
            "name": "Mediocre Plastic Ticket Lift",
            "id": "075fb241b769444bb72431f797fd5776",
            "apiAlias": "product"
        },
        {
            "name": "Sleek Copper Strataform",
            "id": "090fcc2099794771935acf814e3fdb24",
            "apiAlias": "product"
        }
    ]
}
```

#### total-count-mode
The `total-count-mode` parameter can be used to define whether the total for the total number of hits should be determined for the search query.
This parameter supports the following values:

* `0 [default]` - No total is determined 
    * Purpose: This is the most performing mode because MySQL Server does not need to run the `SQL_CALC_FOUND_ROWS` in the background.
    * Purpose: Should be used if pagination is not required
* `1` - An exact total is determined.
    * Purpose: Should be used if a pagination with exact page number has to be displayed
    * Disadvantage: Performance intensive. Here you have to work with `SQL_CALC_FOUND_ROWS`
* `2` - It is determined whether there is a next page
    * Advantage: Good performance, same as `0`.
    * Purpose: Can be used well for infinite scrolling, because with infinite scrolling the information is enough to know if there is a next page to load 

```
POST /api/v3/search/product
{
    "total-count-mode": 1,
    "includes": {
        "product": ["id", "name"]
    }
}

{
    "total": 120,
    "data": [
        {
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        },
    ]
}
```

#### term parameter
The `term` parameter allows a free text search on an entity. The fields used to determine the `_score` value can be defined in the entity. 
The `query` parameter allows a more precise controlling via the API.
* Purpose: To implement a search function, where the server should decide what to search for.

```
POST /api/v3/search/product
{
    "term": "Awesome Bronze",
    "includes": {
        "product": ["id", "name", "extensions"]
    }
}

{
    "total": 17,
    "data": [
        {
            "name": "Awesome Bronze Codeleon",
            "extensions": {
                "search": {
                    "_score": "482.14285714286"
                }
            },
            "id": "3a25807cfa994569bf0f64eeef1bf02a",
            "apiAlias": "product"
        },
        {
            "name": "Awesome Paper Power Coffee",
            "extensions": {
                "search": {
                    "_score": "125"
                }
            },
            "id": "875b07ca3c4d49adbbe9bc036c97100a",
            "apiAlias": "product"
        }
    ]
}

```

#### page & limit parameter
The `page` and `limit` parameters can be used to control pagination:
* Purpose: Listings are implemented with these parameters.

```
POST /api/v3/search/product
{
    "page": 1,
    "limit": 5,
    "total-count-mode": 1,
    "includes": {
        "product": ["id", "name"]
    }
}


{
    "total": 120,
    "data": [
        {
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        },
        {
            "name": "Mediocre Plastic Ticket Lift",
            "id": "075fb241b769444bb72431f797fd5776",
            "apiAlias": "product"
        },
        {
            "name": "Sleek Copper Strataform",
            "id": "090fcc2099794771935acf814e3fdb24",
            "apiAlias": "product"
        },
        {
            "name": "Awesome Bronze Krill Kream",
            "id": "0acc3aa5c45a492c9a2adb8844cb7adc",
            "apiAlias": "product"
        },
        {
            "name": "Sleek Wooden Perceive IT",
            "id": "0dd44e5896bf4210a6a2a755ff723923",
            "apiAlias": "product"
        }
    ]    
}

```

#### filter parameter
The `filter` parameter allows you to filter the result and aggregations. Different filter types are available here.

* Purpose: This can be used to implement api queries for specific scenarios: 
    * "Give me all orders that have the payment status open".
    * "Give me all products from manufacturer xxx"
    * ...
 
``` 
POST /api/v3/search/product
{
    "filter": [
        { "type": "equals", "field": "productNumber", "value": "e3572fb623b24424a08ef84279912dc3" }
    ],
    "includes": {
        "product": ["id", "name", "productNumber"]
    }
}


{
    "total": 1,
    "data": [
        {
            "productNumber": "e3572fb623b24424a08ef84279912dc3",
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        }
    ]    
}
```

#### query parameter
The `query` parameter allows you to create a search query that returns a `_score` for each found entity.
Any filter type can be used for the `query`. A `score` has to be defined for each query. The sum of the matching queries then results in the total `_score` value.

```
{
    "query": [
        {
            "score": 500,
            "query": { "type": "contains", "field": "name", "value": "Bronze"}
        },
        { 
            "score": 500,
            "query": { "type": "equals", "field": "active", "value": true }
        },
        {
            "score": 100,
            "query": { "type": "equals", "field": "manufacturerId", "value": "db3c17b1e572432eb4a4c881b6f9d68f"}
        }
    ],
    "includes": {
        "product": ["id", "name", "extensions", "manufacturerId"]
    }
}

{
    "total": 5,
    "data": [
        {
            "manufacturerId": "db3c17b1e572432eb4a4c881b6f9d68f",
            "name": "Awesome Bronze Krill Kream",
            "extensions": {
                "search": {
                    "_score": "1100"
                }
            },
            "id": "0acc3aa5c45a492c9a2adb8844cb7adc",
            "apiAlias": "product"
        },
        {
            "manufacturerId": "d0c0daa910d94b3c8b03c2bef6acb9b8",
            "name": "Synergistic Bronze New Tab",
            "extensions": {
                "search": {
                    "_score": "1000"
                }
            },
            "id": "72858576ac634f209b7ad61db15b7cc3",
            "apiAlias": "product"
        },
        {
            "manufacturerId": "3b5f9d51803849c68bb72360debd3da0",
            "name": "Fantastic Paper Zamox",
            "extensions": {
                "search": {
                    "_score": "500"
                }
            },
            "id": "18d2b4225ea34b17a6099108da159e7f",
            "apiAlias": "product"
        }
    ]
}
```

#### sort parameter
The `sort` parameter allows to control the sorting of the result. Several sorts can be transferred at the same time.
* The `field` parameter defines which field is to be used for sorting.
* The `order` parameter defines the sort direction.
* The parameter `naturalSorting` allows to use a [Natural Sorting Algorithm](https://en.wikipedia.org/wiki/Natural_sort_order) 

```
{
    "limit": 5,
    "sort": [
        { "field": "name", "order": "ASC", "naturalSorting": true },
        { "field": "active", "order": "DESC" }    
    ],
    "includes": {
        "product": ["id", "name"]
    }
}

{
    "total": 5,
    "data": [
        {
            "name": "Sleek Rubber Blinq",
            "id": "9827a160b63743d3b1d4adad48fb379c",
            "apiAlias": "product"
        },
        {
            "name": "Small Granite Qleen",
            "id": "17a984cf88294b0cbaf0f477301191fe",
            "apiAlias": "product"
        },
        {
            "name": "Fantastic Cotton Zog",
            "id": "1c855bdbb7b542bebfbd6b358657dfa1",
            "apiAlias": "product"
        },
        {
            "name": "Rustic Steel Cleanze",
            "id": "38e6f229ce1843f99b6e2d676e8a001c",
            "apiAlias": "product"
        },
        {
            "name": "Sleek Iron Buzz Kilt",
            "id": "6527730757544efa9728abbc76ee006e",
            "apiAlias": "product"
        }
    ]    
}
```

#### aggregations parameter
With the `aggregation` parameter, meta data can be determined for a search query.
There are different types of aggregations which are listed in the reference documentation.
A simple example is the determination of the average price from a product search query:

* Purpose: Calculation of statistics and metrics
* Purpose: Determination of possible filters

```
{
    "limit": 1,
    "includes": {
        "product": ["id", "name"]
    },
    "aggregations": [
        {
            "name": "average-price",
            "type": "avg",
            "field": "price"
        }    
    ]
}

{
    "total": 1,
    "data": [
        {
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        }
    ],
    "aggregations": {
        "average-price": {
            "avg": 509.39166666666665,
            "extensions": []
        }
    }
}
```


#### post-filter parameter
The `post-filter` parameter allows you to filter the result. However, unlike the `filter` parameter, it has no effect on the result of the aggregations.

*Purpose*: The purpose of this filter is a little more complex. This type of filter is actually only used for listings, where an end user can further filter a listing.

Here is an example: In the storefront, the active category is set as `filter`, because this is the basis for the listing. 
Filters that the customer selects in the filter panel are then applied as `post-filter` (manufacturer filter, price filter, properties, ...). 
These do not affect the aggregations, so that e.g. the complete list is still displayed in the manufacturer filter.  

The following example shows how filters and post-filters affect the results of a search.

1: First, we execute a request in which we want to query a list of products and determine the total count and the average product price:

```
POST /api/v3/search/product
{
    "limit": 1,
    "total-count-mode": 1,
    "includes": { 
        "product": ["id"] 
    },
    "aggregations": [
        {
            "name": "avg-price",
            "type": "avg",
            "field": "price"
        }    
    ]
}

{
    "total": 65,
    "data": [
        {
            "id": "083462cf1ae345faba00ce9432c49a71",
            "apiAlias": "product"
        }
    ],
    "aggregations": {
        "avg-price": {
            "avg": 434.3709677419355,
            "extensions": []
        }
    }
}
```

Summary:
* A total of 65 products were found (`"total": 65,`)
* The average price of the determined products is 434.37€ (`"avg": 434.3709677419355`)

2: Now we will add a filter in which only active products will be considered:

```
{
    "limit": 1,
    "total-count-mode": 1,
    "filter": [
        { "type": "equals", "field": "active", "value": true }
    ],
    "includes": { 
        "product": ["id"] 
    },
    "aggregations": [
        {
            "name": "avg-price",
            "type": "avg",
            "field": "price"
        }    
    ]
}

{
    "total": 45,
    "data": [
        {
            "id": "65d98f2247e14d22b6f2f32805f41bff",
            "apiAlias": "product"
        }
    ],
    "aggregations": {
        "avg-price": {
            "avg": 399.26190476190476,
            "extensions": []
        }
    }
}
```

Impact:
* A total of 45 active products were found (`"total": 45,`) 
    * The `filter` parameter affects the `total`
* The average price of the active products determined is 399.26€ (`"avg": 399.26190476190476"`)
    * The `filter` parameter is taken into account when determining the aggregations

3: Now we set the active filter as a `post-filter`:

```
{
    "limit": 1,
    "total-count-mode": 1,
    "post-filter": [
        { "type": "equals", "field": "active", "value": true }
    ],
    "includes": { 
        "product": ["id"] 
    },
    "aggregations": [
        {
            "name": "avg-price",
            "type": "avg",
            "field": "price"
        }    
    ]
}

{
    "total": 45,
    "data": [
        {
            "id": "65d98f2247e14d22b6f2f32805f41bff",
            "apiAlias": "product"
        }
    ],
    "aggregations": {
        "avg-price": {
            "avg": 434.3709677419355,
            "extensions": []
        }
    }
}
```

Impact:
* A total of 45 active products were found (`"total": 45,`) 
    * The `post-filter` parameter affects the `total`
* The average price of the active products determined is 434.37€ (`"avg": 434.3709677419355"`)
    * The `post-filter` parameter is **not** considered when determining aggregations

#### grouping parameter
The `grouping` parameter allows you to group the result over fields. It is the `GROUP BY` definition of the underlying SQL statement.

**Purpose**: This can be used to realize queries like: 
* "Give me one product for each manufacturer"
* "Give me one order per day"

```
{
    "limit": 5,
    "includes": {
        "product": ["id", "name", "active"]
    },
    "grouping": ["active"]
}

{
    "total": 2,
    "data": [
        {
            "active": true,
            "name": "Synergistic Rubber Fish Soda",
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        },
        {
            "active": false,
            "name": "Small Granite Qleen",
            "id": "17a984cf88294b0cbaf0f477301191fe",
            "apiAlias": "product"
        }
    ]
}
```

#### associations parameter
The `associations` parameter allows you to load additional data to the minimal data set of an entity without sending an extra request.
The key of the parameter is the property name of the association in the entity. Within this key an almost complete search query can be defined:  

```
POST /api/v3/search/category
{
    "limit": 1,
    "associations": {
        "products": {
            "limit": 5,
            "filter": [
                { "type": "equals", "field": "active", "value": true }
            ],
            "sort": [
                { "field": "name", "order": "ASC" }    
            ]
        }
    },
    "includes": {
        "category": ["id", "name", "products"],
        "product": ["id", "name", "active"]
    }
}

{
    "total": 1,
    "data": [
        {
            "name": "Home",
            "products": [
                {
                    "active": true,
                    "name": "Aerodynamic Concrete Pubic Coil",
                    "id": "7ccb8f4c1fa54f59b80a2ec2ab0ba90f",
                    "apiAlias": "product"
                },
                {
                    "active": true,
                    "name": "Aerodynamic Paper Turnling",
                    "id": "93b731b71714452daa919075a0047e29",
                    "apiAlias": "product"
                },
                {
                    "active": true,
                    "name": "Awesome Concrete Rock Meet",
                    "id": "b1aa284c2de24d16af7518bc5530c326",
                    "apiAlias": "product"
                },
                {
                    "active": true,
                    "name": "Mediocre Bronze Cracklebox",
                    "id": "9ecdfb277ff2467b9bb0ff5c30069938",
                    "apiAlias": "product"
                },
                {
                    "active": true,
                    "name": "Practical Leather Isoflux",
                    "id": "18cd05f7ddae45af91b86227a118d28a",
                    "apiAlias": "product"
                }
            ],
            "id": "6de270fbaf6d4fb69ae6b99a8cbb2688",
            "apiAlias": "category"
        }
    ]    
}

```

### Language header
By default, the API delivers the entities via the system language. However, this can be controlled via the `sw-language-id` header.

```
POST /api/v3/search/product
--header 'sw-language-id: be01bd336c204f20ab86eab45bbdbe45'

{
    "limit": 1,
    "includes": {
        "product": ["id", "name"]
    }
}

{
    "total": 1,
    "data": [
        {
            "name": null,
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        }
    ]    
}
``` 

#### Translated property
If a field is not translated, it has the value `null`. 
However, to avoid sending multiple requests to get the final translation of an entity (Shopware 6 has a three layer language inheritance) there is the field `translated`.
In this field, all fields are already translated for the provided language, considering the inheritance:

```
POST /api/v3/search/product
--header 'sw-language-id: be01bd336c204f20ab86eab45bbdbe45'

{
    "limit": 1,
    "includes": {
        "product": ["id", "name", "translated"]
    }
}

{
    "total": 1,
    "data": [
        {
            "name": null,
            "translated": {
                "name": "Synergistic Rubber Fish Soda"
            },
            "id": "012cd563cf8e4f0384eed93b5201cc98",
            "apiAlias": "product"
        }
    ]
}
```

### Inheritance header
Shopware 6 allows developers to define inheritance between parent and child. This has been used, for example, for products and their variants.
Certain fields of a variant can therefore inherit the data from the parent product or define them themselves.
However, the Admin API initially only delivers the data of its own record, without considering parent-child inheritance.
To tell the API that the inheritance should be considered, the header `sw-inheritance` must be sent with the data:

```
POST /api/v3/search/product
--header 'sw-inheritance: 1'

{
    "limit": 1,
    "filter": [
        { 
            "type": "not", 
            "queries": [
                { "type": "equals", "field": "parentId", "value": null }
            ]
        }
    ],
    "includes": {
        "product": ["id", "name", "parentId", "translated"]
    }
}


{
    "total": 1,
    "data": [
        {
            "parentId": "21770668c8d449fc9aa9870d80b1c8a8",
            "name": null,
            "translated": {
                "name": "Synergistic Plastic Zen Collar"
            },
            "id": "cd07930a83ae402bab82e34647a825fa",
            "apiAlias": "product"
        }
    ]
}
```
 
