[titleEn]: <>(Filter, search, limit and sorting)

## Introduction

The whole Admin API and some endpoints of the SalesChannel-API support various operations like filtering, searching and limiting the result set.
For more complex operations we created a special search endpoint:

    POST /api/v1/search/{entityName}

## Limit and page

You can limit the number of results by setting the *limit* parameter.

Please be aware that not all limits are supported. The followinglimits are currently allowed:
**1, 5, 10, 25, 50, 75, 100, 500**

Instead of an offset, we provide a page parameter. If you increase the page by one you are effectively increasing the offset by your
defined limit.

**Examples:**

    /api/v1/product?limit=10 => Entities 1-10 are returned
    /api/v1/product?limit=10&page=5 => Entities 41-50

## Filter

The list routes supports both data filtering via GET parameter and POST parameter for more complex queries.
Simple queries can be made via GET parameters.

**Examples for simple queries:**

    /api/v1/product?filter[product.active]=1
    /api/v1/product?filter[product.active]=1&filter[product.name]=Test

## Search

The list routes support both, searching data via GET parameter and POST parameter, for more complex queries.
Simple searches can be made via GET parameters.

**Examples for simple queries:**

    /api/v1/product?term=searchterm

## Sort

The list routes support both, sorting data via GET parameter and POST parameter, for more complex queries.
Simple sorting can be made via GET parameters.

    /api/v1/product?sort=name => Ascending sort by name
    /api/v1/product?sort=-name => Descending sort by name
