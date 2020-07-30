[titleEn]: <>(Admin API extended read)
[hash]: <>(article:api_admin_extended_read)

## Overview

The examples mentioned below are only available for the following entities:

- Products
- Customers
- Media
- Orders

## General search

**GET /api/v3/_search**

Query parameters:

| Name  | Type   | Notes                   | Required |
| ----- | ------ | ----------------------- | :------: |
| term  | string | The term to search for  |    ✔     |
| limit | int    | Limit of the result set |          |

## Advanced search

**POST /api/v3/search/{entity\_name}**

The advanced search endpoint allows you to query by complex conditions and aggregate at the same time.

The following options are possible in the POST request body:

| Name         | Type   | Notes                                                                            |
| ------------ | ------ | -------------------------------------------------------------------------------- |
| term         | string | Term to search for                                                               |
| limit        | string | Result set limit, see [Limit & page](./050-filter-search-limit.md#limit-and-page) |
| page         | string | Result set page, see [Limit & page](./050-filter-search-limit.md#limit-and-page)  |
| filter       | object | See [Filter](./050-filter-search-limit.md#filter)                                 |
| post-filter  | object | Same as filter but does not affect aggregations                                  |
| sort         | mixed  | See [Sort](./050-filter-search-limit.md#sort)                                     |
| aggregations | object | See below                                                                        |

### Aggregation

Aggregations are a powerful tool which allows you to gather statistical data about your executed query.

The following aggregations are currently supported:

| Description                                           | API name     | Type     | Return values             |
| ----------------------------------------------------- | ------------ | -------- | ------------------------- |
| Average of all numeric values for the specified field | avg          | int      | avg                       |
| An approximate count of distinct values               | cardinality  | int      | cardinality               |
| Number of records for the specified field             | count        | int      | count                     |
| Maximal value for the specified field                 | max          | number   | max                       |
| Minimal value for the specified field                 | min          | number   | min                       |
| Stats over all numeric values for the specified field | stats        | object   | count, avg, sum, min, max |
| Sum of all numeric values for the specified field     | sum          | number   | sum                       |
| Grouping for the specific field                       | histogram    | object[] | key, count                |
| Date interval grouping for the specific field         | terms        | object[] | key, count                |

**Example:**

**POST /api/v3/search/category**

```javascript
    const data = {
        "limit": 1,
        "aggregations": [
            { "name": "product-count", "type": "count", "field": "product.id" },
            { "name": "avg-price", "type": "avg", "field": "product.price" },
            { "name": "max-price", "type": "max", "field": "product.price" },
            { "name": "min-price", "type": "min", "field": "product.price" },
            { "name": "stats-price", "type": "stats", "field": "product.price" },
            { "name": "sum-price", "type": "sum", "field": "product.price" },
            { 
                "name": "filter", 
                "type": "filter", 
                "filter": [
                    { "type": "equals", "field": "product.active", "value": true }
                ], 
                "aggregation": { "name": "filtered-avg-price", "type": "avg", "field": "product.price" } 
            },
            {
                "name": "manufacturer-ids",
                "type": "terms",
                "field": "product.manufacturerId"
            },
            {
                "name": "release-histogram",
                "type": "histogram",
                "field": "product.releaseDate",
                "interval": "month"
            },
            {
                "name": "manufacturers",
                "type": "entity",
                "definition": "product_manufacturer",
                "field": "product.manufacturerId"
            }
        ]
    };

    const headers = { 
        "Content-Type": "application/json",
        "Authorization": "Bearer " + bearerToken
    };
    const init = {
        method: 'POST',
        body: JSON.stringify(data),
        headers
    };
    
    fetch('/api/v3/search/category', init)
        .then((response) => response.json())
        .then((responseData) => {
            console.log(responseData);
        });
```

```json
{
    "aggregations": {
        "product-count": {
            "count": 60,
            "extensions": []
        },
        "avg-price": {
            "avg": 521.8666666666667,
            "extensions": []
        },
        "max-price": {
            "max": "996",
            "extensions": []
        },
        "min-price": {
            "min": "10",
            "extensions": []
        },
        "stats-price": {
            "min": "10",
            "max": "996",
            "avg": 521.8666666666667,
            "sum": 31312,
            "extensions": []
        },
        "sum-price": {
            "sum": 31312,
            "extensions": []
        },
        "filtered-avg-price": {
            "avg": 521.8666666666667,
            "extensions": []
        },
        "manufacturer-ids": {
            "buckets": [
                {
                    "key": "a22b9ab55e9942e5ace8ad9577b4a3f2",
                    "count": 2,
                    "extensions": []
                },
                {
                    "key": "eda7011de40f46e0a9513ceaf0fa4a31",
                    "count": 1,
                    "extensions": []
                },
                {
                    "key": "cb84dcd7b23a4014a2cc1cf08a0d3e1f",
                    "count": 3,
                    "extensions": []
                }
                // ...
            ],
            "extensions": []
        },
        "release-histogram": {
            "buckets": [
                {
                    "key": "2019-09-01 00:00:00",
                    "count": 60,
                    "extensions": []
                }
            ],
            "extensions": []
        },
        "manufacturers": {
            "entities": [
                {
                    "mediaId": null,
                    "name": "Cronin, Heidenreich and White",
                    "link": "http://okon.com/et-doloribus-quas-modi-similique.html",
                    "description": null,
                    "media": null,
                    "translations": null,
                    "products": null,
                    "customFields": null,
                    "_uniqueIdentifier": "a22b9ab55e9942e5ace8ad9577b4a3f2",
                    "versionId": "0fa91ce3e96a4bc2be4bd9ce752c3425",
                    "translated": {
                        "name": "Cronin, Heidenreich and White",
                        "description": null,
                        "customFields": []
                    },
                    "createdAt": "2019-09-16T06:48:31.334+00:00",
                    "updatedAt": null,
                    "extensions": [],
                    "id": "a22b9ab55e9942e5ace8ad9577b4a3f2"
                }
                // ...
            ],
            "extensions": []
        }
    }
}
```

## Simple schema

**GET /api/v3/_info/entity-schema.json**

This endpoint responses with a simple schema describing the whole Admin API.

## OpenAPI 3 schema

**GET /api/v3/_info/openapi3.json**

This endpoint's response with an [OpenAPI 3](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.1.md)
schema describing the whole Admin API. 
