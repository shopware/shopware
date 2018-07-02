# Category storefront api

The category storefront api can be used to query category information that has been prepared for end customers.
The endpoint is available via `/storefront-api/category` and offers the following routes:
* `/storefront-api/category`
    * List request for category data with filter and sorting support
* `/storefront-api/category/{id} `
    * Detail request for category data 

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
    
If the list route is addressed by POST, as mentioned earlier, more complex queries can be sent as body:
```json
{
    "offset": 0,
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

A typical result of this route looks as follow:
```json
{
    "total": 4,
    "data": [
        { "name": "Test", "active": true }
    ],
    "aggregations": {
        "active_categories": { "count": "4" }
    }
}
```


  