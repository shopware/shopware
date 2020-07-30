[titleEn]: <>(Store api product routes)
[hash]: <>(article:store_api_product)

## Products
In this article we will show how you load product listings, searches and suggest searches via the API.

### Get a product listing
To get a product listing of a category you use the following route: `store-api.product.listing`
This route only needs the 'categoryId' parameter. 

If such a product listing is determined via the API, the settings of the corresponding sales channel take effect, which means that 
the standard aggregations are loaded automatically.

By default, the following aggregations are also loaded:
* `price` - Determines a `stats` aggregation for the prices 
* `rating` - Determines a `max` aggregation for the average product ratings
* `shipping-free` - Determines a `max` aggregation if there are free shipping products
* `manufacturer` - Determines an `entity` aggregation to determine all manufacturers of the listing
* `properties` - Determines an entity aggregation to determine all product properties that occur in the listing.

```
POST /store-api/v3/product-listing/256b32bb62c84ecd86ddcb16df87ef28

{
    "includes": {
		"product_listing_sorting": ["key"],
		"product_manufacturer": ["id", "name"],
		"price_aggregation": ["min", "max"],
		"product_group": ["id", "name", "options"],
		"product_group_option": ["id", "name"],
		"product": ["id", "name", "calculatedPrice", "cover"],
		"media": ["id", "url"],
		"product_media": ["media"],
		"calculated_price": ["unitPrice", "totalPrice"]
    }
}

{
    "sorting": "name-asc",
    "currentFilters": {
        "navigationId": "256b32bb62c84ecd86ddcb16df87ef28",
        "manufacturer": [],
        "properties": [],
        "shipping-free": null,
        "rating": null,
        "price": {
            "min": null,
            "max": null
        }
    },
    "page": 1,
    "limit": 24,
    "sortings": {
        "name-asc": {
            "key": "name-asc",
            "apiAlias": "product_listing_sorting"
        },
        "name-desc": {
            "key": "name-desc",
            "apiAlias": "product_listing_sorting"
        },
        "price-asc": {
            "key": "price-asc",
            "apiAlias": "product_listing_sorting"
        },
        "price-desc": {
            "key": "price-desc",
            "apiAlias": "product_listing_sorting"
        }
    },
    "total": 14,
    "aggregations": {
        "manufacturer": {
            "entities": [
                {
                    "name": "Ledner-Turner",
                    "id": "62808110075f46c8b6233205f5e1fea7",
                    "apiAlias": "product_manufacturer"
                },
                {
                    "name": "Emard, Erdman and Stracke",
                    "id": "44a000487c874ff0bd26e0b262935123",
                    "apiAlias": "product_manufacturer"
                }
            ],
            "apiAlias": "manufacturer_aggregation"
        },
        "price": {
            "min": "539",
            "max": "983",
            "apiAlias": "price_aggregation"
        },
        "shipping-free": {
            "max": null,
            "apiAlias": "shipping-free_aggregation"
        },
        "rating": {
            "max": "4",
            "apiAlias": "rating_aggregation"
        },
        "properties": {
            "entities": [
                {
                    "name": "textile",
                    "options": [
                        {
                            "name": "silk",
                            "id": "28b6e2296bbf4dd2a73ea1d0ae4d3bb6",
                            "apiAlias": "product_group_option"
                        }
                    ],
                    "id": "ac57256046234c298d6d7eb9c4f82305",
                    "apiAlias": "product_group"
                },
                {
                    "name": "width",
                    "options": [
                        {
                            "name": "17 mm",
                            "id": "640b60ad7d734901b27d08c4b403a3a1",
                            "apiAlias": "product_group_option"
                        },
                        {
                            "name": "9 mm",
                            "id": "a608a1ed380b46618629279b739ade76",
                            "apiAlias": "product_group_option"
                        }
                    ],
                    "id": "ef87796700384395be94a4b91c040d9f",
                    "apiAlias": "product_group"
                }
            ],
            "apiAlias": "properties_aggregation"
        }
    },
    "elements": [
        {
            "calculatedPrice": {
                "unitPrice": 375,
                "totalPrice": 375,
                "apiAlias": "calculated_price"
            },
            "name": "Awesome Iron CompuBooth",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/1d/1a/54/1587979070/14018bc6b227c0e5814589aaa04a6b51.jpg",
                    "id": "265d67b5213b4801a5ff77738bea79d0",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "fc54673b8ab44a2f8329de8f25c664c2",
            "apiAlias": "product"
        }
    ],
    "apiAlias": "product_listing"
}
```

#### Seo Filters
In addition to the standard API functions such as `filter` or `aggregations`, this route also supports seo filters, which are also available in the 
Shopware 6 storefront application:
* `min-price` - Short hand to set a minimum price filter
* `max-price` - Short hand to set a maximum price filter
* `rating` - Short hand to set a filter for the average rating of a product
* `shipping-free` - Short hand to filter only shipping free products
* `manufacturer` - Short hand to filter the products according to a list of manufacturers
* `properties` - Short hand to filter the products according to a list of properties
* `p` - Short hand for the `page` parameter
* `order` - Short hand for a sort that is registered in the system. The available sorts are contained in the response 

#### Behaviors
In addition to the seo filters and the standard API filters, this route also offers other parameters with which the result of the route
can be influenced. These parameters refer to the aggregations determined and are used for various purposes:

**`no-aggregations`** - No aggregations are loaded. This is used, for example, in the storefront if a filtering or sorting has taken place and then only the product list is updated.
**`reduce-aggregations`** - All `post-filters` are used as `filters`. This means that the aggregations will only contain values that would lead to a further result.
**`only-aggregations`** - This parameter sets the internal `limit` to `0`, so that no products are loaded and only the aggregations are returned. 
In the storefront this parameter is used to update the filters and in combination with the `reduce-aggregations` parameter to deactivate filters that would lead to an empty result.

### Get a product search
Product searches can be queried in the Store API via the route 'store-api.search'.
This route supports all features that are also supported in the 'store-api.product.listing'.
Unlike the `store-api.product.listing` the parameter `search` is required instead of the `categoryId`.
This parameter is a search term to be searched for.

```
POST /store-api/v3/search

{
	"search": "Awesome Iron",
    "includes": {
		"product_listing_sorting": ["key"],
		"product_manufacturer": ["id", "name"],
		"price_aggregation": ["min", "max"],
		"product_group": ["id", "name", "options"],
		"product_group_option": ["id", "name"],
		"product": ["id", "name", "calculatedPrice", "cover"],
		"media": ["id", "url"],
		"product_media": ["media"],
		"calculated_price": ["unitPrice", "totalPrice"]
    }
}


{
    "sorting": "score",
    "currentFilters": {
        "manufacturer": [],
        "properties": [],
        "shipping-free": null,
        "rating": null,
        "price": {
            "min": null,
            "max": null
        },
        "search": null
    },
    "page": 1,
    "limit": 24,
    "sortings": {
        "name-asc": {
            "key": "name-asc",
            "apiAlias": "product_listing_sorting"
        },
        "name-desc": {
            "key": "name-desc",
            "apiAlias": "product_listing_sorting"
        },
        "price-asc": {
            "key": "price-asc",
            "apiAlias": "product_listing_sorting"
        },
        "price-desc": {
            "key": "price-desc",
            "apiAlias": "product_listing_sorting"
        },
        "score": {
            "key": "score",
            "apiAlias": "product_listing_sorting"
        }
    },
    "total": 5,
    "aggregations": {
        "manufacturer": {
            "entities": [
                {
                    "name": "Zemlak and Sons",
                    "id": "9ca0c40d3cb441fd85ba2d3d63d8f2a3",
                    "apiAlias": "product_manufacturer"
                },
                {
                    "name": "Conn Group",
                    "id": "bfd0f18a316548c2bd92654e476761fe",
                    "apiAlias": "product_manufacturer"
                }
            ],
            "apiAlias": "manufacturer_aggregation"
        },
        "price": {
            "min": "775",
            "max": "974",
            "apiAlias": "price_aggregation"
        },
        "shipping-free": {
            "max": 0,
            "apiAlias": "shipping-free_aggregation"
        },
        "rating": {
            "max": 0,
            "apiAlias": "rating_aggregation"
        },
        "properties": {
            "entities": [
                {
                    "name": "color",
                    "options": [
                        {
                            "name": "goldenrod",
                            "id": "0bcf6622bcc94acc9abe7a42ce06b4a5",
                            "apiAlias": "product_group_option"
                        },
                        {
                            "name": "verylightgrey",
                            "id": "68b59078b78b42439d2b3797a3719942",
                            "apiAlias": "product_group_option"
                        }
                    ],
                    "id": "b5c9a96a2ac2470ca375a9d7c9652fa5",
                    "apiAlias": "product_group"
                },
                {
                    "name": "content",
                    "options": [
                        {
                            "name": "1 ml",
                            "id": "681ee54ab5ff489b87b46b6ceb67c53c",
                            "apiAlias": "product_group_option"
                        },
                        {
                            "name": "16 ml",
                            "id": "89ef65b0275d4000899956509e418e7a",
                            "apiAlias": "product_group_option"
                        },
                        {
                            "name": "9 ml",
                            "id": "c9eafa0fd9bf442cafea3e5a9c1487fd",
                            "apiAlias": "product_group_option"
                        }
                    ],
                    "id": "467068b268024754bf8ded18fa5cba1d",
                    "apiAlias": "product_group"
                }
            ],
            "apiAlias": "properties_aggregation"
        }
    },
    "elements": [
        {
            "calculatedPrice": {
                "unitPrice": 375,
                "totalPrice": 375,
                "apiAlias": "calculated_price"
            },
            "name": "Awesome Iron CompuBooth",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/1d/1a/54/1587979070/14018bc6b227c0e5814589aaa04a6b51.jpg",
                    "id": "265d67b5213b4801a5ff77738bea79d0",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "fc54673b8ab44a2f8329de8f25c664c2",
            "apiAlias": "product"
        },
        {
            "calculatedPrice": {
                "unitPrice": 32,
                "totalPrice": 32,
                "apiAlias": "calculated_price"
            },
            "name": "Awesome Silk Black Belt Barbie",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/1e/2f/90/1587979070/6ef4e4738c68ce8717bc97757691718d.jpg",
                    "id": "544b50110c8d43b0be368b983891cf33",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "69949e4c23634d51b3459f1a9d46e1bd",
            "apiAlias": "product"
        },
        {
            "calculatedPrice": {
                "unitPrice": 409,
                "totalPrice": 409,
                "apiAlias": "calculated_price"
            },
            "name": "Mediocre Iron Savvy Ass",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/a5/a6/36/1587979070/feed8f0bd4664ded6fec4d66c9e82fab.jpg",
                    "id": "f247d993aaa5408886dacef4afe5b88f",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "40f4bc2189e64b3895d57b3e453ee56d",
            "apiAlias": "product"
        }
    ],
    "apiAlias": "product_listing"
}
```

### Get a product suggest
To load a product suggest search, the route 'store-api.search.suggest' can be used.
This works in the same way as the `store-api.search` but no aggregations are loaded here.

```
POST /store-api/v3/search-suggest
{
	"search": "Awesome Iron",
    "includes": {
		"product_manufacturer": ["id", "name"],
		"product": ["id", "name", "calculatedPrice", "cover"],
		"media": ["id", "url"],
		"product_media": ["media"],
		"calculated_price": ["unitPrice", "totalPrice"]
    }
}


{
    "sorting": null,
    "currentFilters": [],
    "page": null,
    "limit": null,
    "sortings": [],
    "total": 5,
    "aggregations": [],
    "elements": [
        {
            "calculatedPrice": {
                "unitPrice": 375,
                "totalPrice": 375,
                "apiAlias": "calculated_price"
            },
            "name": "Awesome Iron CompuBooth",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/1d/1a/54/1587979070/14018bc6b227c0e5814589aaa04a6b51.jpg",
                    "id": "265d67b5213b4801a5ff77738bea79d0",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "fc54673b8ab44a2f8329de8f25c664c2",
            "apiAlias": "product"
        },
        {
            "calculatedPrice": {
                "unitPrice": 32,
                "totalPrice": 32,
                "apiAlias": "calculated_price"
            },
            "name": "Awesome Silk Black Belt Barbie",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/1e/2f/90/1587979070/6ef4e4738c68ce8717bc97757691718d.jpg",
                    "id": "544b50110c8d43b0be368b983891cf33",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "69949e4c23634d51b3459f1a9d46e1bd",
            "apiAlias": "product"
        },
        {
            "calculatedPrice": {
                "unitPrice": 409,
                "totalPrice": 409,
                "apiAlias": "calculated_price"
            },
            "name": "Mediocre Iron Savvy Ass",
            "cover": {
                "media": {
                    "url": "http://shopware.development/media/a5/a6/36/1587979070/feed8f0bd4664ded6fec4d66c9e82fab.jpg",
                    "id": "f247d993aaa5408886dacef4afe5b88f",
                    "apiAlias": "media"
                },
                "apiAlias": "product_media"
            },
            "id": "40f4bc2189e64b3895d57b3e453ee56d",
            "apiAlias": "product"
        }
    ],
    "apiAlias": "product_listing"
}
```
