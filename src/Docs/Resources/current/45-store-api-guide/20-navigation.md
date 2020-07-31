[titleEn]: <>(Store api navigation routes)
[hash]: <>(article:store_api_navigation)

## Navigation
You can use our store-api to fetch all the categories you need. In the following example we will show you how you can fetch all sorts of navigations, how you can load cms pages with and without categories.

### Get the main navigation
To get the main navigation of your Sales Channel you use the following route: `store-api.navigation`.

This route needs some parameters:
* `requestActiveId`: here you determine which category is currently the active category. Regardless of the `depth` parameter, children of this category will be loaded.
    * You can use aliases (`main-navigation`, `service-navigation`, `footer-navigation`) on this route. They help you to easily get common categories.
* `requestRootId`: this is where you enter your root category of your Sales Channel.
    * Additionally, you can use the same aliases here as you can on the `requestActiveId` parameter.
* `buildTree`: when setting this parameter to `true` the api returns the categories in a tree like format.
* `depth`: determines how many layers of categories should get loaded.

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).
```
POST /store-api/v3/navigation/main-navigation/main-navigation

{
    "includes": {
        "category": ["id", "name", "children"]
    },
    "buildTree": true,
    "depth": 3
}

[
    {
        "name": "Computers",
        "children": [
            {
                "name": "Electronics & Computers",
                "children": [],
                "id": "0517aa68122448818baed0be8be86be3",
                "apiAlias": "category"
            }
        ]
    }
]
```

### Get the service menu

For this example we use the same route (`store-api.navigation`), so if you need to see which parameters you can use check the example above.

Thanks to the aliases (`service-navigation`) for the categories you easily can fetch data of the service menu.

Beware, that your Sales Channel has a service navigation assigned to it, otherwise you won't get the expected result.

```
POST /store-api/v3/navigation/service-navigation/service-navigation

{
    "includes": {
        "category": [
            "id",
            "parentId",
            "name"
        ]
    }
}

[
    {
        "parentId": "f597740041bf459ea1ee7f9a61784adc",
        "name": "Press",
        "id": "ad5d1b0f5dc149d4a1a7324433f25aa9",
        "apiAlias": "category"
    },
    {
        "parentId": "f597740041bf459ea1ee7f9a61784adc",
        "name": "Contact",
        "id": "b76a31dc6c5a405e8295724fd54bdb9f",
        "apiAlias": "category"
    }
]
```

### Get the footer navigation

To get the footer navigation we're using the same route (`store-api.navigation`) which is already used in the two examples above. We now use the `footer-navigation` alias to get the footer navigation.

Note, that your Sales Channel needs a footer-navigation attached to it, otherwise you won't get the data you expected to get.

```
POST /store-api/v3/navigation/footer-navigation/footer-navigation

{
    "includes": {
        "category": [
            "parentId",
            "children",
            "active",
            "name"
        ]
    },
    "buildTree": true,
    "depth": 2
}

[
    {
        "parentId": "ef79728e04c949d784d6186ee89f0dd9",
        "name": "Legal",
        "active": true,
        "children": [
            {
                "parentId": "387344b56f824a009204a50d7aa12761",
                "name": "Impress",
                "active": true,
                "children": [],
                "apiAlias": "category"
            },
            {
                "parentId": "387344b56f824a009204a50d7aa12761",
                "name": "Contact",
                "active": true,
                "children": [],
                "apiAlias": "category"
            }
        ],
        "apiAlias": "category"
    }
]
```

### Get cms page

To fetch an cms page via the api you can use this route `store-api.cms.detail`.

This route has the following parameters:
* `id`: Here you enter the id of the cms page that you want to fetch

In this route only the data configured in the CMS page is loaded. This route should be used when loading static CMS pages such as landing pages.

If the cms page contains a product listing element, this route supports all parameters of the [store-api.product.listing](./30-products.md). route.

```
POST /store-api/v3/cms/da05c76975104f39a9f283b0b64db930

{
    "includes": {
        "cms_page": ["name", "id", "sections"],
        "cms_page_section": ["type", "blocks"],
        "cms_page_block": ["type", "slots"],
        "cms_page_slot": ["id", "data"]
    }
}

{
    "name": "Right of rescission",
    "sections": [
        {
            "type": "default",
            "blocks": [
                {
                    "type": "text",
                    "slots": [
                        {
                            "data": {
                                "content": "<h2>Right of rescission</h2><p>Lorem ipsum dolor sit amet... </p>",
                                "apiAlias": "cms_text"
                            },
                            "id": "b5053f6a571f4bb5b109fe7a39f3c542",
                            "apiAlias": "cms_page_slot"
                        }
                    ],
                    "apiAlias": "cms_page_block"
                }
            ],
            "apiAlias": "cms_page_section"
        }
    ],
    "id": "da05c76975104f39a9f283b0b64db930",
    "apiAlias": "cms_page"
}

```


#### Get cms page with category

If you want to load a cms page with a category you can use teh following route: `store-api.category.detail`
In contrast to the `/cms/{id}` route, this route also considers the category settings of the cms page.

This route needs one parameter:
* `navigationId`: the id of the navigation you want to fetch

Note, that you cannot use the api aliases like: `main-navigation`, 'footer-navigation', etc...
This route supports an alias `home` to load the home page of the sales channel.

If the cms page contains a product listing element, this route supports all parameters of the [store-api.product.listing](./30-products.md). route.

```
POST /store-api/v3/category/04cfc07532344f938d1c88735b54281e

{
    "includes": {
        "category": ["parentId", "breadcrumb", "active", "cmsPage"],
        "cms_page": ["name", "id", "sections"],
        "cms_page_section": ["type", "blocks"],
        "cms_page_block": ["type", "slots"],
        "cms_page_slot": ["id", "data"],
        "cms_image": ["mediaId"]
    }
}

{
    "parentId": null,
    "breadcrumb": [
        "Home"
    ],
    "active": true,
    "cmsPage": {
        "name": "Home page",
        "sections": [
            {
                "type": "default",
                "blocks": [
                    {
                        "type": "text",
                        "slots": [
                            {
                                "data": {
                                    "content": "<h2>Lorem Ipsum dolor sit amet</h2>\n <p>Lorem ipsum ... dolor sit amet.</p>",
                                    "apiAlias": "cms_text"
                                },
                                "id": "6079136addee484691339f3d74d21437",
                                "apiAlias": "cms_page_slot"
                            }
                        ],
                        "apiAlias": "cms_page_block"
                    },
                    {
                        "type": "image",
                        "slots": [
                            {
                                "data": {
                                    "mediaId": "1d4c625c409247dab961219deb9a9ebf",
                                    "apiAlias": "cms_image"
                                },
                                "id": "35d56c05e0414f748730ffae1b0f4010",
                                "apiAlias": "cms_page_slot"
                            }
                        ],
                        "apiAlias": "cms_page_block"
                    }
                ],
                "apiAlias": "cms_page_section"
            }
        ],
        "id": "da8a3bba124e44f2bebfe1b10bebd932",
        "apiAlias": "cms_page"
    },
    "apiAlias": "category"
}
```
