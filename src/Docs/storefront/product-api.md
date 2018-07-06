- [Product storefront api](#product-storefront-api)
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

# Product storefront api

## Route overview
The product storefront api can be used to query product information that has been prepared for end customers.
The endpoint is available via `/storefront-api/product` and offers the following routes:
* `/storefront-api/product`
    * List request for product data with filter and sorting support
* `/storefront-api/product/{id} `
    * Detail request for product data 

## List route
The List Route supports both data filtering via GET parameter and POST parameter for more complex queries. Simple queries can be made via GET parameters.

* `/storefront-api/product?filter[product.active]=1`
    * Filtering active products only
* `/storefront-api/product?filter[product.active]=1&filter[product.name]=Test`
    * Filtering active products named "Test"
* `/storefront-api/product?sort=name`
    * Ascending sort by name
* `/storefront-api/product?sort=-name`
    * Descending sort by name
* `/storefront-api/product?term=Test`
    * Search for products which contains the term "test"

## Complex queries    
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
                {"type": "term", "field": "product.active", "value": true},
                {"type": "term", "field": "product.name", "value": "B"}
            ]
        }
    ],
    "term": "Test",
    "sort": [
        { "field": "product.name", "direction": "descending" },
        { "field": "product.metaTitle", "direction": "ascending" }
    ],
    "post-filter": [
        {"type": "term", "field": "product.active", "value": true}
    ],
    "aggregations": {
        "active_products": {
            "count": {"field": "product.active"}
        }
    }
}
```

## Result
A typical result of this route looks as follow:
```json
{
    "links": {
        "first": "http://shopware.development/storefront-api/product?page%5Boffset%5D=0&page%5Blimit%5D=1",
        "last": "http://shopware.development/storefront-api/product?page%5Boffset%5D=49&page%5Blimit%5D=1",
        "next": "http://shopware.development/storefront-api/product?page%5Boffset%5D=1&page%5Blimit%5D=1",
        "self": "http://shopware.development/storefront-api/product"
    },
    "meta": {
        "total": 50
    },
    "aggregations": {
        "active_products": {
            "count": "50"
        }
    },
    "data": [
        {
            "id": "b5719dba30e84f4187248ce0b75ca68b",
            "type": "product",
            "links": {
                "self": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "catalogId": "ffffffffffffffffffffffffffffffff",
                "parentId": null,
                "parentVersionId": null,
                "autoIncrement": 16,
                "active": true,
                "stock": 23413,
                "createdAt": null,
                "updatedAt": null,
                "manufacturerId": "ea2367de87d04461ac332236b66cb331",
                "productManufacturerVersionId": null,
                "unitId": null,
                "unitVersionId": null,
                "taxId": "4926035368e34d9fa695e017d7a231b9",
                "taxVersionId": null,
                "price": {
                    "net": 410.0840336134454,
                    "gross": 488,
                    "extensions": []
                },
                "listingPrices": [],
                "supplierNumber": null,
                "ean": null,
                "isCloseout": false,
                "minStock": null,
                "purchaseSteps": 1,
                "maxPurchase": null,
                "minPurchase": 1,
                "purchaseUnit": null,
                "referenceUnit": null,
                "shippingFree": false,
                "purchasePrice": null,
                "pseudoSales": null,
                "markAsTopseller": false,
                "sales": 0,
                "position": null,
                "weight": null,
                "width": null,
                "height": null,
                "length": null,
                "template": null,
                "allowNotification": false,
                "releaseDate": null,
                "categoryTree": [],
                "datasheetIds": [],
                "variationIds": [],
                "minDeliveryTime": 1,
                "maxDeliveryTime": 2,
                "restockTime": 1,
                "additionalText": null,
                "name": "Aerodynamic Aluminum Chair",
                "keywords": null,
                "description": "Qui hic suscipit velit ut delectus est. Corrupti adipisci blanditiis omnis est. Doloribus deserunt quis sequi suscipit ab. Aliquam eos porro provident corrupti veritatis incidunt eveniet.",
                "descriptionLong": "<html><head><title>Quae recusandae assumenda quidem sequi laborum nihil earum qui.</title></head><body><form action=\"example.net\" method=\"POST\"><label for=\"username\">repudiandae</label><input type=\"text\" id=\"username\"><label for=\"password\">fuga</label><input type=\"password\" id=\"password\"></form><ul><li>Provident velit mollitia et assumenda.</li><li>Illo.</li><li>Accusamus esse.</li><li>Voluptatibus dolores.</li><li>Voluptatem quia.</li><li>Officiis sapiente atque.</li><li>Non dolorem tempore.</li><li>Voluptatum aut.</li><li>Voluptatem ab.</li><li>Accusantium sed quaerat.</li><li>Aliquid.</li></ul></body></html>\n",
                "metaTitle": null,
                "packUnit": null
            },
            "relationships": {
                "parent": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/parent"
                    }
                },
                "children": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/children"
                    }
                },
                "tax": {
                    "data": {
                        "id": "4926035368e34d9fa695e017d7a231b9",
                        "type": "tax"
                    },
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/tax"
                    }
                },
                "manufacturer": {
                    "data": {
                        "id": "ea2367de87d04461ac332236b66cb331",
                        "type": "product_manufacturer"
                    },
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/manufacturer"
                    }
                },
                "unit": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/unit"
                    }
                },
                "media": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/media"
                    }
                },
                "priceRules": {
                    "data": [
                        {
                            "id": "024fcb9a4bec4eb192a47a2b9d36e962",
                            "type": "product_price_rule"
                        },
                        {
                            "id": "3ba8f379679f4f61816783c61082273f",
                            "type": "product_price_rule"
                        },
                        {
                            "id": "4d434a6191674bd0bdf167e467bee7b3",
                            "type": "product_price_rule"
                        },
                        {
                            "id": "8c6daa0762af44a4a20af418d246560b",
                            "type": "product_price_rule"
                        },
                        {
                            "id": "b2a406e94a61456b9ee021415a2785fe",
                            "type": "product_price_rule"
                        },
                        {
                            "id": "c21e892ae9bd458fa05fc857b5a4796b",
                            "type": "product_price_rule"
                        }
                    ],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/price-rules"
                    }
                },
                "services": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/services"
                    }
                },
                "datasheet": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/datasheet"
                    }
                },
                "categories": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/categories"
                    }
                },
                "categoriesRo": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/categories-ro"
                    }
                },
                "searchKeywords": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/search-keywords"
                    }
                },
                "cover": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/cover"
                    }
                },
                "configurators": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/configurators"
                    }
                },
                "variations": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/variations"
                    }
                },
                "catalog": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/catalog"
                    }
                },
                "canonicalUrl": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product/b5719dba30e84f4187248ce0b75ca68b/canonical-url"
                    }
                }
            }
        }
    ],
    "included": [
        {
            "id": "4926035368e34d9fa695e017d7a231b9",
            "type": "tax",
            "links": {
                "self": "http://shopware.development/api/v0/tax/4926035368e34d9fa695e017d7a231b9"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "rate": 19,
                "name": "19%",
                "createdAt": "2017-12-14T15:51:51+00:00",
                "updatedAt": null
            },
            "relationships": {
                "products": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/tax/4926035368e34d9fa695e017d7a231b9/products"
                    }
                },
                "areaRules": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/tax/4926035368e34d9fa695e017d7a231b9/area-rules"
                    }
                },
                "productServices": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/tax/4926035368e34d9fa695e017d7a231b9/product-services"
                    }
                }
            }
        },
        {
            "id": "ea2367de87d04461ac332236b66cb331",
            "type": "product_manufacturer",
            "links": {
                "self": "http://shopware.development/api/v0/product-manufacturer/ea2367de87d04461ac332236b66cb331"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "catalogId": "ffffffffffffffffffffffffffffffff",
                "mediaId": null,
                "mediaVersionId": null,
                "link": "http://wieland.com/aliquam-animi-animi-repudiandae-unde-officiis.html",
                "updatedAt": null,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "name": "Hanke",
                "description": null,
                "metaTitle": null,
                "metaDescription": null,
                "metaKeywords": null
            },
            "relationships": {
                "media": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-manufacturer/ea2367de87d04461ac332236b66cb331/media"
                    }
                },
                "products": {
                    "data": [],
                    "links": {
                        "related": "http://shopware.development/api/v0/product-manufacturer/ea2367de87d04461ac332236b66cb331/products"
                    }
                },
                "catalog": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-manufacturer/ea2367de87d04461ac332236b66cb331/catalog"
                    }
                }
            }
        },
        {
            "id": "024fcb9a4bec4eb192a47a2b9d36e962",
            "type": "product_price_rule",
            "links": {
                "self": "http://shopware.development/api/v0/product-price-rule/024fcb9a4bec4eb192a47a2b9d36e962"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "productId": "b5719dba30e84f4187248ce0b75ca68b",
                "productVersionId": null,
                "currencyId": "4c8eba11bd3546d786afbed481a6e665",
                "currencyVersionId": null,
                "ruleId": "08434785e57b444ea1e066e1e0a79bb7",
                "price": {
                    "net": 831.09243697479,
                    "gross": 989,
                    "extensions": []
                },
                "quantityStart": 1,
                "quantityEnd": 10,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "updatedAt": null
            },
            "relationships": {
                "product": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/024fcb9a4bec4eb192a47a2b9d36e962/product"
                    }
                },
                "currency": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/024fcb9a4bec4eb192a47a2b9d36e962/currency"
                    }
                },
                "rule": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/024fcb9a4bec4eb192a47a2b9d36e962/rule"
                    }
                }
            }
        },
        {
            "id": "3ba8f379679f4f61816783c61082273f",
            "type": "product_price_rule",
            "links": {
                "self": "http://shopware.development/api/v0/product-price-rule/3ba8f379679f4f61816783c61082273f"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "productId": "b5719dba30e84f4187248ce0b75ca68b",
                "productVersionId": null,
                "currencyId": "4c8eba11bd3546d786afbed481a6e665",
                "currencyVersionId": null,
                "ruleId": "5e198ddfac6d45fd8248c029d8003eb0",
                "price": {
                    "net": 197.47899159663865,
                    "gross": 235,
                    "extensions": []
                },
                "quantityStart": 11,
                "quantityEnd": null,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "updatedAt": null
            },
            "relationships": {
                "product": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/3ba8f379679f4f61816783c61082273f/product"
                    }
                },
                "currency": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/3ba8f379679f4f61816783c61082273f/currency"
                    }
                },
                "rule": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/3ba8f379679f4f61816783c61082273f/rule"
                    }
                }
            }
        },
        {
            "id": "4d434a6191674bd0bdf167e467bee7b3",
            "type": "product_price_rule",
            "links": {
                "self": "http://shopware.development/api/v0/product-price-rule/4d434a6191674bd0bdf167e467bee7b3"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "productId": "b5719dba30e84f4187248ce0b75ca68b",
                "productVersionId": null,
                "currencyId": "4c8eba11bd3546d786afbed481a6e665",
                "currencyVersionId": null,
                "ruleId": "e6375fa4ebfc4b5690735a61bad871ce",
                "price": {
                    "net": 357.98319327731093,
                    "gross": 426,
                    "extensions": []
                },
                "quantityStart": 11,
                "quantityEnd": null,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "updatedAt": null
            },
            "relationships": {
                "product": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/4d434a6191674bd0bdf167e467bee7b3/product"
                    }
                },
                "currency": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/4d434a6191674bd0bdf167e467bee7b3/currency"
                    }
                },
                "rule": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/4d434a6191674bd0bdf167e467bee7b3/rule"
                    }
                }
            }
        },
        {
            "id": "8c6daa0762af44a4a20af418d246560b",
            "type": "product_price_rule",
            "links": {
                "self": "http://shopware.development/api/v0/product-price-rule/8c6daa0762af44a4a20af418d246560b"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "productId": "b5719dba30e84f4187248ce0b75ca68b",
                "productVersionId": null,
                "currencyId": "4c8eba11bd3546d786afbed481a6e665",
                "currencyVersionId": null,
                "ruleId": "5e198ddfac6d45fd8248c029d8003eb0",
                "price": {
                    "net": 509.24369747899163,
                    "gross": 606,
                    "extensions": []
                },
                "quantityStart": 1,
                "quantityEnd": 10,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "updatedAt": null
            },
            "relationships": {
                "product": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/8c6daa0762af44a4a20af418d246560b/product"
                    }
                },
                "currency": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/8c6daa0762af44a4a20af418d246560b/currency"
                    }
                },
                "rule": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/8c6daa0762af44a4a20af418d246560b/rule"
                    }
                }
            }
        },
        {
            "id": "b2a406e94a61456b9ee021415a2785fe",
            "type": "product_price_rule",
            "links": {
                "self": "http://shopware.development/api/v0/product-price-rule/b2a406e94a61456b9ee021415a2785fe"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "productId": "b5719dba30e84f4187248ce0b75ca68b",
                "productVersionId": null,
                "currencyId": "4c8eba11bd3546d786afbed481a6e665",
                "currencyVersionId": null,
                "ruleId": "08434785e57b444ea1e066e1e0a79bb7",
                "price": {
                    "net": 159.6638655462185,
                    "gross": 190,
                    "extensions": []
                },
                "quantityStart": 11,
                "quantityEnd": null,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "updatedAt": null
            },
            "relationships": {
                "product": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/b2a406e94a61456b9ee021415a2785fe/product"
                    }
                },
                "currency": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/b2a406e94a61456b9ee021415a2785fe/currency"
                    }
                },
                "rule": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/b2a406e94a61456b9ee021415a2785fe/rule"
                    }
                }
            }
        },
        {
            "id": "c21e892ae9bd458fa05fc857b5a4796b",
            "type": "product_price_rule",
            "links": {
                "self": "http://shopware.development/api/v0/product-price-rule/c21e892ae9bd458fa05fc857b5a4796b"
            },
            "attributes": {
                "tenantId": "ffffffffffffffffffffffffffffffff",
                "versionId": null,
                "productId": "b5719dba30e84f4187248ce0b75ca68b",
                "productVersionId": null,
                "currencyId": "4c8eba11bd3546d786afbed481a6e665",
                "currencyVersionId": null,
                "ruleId": "e6375fa4ebfc4b5690735a61bad871ce",
                "price": {
                    "net": 821.0084033613446,
                    "gross": 977,
                    "extensions": []
                },
                "quantityStart": 1,
                "quantityEnd": 10,
                "createdAt": "2018-07-06T07:35:35+00:00",
                "updatedAt": null
            },
            "relationships": {
                "product": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/c21e892ae9bd458fa05fc857b5a4796b/product"
                    }
                },
                "currency": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/c21e892ae9bd458fa05fc857b5a4796b/currency"
                    }
                },
                "rule": {
                    "data": null,
                    "links": {
                        "related": "http://shopware.development/api/v0/product-price-rule/c21e892ae9bd458fa05fc857b5a4796b/rule"
                    }
                }
            }
        }
    ]
}
```


## Examples

### PHP
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/product",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => json_encode(array(
    'offset'=> 0,
    'limit'=> 10,
    'filter'=> [
        [
            'type'=> 'nested',
            'operator'=> 'OR',
            'queries'=> [
                ['type'=> 'term', 'field'=> 'product.active', 'value'=> true]
            ]
        ]
    ],
    'term'=> 'A',
    'sort'=> [
        [ 'field'=> 'product.name', 'direction'=> 'descending' ]
    ],
    'post-filter'=> [
        ['type'=> 'term', 'field'=> 'product.active', 'value'=> true]
    ],
    'aggregations'=> [
        'active_products'=> [
            'count'=> ['field'=> 'product.active']
        ]
    ]
  )),
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
    "Cache-Control: no-cache",
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
  http://shopware.development/storefront-api/product \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw' \
  -H 'Cache-Control: no-cache' \
  -H 'Content-Type: application/json' \
  -H 'Postman-Token: 38f86e88-3227-44dc-99f3-b503865ca74e' \
  -d '{"offset":0,"limit":10,"filter":[{"type":"nested","operator":"OR","queries":[{"type":"term","field":"product.active","value":true}]}],"term":"A","sort":[{"field":"product.name","direction":"descending"}],"post-filter":[{"type":"term","field":"product.active","value":true}],"aggregations":{"active_products":{"count":{"field":"product.active"}}}}'
```

### Python
```python
import http.client

conn = http.client.HTTPConnection("shopware,development")

payload = "{\"offset\":0,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"product.name\",\"direction\":\"descending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}],\"aggregations\":{\"active_products\":{\"count\":{\"field\":\"product.active\"}}}}"

headers = {
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
    'Cache-Control': "no-cache",
    'Postman-Token': "4e929411-799b-4f21-83ba-67a5d02b151f"
    }

conn.request("POST", "storefront-api,product", payload, headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

### Java
```
OkHttpClient client = new OkHttpClient();

MediaType mediaType = MediaType.parse("application/json");
RequestBody body = RequestBody.create(mediaType, "{\"offset\":0,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"product.name\",\"direction\":\"descending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}],\"aggregations\":{\"active_products\":{\"count\":{\"field\":\"product.active\"}}}}");
Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/product")
  .post(body)
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")
  .addHeader("Cache-Control", "no-cache")
  .addHeader("Postman-Token", "5cd8d6d6-4afe-4868-9ab5-a1dfe4307c6d")
  .build();

Response response = client.newCall(request).execute();
```

### Javascript
```javascript
var data = JSON.stringify({
  "offset": 0,
  "limit": 10,
  "filter": [
    {
      "type": "nested",
      "operator": "OR",
      "queries": [
        {
          "type": "term",
          "field": "product.active",
          "value": true
        }
      ]
    }
  ],
  "term": "A",
  "sort": [
    {
      "field": "product.name",
      "direction": "descending"
    }
  ],
  "post-filter": [
    {
      "type": "term",
      "field": "product.active",
      "value": true
    }
  ],
  "aggregations": {
    "active_products": {
      "count": {
        "field": "product.active"
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

xhr.open("POST", "http://shopware.development/storefront-api/product");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw");
xhr.setRequestHeader("Cache-Control", "no-cache");
xhr.setRequestHeader("Postman-Token", "5d2337ea-feb2-41c0-82f8-5712c63a6ca9");

xhr.send(data);
```


### jQuery
```javascript
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/product",
  "method": "POST",
  "headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
    "Cache-Control": "no-cache",
    "Postman-Token": "65a047db-71fe-44cc-a1d3-5a2622d599cb"
  },
  "processData": false,
  "data": "{\"offset\":0,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"product.name\",\"direction\":\"descending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}],\"aggregations\":{\"active_products\":{\"count\":{\"field\":\"product.active\"}}}}"
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
    "product"
  ],
  "headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
    "Cache-Control": "no-cache",
    "Postman-Token": "6623c810-eaf8-4b50-a9af-3b6e9ba212f9"
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

req.write(JSON.stringify({ offset: 0,
  limit: 10,
  filter: 
   [ { type: 'nested',
       operator: 'OR',
       queries: [ { type: 'term', field: 'product.active', value: true } ] } ],
  term: 'A',
  sort: [ { field: 'product.name', direction: 'descending' } ],
  'post-filter': [ { type: 'term', field: 'product.active', value: true } ],
  aggregations: { active_products: { count: { field: 'product.active' } } } }));
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

	url := "http://shopware.development/storefront-api/product"

	payload := strings.NewReader("{\"offset\":0,\"limit\":10,\"filter\":[{\"type\":\"nested\",\"operator\":\"OR\",\"queries\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}]}],\"term\":\"A\",\"sort\":[{\"field\":\"product.name\",\"direction\":\"descending\"}],\"post-filter\":[{\"type\":\"term\",\"field\":\"product.active\",\"value\":true}],\"aggregations\":{\"active_products\":{\"count\":{\"field\":\"product.active\"}}}}")

	req, _ := http.NewRequest("POST", url, payload)

	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")
	req.Header.Add("Cache-Control", "no-cache")
	req.Header.Add("Postman-Token", "df67ef91-f423-4e2c-aa58-a78c9a28c288")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```
