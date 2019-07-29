[titleEn]: <>(Admin API extended read)

## Overview

The examples mentioned below are only available for the following entities:

- Products
- Customers
- Media
- Orders

## General search

**GET /api/v1/_search**

Query parameters:

| Name  | Type   | Notes                   | Required |
| ----- | ------ | ----------------------- | :------: |
| term  | string | The term to search for  |    ✔     |
| limit | int    | Limit of the result set |          |

## Advanced search

**POST /api/v1/search/{entity\_name}**

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

| Description                                           | API name     | Type   | Return values             |
| ----------------------------------------------------- | ------------ | ------ | ------------------------- |
| Average of all numeric values for the specified field | avg          | int    | avg                       |
| An approximate count of distinct values               | cardinality  | int    | cardinality               |
| Number of records for the specified field             | count        | int    | count                     |
| Maximal value for the specified field                 | max          | number | max                       |
| Minimal value for the specified field                 | min          | number | min                       |
| Stats over all numeric values for the specified field | stats        | object | count, avg, sum, min, max |
| Sum of all numeric values for the specified field     | sum          | number | sum                       |
| Number of unique values for the specified field       | value\_count | int    | count                     |

**Example:**

**POST /api/v1/search/category**


```javascript
    const data = {
        aggregations: {
            product_count: { count: { field: "category.products.id" } }
        }
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
    
    fetch('/api/v1/search/category', init)
        .then((response) => response.json())
        .then((responseData) => {
            console.log(responseData);
        });
```

```json
    {
        "aggregations": {
            "product_count": { "count": { "field": "category.product.id" } }
        }
    }
```

## Simple schema

**GET /api/v1/_info/entity-schema.json**

This endpoint responses with a simple schema describing the whole Admin API.

## OpenAPI 3 schema

**GET /api/v1/_info/openapi3.json**

This endpoint's response with an [OpenAPI 3](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/3.0.1.md)
schema describing the whole Admin API. 
