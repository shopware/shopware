[titleEn]: <>(Store api product routes)
[hash]: <>(article:store_api_product)

### Searching products

If you want to fetch data about products you have to use the following route: `store-api.search`

This route accepts one parameter:
* `search`: this is the search term

```
POST /store-api/v1/search

{
    "search": "Vintage"
}

// Reponse fehlt
```

### Search suggest

To ... you can use this route: `store-api.search.suggest`

This route needs one parameter:
* `search`: this is the search term 

```
POST /store-api/v1/search-suggest

{
    "search": "Bag"
}

// Response fehlt
```

### Product listig

You can fetch a product listing with this route: `store-api.product.listing`

This route accepts one parameter:
* `categoryId`: id of a category

```
POST /store-api/v1/product-listing/2a7967bc33af49F2a9f76f11e2991c5c

{
    "includes": {
        "product_listing": [
            "sorting",
            "total",
            "aggregations"
        ]
    }
}

{
    "sorting": "name-asc",
    "total": 3,
    "aggregations": {
        "manufacturer": {
            "entities": [
                {
                    "mediaId": null,
                    "name": "Harvey LLC",
                    "link": "https://leannon.net/ducimus-est-ipsum-nam-voluptatem-alias.html",
                    "description": null,
                    "media": null,
                    "products": null,
                    "_uniqueIdentifier": "c4f0c25332f140c28a6618c27b10c3a7",
                    "versionId": "0fa91ce3e96a4bc2be4bd9ce752c3425",
                    "createdAt": "2020-04-20T13:24:11+00:00",
                    "id": "c4f0c25332f140c28a6618c27b10c3a7",
                    "apiAlias": "product_manufacturer"
                }
            ],
            "apiAlias": "manufacturer_aggregation"
        },
        "price": {
            "min": "304",
            "max": "917",
            "avg": 691,
            "sum": 2073,
            "apiAlias": "price_aggregation"
        }
    },
    "apiAlias": "product_listing"
}
```

