- [Category storefront api](#category-storefront-api)
  * [Route overview](#route-overview)
  * [List route](#list-route)
  * [Complex queries](#complex-queries)
  * [Result](#result)
  * [Examples](#examples)
    + [PHP](#php)
    + [Curl](#curl)

# Category storefront api

## Route overview
The category storefront api can be used to query category information that has been prepared for end customers.
The endpoint is available via `/storefront-api/category` and offers the following routes:
* `/storefront-api/category`
    * List request for category data with filter and sorting support
* `/storefront-api/category/{id} `
    * Detail request for category data 

## List route
The List Route supports both data filtering via GET parameter and POST parameter for more complex queries. Simple queries can be made via GET parameters.

* `/storefront-api/category?filter[category.active]=1`
    * Filtering active categories only
* `/storefront-api/category?filter[category.active]=1&filter[category.name]=Test`
    * Filtering active categories named "Test"
* `/storefront-api/category?sort=name`
    * Ascending sort by name
* `/storefront-api/category?sort=-name`
    * Descending sort by name
* `/storefront-api/category?term=Test`
    * Search for categories which contains the term "test"
    
## Complex queries        
If the list route is addressed by POST, as mentioned earlier, more complex queries can be sent as body:
```json
{
    "page": 1,
    "limit": 10,
    "filter": [
        {
            "type": "nested",
            "operator": "OR",
            "queries": [
                {"type": "term", "field": "category.active", "value": true},
                {"type": "term", "field": "category.name", "value": "B"}
            ]
        }
    ],
    "term": "Test",
    "sort": [
        { "field": "category.name", "direction": "descending" },
        { "field": "category.metaTitle", "direction": "ascending" }
    ],
    "post-filter": [
        {"type": "term", "field": "category.active", "value": true}
    ],
    "aggregations": {
        "active_categories": {
            "count": {"field": "category.active"}
        }
    }
}
```

## Result
A typical result of this route looks as follow:
```json

{
    "links": {
        "first": "http://shopware.development/storefront-api/category?page=1&limit=1",
        "last": "http://shopware.development/storefront-api/category?page=33&limit=1",
        "next": "http://shopware.development/storefront-api/category?page=2&limit=1",
        "self": "http://shopware.development/storefront-api/category"
    },
    "meta": {
        "total": 129
    },
    "aggregations": {
        "active_categories": {
            "count": "205"
        }
    },
    "data": [
        {
            "id": "8ed905942b074107acdbc1e8ae08c376",
            "type": "category",
            "links": {
                "self": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376"
            },
            "attributes": {
                "tenantId": "20080911ffff4fffafffffff19830531",
                "versionId": null,
                "catalogId": "20080911ffff4fffafffffff19830531",
                "parentId": "af22fa927d1b42f1baea28b737fa3072",
                "parentVersionId": null,
                "mediaId": null,
                "mediaVersionId": null,
                "autoIncrement": 51,
                "path": "|af22fa927d1b42f1baea28b737fa3072|",
                "position": 5,
                "level": 2,
                "template": null,
                "active": true,
                "isBlog": false,
                "external": null,
                "hideFilter": false,
                "hideTop": false,
                "productBoxLayout": null,
                "hideSortings": false,
                "sortingIds": null,
                "facetIds": null,
                "childCount": 0,
                "createdAt": "2018-07-06T07:35:34+00:00",
                "updatedAt": null,
                "name": "Automotive",
                "pathNames": "||",
                "metaKeywords": null,
                "metaTitle": null,
                "metaDescription": null,
                "cmsHeadline": null,
                "cmsDescription": null
            },
            "relationships": {
                "parent": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/parent"
                    }
                },
                "media": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/media"
                    }
                },
                "children": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/children"
                    }
                },
                "products": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/products"
                    }
                },
                "catalog": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/catalog"
                    }
                },
                "nestedProducts": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/nested-products"
                    }
                },
                "canonicalUrl": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/category/8ed905942b074107acdbc1e8ae08c376/canonical-url"
                    }
                }
            }
        }
    ],
    "included": []
}
```

## Examples

### PHP
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/category",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "{\"page\":1,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"category.name\",\"direction\":\"descending\"},{\"field\":\"category.metaTitle\",\"direction\":\"ascending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}],\"aggregations\":{\"active_categories\":{\"count\":{\"field\":\"category.active\"}}}}",
  CURLOPT_HTTPHEADER => array(
    "x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ",
    "Content-Type: application/json"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

### Curl
```
curl -X POST \
  http://shopware.development/storefront-api/category \
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
  -H 'Content-Type: application/json' \
  -d '{"page":1,"limit":10,"filter":[{"type":"nested","operator":"OR","queries":[{"type":"term","field":"category.active","value":true}]}],"term":"A","sort":[{"field":"category.name","direction":"descending"},{"field":"category.metaTitle","direction":"ascending"}],"post-filter":[{"type":"term","field":"category.active","value":true}],"aggregations":{"active_categories":{"count":{"field":"category.active"}}}}'
```
