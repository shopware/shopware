---
title: Deprecated composite search, use administration search instead
issue: NEXT-16926
flag: FEATURE_NEXT_6040
---
# Core
*  Deprecated the class `\Shopware\Core\Framework\DataAbstractionLayer\Search\CompositeEntitySearcher`
*  Deprecated service tag `shopware.composite_search.definition` as we are no longer using it
___
# Administration
*  Added new class `\Shopware\Administration\Service\AdminSearcher`
*  Added new controller `\Shopware\Administration\Controller\AdminSearchController`
*  Deprecated method `SearchApiService::search` in `src/core/service/api/search.api.service.js` which will be replaced by `searchQuery` method in the same service
*  Added a new method `SearchApiService::searchQuery` in `src/core/service/api/search.api.service.js`
___
# API
*  Deprecated `api.composite.search` endpoint
*  Added a new endpoint POST `api.admin.search` which can received a nested array of criteria in request payload which keys are the criteria's entity name
___
# Upgrade Information

## Deprecated composite search api

The composite search api endpoint `api.composite.search` will be deprecated in the next major version. For the replacement we introduce a new endpoint in `Administration`: POST `api/_admin/search`

With this new endpoint you need to define which entities you want to search and its own criteria in the request body

### Before

Request:
```
[GET|POST] /api/_search?term=test&limit=25
```

Response
```json
{
    "data":[
        {"entity":"landing_page","total":0,"entities":[...]},
        {"entity":"order","total":0,"entities":[...]},
        {"entity":"customer","total":0,"entities":[...},
        {"entity":"product","total":0,"entities":[...]},
        {"entity":"category","total":0,"entities":[...]},
        {"entity":"media","total":0,"entities":...]},
        {"entity":"product_manufacturer","total":0,"entities":[...]},
        {"entity":"tag","total":0,"entities":[...]},
        {"entity":"cms_page","total":0,"entities":[...]}
    ]
}
```

### After

Request
```
[POST] /api/_admin/search

Body

{
    "product": {
        "page": 1,
        "limit": 25,
        "term": "test",
    },
    "category": {
        "page": 1,
        "limit": 25,
        "query": {...},
    },
    "custom_entity": {
        "page": 1,
        "limit": 25,
        "query": {...},
    },
}
```

Response
```json
{
    "data":{
        "product": {"total":0,"data":[...]},
        "category": {"total":0,"data":[...]},
        "custom_entity": {"total":0,"data":...]},
        // Or if the user do not have the read privileges within their request filter/query/associations...
        "customer": {
            "status": "403",
            "code": "FRAMEWORK__MISSING_PRIVILEGE_ERROR",
            "title": "Forbidden",
            "detail": "{'message':'Missing privilege','missingPrivileges':['customer:read']}",
            "meta": {
                 "parameters": []
            }
        }
    }
}
```

## Deprecated SearchApiService::search in administration

With the same reason, the `SearchApiService::search` in `src/core/service/api/search.api.service.js` will be replaced with `SearchApiService::searchByQuery`

### Usage

```js
const productCriteria = new Criteria();
const manufacturerCriteria = new Criteria();
productCriteria.addQuery(Criteria.contains('name', searchTerm), 5000);
manufacturerCriteria.addQuery(Criteria.contains('name', searchTerm), 5000);

const queries = { product: productCriteria, product_manufacturer: manufacturerCriteria };

this.searchService.searchQuery(queries).then((response) => {
    ...
});
```
