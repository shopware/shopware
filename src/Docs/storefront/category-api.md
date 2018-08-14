- [Category storefront api](#category-storefront-api)
  * [Route overview](#route-overview)
  * [List route](#list-route)
  * [Complex queries](#complex-queries)
  * [Result](#result)
  * [Examples](#examples)
    + [PHP](#php)
    + [Curl](#curl)
    + [Python](#python)
    + [Java](#java)
    + [Javascript](#javascript)
    + [jQuery](#jquery)
    + [NodeJS Native](#nodejs-native)
    + [Go](#go)

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
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
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
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw' \
  -H 'Content-Type: application/json' \
  -d '{"page":1,"limit":10,"filter":[{"type":"nested","operator":"OR","queries":[{"type":"term","field":"category.active","value":true}]}],"term":"A","sort":[{"field":"category.name","direction":"descending"},{"field":"category.metaTitle","direction":"ascending"}],"post-filter":[{"type":"term","field":"category.active","value":true}],"aggregations":{"active_categories":{"count":{"field":"category.active"}}}}'
```

### Python
```python
import http.client

conn = http.client.HTTPConnection("shopware,development")

payload = "{\"page\":1,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"category.name\",\"direction\":\"descending\"},{\"field\":\"category.metaTitle\",\"direction\":\"ascending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}],\"aggregations\":{\"active_categories\":{\"count\":{\"field\":\"category.active\"}}}}"

headers = {
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
    }

conn.request("POST", "storefront-api,category", payload, headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

### Java
```
OkHttpClient client = new OkHttpClient();

MediaType mediaType = MediaType.parse("application/json");
RequestBody body = RequestBody.create(mediaType, "{\"page\":1,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"category.name\",\"direction\":\"descending\"},{\"field\":\"category.metaTitle\",\"direction\":\"ascending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}],\"aggregations\":{\"active_categories\":{\"count\":{\"field\":\"category.active\"}}}}");
Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/category")
  .post(body)
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")
  .build();

Response response = client.newCall(request).execute();
```

### Javascript
```javascript
var data = JSON.stringify({
  "page": 1,
  "limit": 10,
  "filter": [
    {
      "type": "nested",
      "operator": "OR",
      "queries": [
        {
          "type": "term",
          "field": "category.active",
          "value": true
        }
      ]
    }
  ],
  "term": "A",
  "sort": [
    {
      "field": "category.name",
      "direction": "descending"
    },
    {
      "field": "category.metaTitle",
      "direction": "ascending"
    }
  ],
  "post-filter": [
    {
      "type": "term",
      "field": "category.active",
      "value": true
    }
  ],
  "aggregations": {
    "active_categories": {
      "count": {
        "field": "category.active"
      }
    }
  }
});

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/category");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw");

xhr.send(data);
```


### jQuery
```javascript
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/category",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
  },
  "processData": false,
  "data": "{\"page\":1,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"category.name\",\"direction\":\"descending\"},{\"field\":\"category.metaTitle\",\"direction\":\"ascending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}],\"aggregations\":{\"active_categories\":{\"count\":{\"field\":\"category.active\"}}}}"
};

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

### NodeJS Native
```javascript
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "category"
  ],
  "headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.write(JSON.stringify({ page: 1,
  limit: 10,
  filter: 
   [ { type: 'nested',
       operator: 'OR',
       queries: [ { type: 'term', field: 'category.active', value: true } ] } ],
  term: 'A',
  sort: 
   [ { field: 'category.name', direction: 'descending' },
     { field: 'category.metaTitle', direction: 'ascending' } ],
  'post-filter': [ { type: 'term', field: 'category.active', value: true } ],
  aggregations: { active_categories: { count: { field: 'category.active' } } } }));
req.end();
```

### Go
```go
package main

import (
	"fmt"
	"strings"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/category"

	payload := strings.NewReader("{\"page\":1,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"category.name\",\"direction\":\"descending\"},{\"field\":\"category.metaTitle\",\"direction\":\"ascending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"category.active\",\"value\":true}],\"aggregations\":{\"active_categories\":{\"count\":{\"field\":\"category.active\"}}}}")

	req, _ := http.NewRequest("POST", url, payload)

	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```