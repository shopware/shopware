[titleEn]: <>(Admin API usage)
[hash]: <>(article:api_admin_usage)

This guide describes the default usage with the default schema, which almost all resources are using.
There are extended read possibilities for a few entities. Read [here](./040-admin-extended-read.md) for more information about that.

## Usage

The examples use the simple JSON format for brevity.
To get the examples running, you have to set the Authorization and Accept Header as shown below:

```javascript
const headers = { 
    "Authorization": "Bearer " + token,
    "Accept": "application/json"
};
fetch(`${baseUrl}/api/v3/product`, { headers })
    .then((response) => response.json())
    .then((products) => console.log('Products', products));
```

### GET /api/v3/category?limit=1

Get a list of categories with a limit of 1.

**Response body**:
```json
{
    "total": 67,
    "data": [
        {
            "parentId": "706f3eef52db489eab8ba4a2a8e0b68e",
            "catalogId": "20080911ffff4fffafffffff19830531",
            "autoIncrement": 17,
            "mediaId": null,
            "name": "Baby, Clothing & Toys",
            "path": "|706f3eef52db489eab8ba4a2a8e0b68e|",
            "position": 0,
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
            "createdAt": "2018-09-14T09:38:35.324+02:00",
            "updatedAt": null,
            "pathNames": "||",
            "metaKeywords": null,
            "metaTitle": null,
            "metaDescription": null,
            "cmsHeadline": null,
            "cmsDescription": null,
            "parent": null,
            "children": null,
            "translations": null,
            "media": null,
            "products": null,
            "catalog": null,
            "nestedProducts": null,
            "id": "215d2775df0a46caba5f7edbb233b7ff",
            "extensions": {
                "translated": {
                    "name": true,
                    "pathNames": true,
                    "metaKeywords": false,
                    "metaTitle": false,
                    "metaDescription": false,
                    "cmsHeadline": false,
                    "cmsDescription": false
                },
                "search": {
                    "primary_key": "215d2775df0a46caba5f7edbb233b7ff"
                }
            },
            "versionId": null,
            "parentVersionId": null,
            "mediaVersionId": null
        }
    ],
    "aggregations": []
}
```
 

### GET /api/v3/category/{id}

Get a single category.

**Response body**:
```json
{
    "data": {
        "parentId": "706f3eef52db489eab8ba4a2a8e0b68e",
        "catalogId": "20080911ffff4fffafffffff19830531",
        "autoIncrement": 17,
        "mediaId": null,
        "name": "Baby, Clothing & Toys",
        "path": "|706f3eef52db489eab8ba4a2a8e0b68e|",
        "position": 0,
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
        "createdAt": "2018-09-14T09:38:35.542+02:00",
        "updatedAt": null,
        "pathNames": "||",
        "metaKeywords": null,
        "metaTitle": null,
        "metaDescription": null,
        "cmsHeadline": null,
        "cmsDescription": null,
        "parent": null,
        "children": null,
        "translations": null,
        "media": null,
        "products": null,
        "catalog": null,
        "nestedProducts": null,
        "id": "215d2775df0a46caba5f7edbb233b7ff",
        "extensions": {
            "translated": {
                "name": true,
                "pathNames": true,
                "metaKeywords": false,
                "metaTitle": false,
                "metaDescription": false,
                "cmsHeadline": false,
                "cmsDescription": false
            }
        },
        "versionId": null,
        "parentVersionId": null,
        "mediaVersionId": null
    }
}
```

### POST /api/v3/category

Add a new category.

**Request body:**
```json
{
    "name": "New category"
}
```

**Response:**

    Status 204 No Content
    Location: http://localhost:8000/api/v3/category/20080911ffff4fffafffffff19830531
 

### PATCH /api/v3/category/{id}

Change attributes of the category.

**Request body:**
```json
{
    "name": "Changed category name"
}
```
**Response:**

    Status 204 No Content
    Location: http://localhost:8000/api/v3/category/20080911ffff4fffafffffff19830531

### DELETE /api/v3/category/{id}

Delete the category.

**Response:**

    Status 204 No Content

### GET /api/v3/category/{id}/products?limit=1

Get a list of products belonging to the category.
```json
{
    "total": 2,
    "data": [
        {
            "parentId": null,
            "catalogId": "20080911ffff4fffafffffff19830531",
            "autoIncrement": 213,
            "taxId": "7ad9535e9ff04f04a671ae07470e572d",
            "manufacturerId": "c4da48bfcdca41f38969ac78f13353e2",
            "unitId": null,
            "active": true,
            "price": {
                "net": 181.51260504201682,
                "gross": 216,
                "linked": true,
                "extensions": []
            },
            "supplierNumber": null,
            "ean": null,
            "stock": 977352908,
            "minDeliveryTime": 1,
            "maxDeliveryTime": 2,
            "restockTime": 1,
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
            "createdAt": "2018-09-14T09:38:37.192+02:00",
            "updatedAt": null,
            "categoryTree": [],
            "optionIds": [],
            "propertyIds": [],
            "additionalText": null,
            "name": "Aerodynamic Rubber Essentia",
            "keywords": null,
            "description": "At porro minima est alias hic beatae. Consequatur molestias voluptatem odit sit ut aliquam non.",
            "descriptionLong": "<h3>et magni voluptatem quam laudantium magni adipisci hic perspiciatis eos</h3><br/><h4>et perferendis hic ut sapiente asperiores et ea</h4><br/><h3>inventore et quod incidunt laudantium ea vitae ipsum officia qui</h3><br/><h4>quam nihil assumenda</h4><br/><h3>voluptas ut porro molestiae</h3><br/><h3>consequatur dolor</h3><br/><i>libero facere unde</i><br/><h2>qui</h2><br/><h3>omnis magnam ea rem non</h3><br/><h2>necessitatibus dolorem</h2><br/>",
            "metaTitle": null,
            "packUnit": null,
            "tax": {
                "taxRate": 19,
                "name": "19%",
                "createdAt": "2018-09-14T09:29:16.984+02:00",
                "updatedAt": null,
                "areaRules": null,
                "products": null,
                "productServices": null,
                "id": "7ad9535e9ff04f04a671ae07470e572d",
                "extensions": [],
                "versionId": null
            },
            "manufacturer": {
                "mediaId": null,
                "catalogId": "20080911ffff4fffafffffff19830531",
                "name": "Niemann",
                "link": "https://keil.net/tenetur-beatae-consequatur-dolor-aut.html",
                "updatedAt": null,
                "createdAt": "2018-09-14T09:38:35.442+02:00",
                "description": null,
                "metaTitle": null,
                "metaDescription": null,
                "metaKeywords": null,
                "media": null,
                "translations": null,
                "products": null,
                "catalog": null,
                "id": "c4da48bfcdca41f38969ac78f13353e2",
                "extensions": {
                    "translated": {
                        "name": true,
                        "description": false,
                        "metaTitle": false,
                        "metaDescription": false,
                        "metaKeywords": false
                    }
                },
                "versionId": null,
                "mediaVersionId": null
            },
            "unit": null,
            "prices": [],
            "listingPrices": [],
            "cover": {
                "productId": "34ece98ba27a4e6f8a054887cb009fd1",
                "mediaId": "e3c6f1e1faac418d9c56a5328bb287c1",
                "catalogId": "20080911ffff4fffafffffff19830531",
                "position": 1,
                "createdAt": "2018-09-14T09:38:37.592+02:00",
                "updatedAt": null,
                "media": {
                    "catalogId": "20080911ffff4fffafffffff19830531",
                    "userId": null,
                    "mimeType": "image/jpeg",
                    "fileExtension": "jpg",
                    "fileSize": 30030,
                    "name": "Product image of Aerodynamic Rubber Essentia",
                    "metaData": {
                        "rawMetadata": {
                            "Shopware\\Core\\Content\\Media\\Metadata\\MetadataLoader\\GetId3Loader": {
                                "GETID3_VERSION": "1.9.15-201709291043",
                                "filesize": 30030,
                                "filepath": "/app/custom/plugins/SwagPlayground/Resources/media/image",
                                "filename": "socken_1280x1280@2x.jpg",
                                "filenamepath": "/app/custom/plugins/SwagPlayground/Resources/media/image/socken_1280x1280@2x.jpg",
                                "avdataoffset": 0,
                                "avdataend": 30030,
                                "fileformat": "jpg",
                                "video": {
                                    "dataformat": "jpg",
                                    "lossless": false,
                                    "bits_per_sample": 24,
                                    "pixel_aspect_ratio": 1,
                                    "resolution_x": 1280,
                                    "resolution_y": 1280,
                                    "compression_ratio": 0.006109619140625
                                },
                                "encoding": "UTF-8",
                                "mime_type": "image/jpeg"
                            }
                        },
                        "typeName": "image",
                        "type": {
                            "width": 1280,
                            "height": 1280,
                            "extensions": []
                        },
                        "extensions": []
                    },
                    "createdAt": "2018-09-14T09:38:37.333+02:00",
                    "updatedAt": "2018-09-14T09:38:58.590+02:00",
                    "description": null,
                    "url": "http://localhost:8000/media/a9/83/d1/e3c6f1e1faac418d9c56a5328bb287c1.jpg",
                    "user": null,
                    "translations": null,
                    "categories": null,
                    "productManufacturers": null,
                    "productMedia": null,
                    "catalog": null,
                    "thumbnails": [
                        {
                            "width": 140,
                            "height": 140,
                            "highDpi": false,
                            "url": "http://localhost:8000/thumbnail/a9/83/d1/e3c6f1e1faac418d9c56a5328bb287c1_140x140.jpg",
                            "mediaId": "e3c6f1e1faac418d9c56a5328bb287c1",
                            "media": null,
                            "createdAt": "2018-09-14T09:38:58.435+02:00",
                            "updatedAt": null,
                            "id": "1b6147466c30453599e30af7bb11e3cf",
                            "extensions": [],
                            "mediaVersionId": null
                        },
                        {
                            "width": 300,
                            "height": 300,
                            "highDpi": false,
                            "url": "http://localhost:8000/thumbnail/a9/83/d1/e3c6f1e1faac418d9c56a5328bb287c1_300x300.jpg",
                            "mediaId": "e3c6f1e1faac418d9c56a5328bb287c1",
                            "media": null,
                            "createdAt": "2018-09-14T09:38:58.102+02:00",
                            "updatedAt": null,
                            "id": "6a3dcd659d174f6b9051d6211cf3020c",
                            "extensions": [],
                            "mediaVersionId": null
                        }
                    ],
                    "id": "e3c6f1e1faac418d9c56a5328bb287c1",
                    "extensions": {
                        "translated": {
                            "description": false,
                            "name": true
                        }
                    },
                    "versionId": null
                },
                "product": null,
                "catalog": null,
                "id": "4d4fb856601943a49730d285a5193f07",
                "extensions": [],
                "versionId": null,
                "productVersionId": null,
                "mediaVersionId": null
            },
            "parent": null,
            "children": null,
            "media": null,
            "searchKeywords": null,
            "translations": null,
            "categories": null,
            "properties": null,
            "variations": null,
            "configuratorSettings": null,
            "services": null,
            "categoriesRo": null,
            "catalog": null,
            "coverId": "4d4fb856601943a49730d285a5193f07",
            "id": "34ece98ba27a4e6f8a054887cb009fd1",
            "extensions": {
                "inherited": {
                    "manufacturerId": false,
                    "productManufacturerVersionId": false,
                    "unitId": true,
                    "taxId": false,
                    "taxVersionId": false,
                    "coverId": false,
                    "productMediaVersionId": true,
                    "price": false,
                    "listingPrices": true,
                    "supplierNumber": true,
                    "ean": true,
                    "isCloseout": false,
                    "minStock": true,
                    "purchaseSteps": false,
                    "maxPurchase": true,
                    "minPurchase": false,
                    "purchaseUnit": true,
                    "referenceUnit": true,
                    "shippingFree": false,
                    "purchasePrice": true,
                    "pseudoSales": true,
                    "markAsTopseller": true,
                    "sales": false,
                    "position": true,
                    "weight": true,
                    "width": true,
                    "height": true,
                    "length": true,
                    "template": true,
                    "allowNotification": true,
                    "releaseDate": true,
                    "categoryTree": true,
                    "propertyIds": true,
                    "minDeliveryTime": false,
                    "maxDeliveryTime": false,
                    "restockTime": false,
                    "additionalText": true,
                    "name": false,
                    "keywords": true,
                    "description": false,
                    "descriptionLong": false,
                    "metaTitle": true,
                    "packUnit": true,
                    "media": false,
                    "prices": false,
                    "services": false,
                    "properties": false,
                    "categories": false
                },
                "translated": {
                    "additionalText": false,
                    "name": true,
                    "keywords": false,
                    "description": true,
                    "descriptionLong": true,
                    "metaTitle": false,
                    "packUnit": false
                },
                "search": {
                    "primary_key": "34ece98ba27a4e6f8a054887cb009fd1"
                }
            },
            "versionId": null,
            "parentVersionId": null,
            "productManufacturerVersionId": null,
            "unitVersionId": null,
            "taxVersionId": null,
            "productMediaVersionId": null
        }
    ],
    "aggregations": []
}
```

### GET /api/v3/product?associations[media][]&limit=1

Not all associations are loaded by default if you request an entity.
If you want to load product images with your product, add the `associations` parameter.
List of products with their media associations limited to one.

```json
{
    "total": 60,
    "data": [
        {
            "parentId": null,
            "childCount": 0,
            "autoIncrement": 33,
            "taxId": "26c8e711050e4f33afcf19dd23660e13",
            "manufacturerId": "46c829bc98424f22bf8b25327a7411ba",
            "unitId": null,
            "active": true,
            "price": [
                {
                    "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                    "net": 417.64705882352945,
                    "gross": 497,
                    "linked": true,
                    "extensions": []
                }
            ],
            "manufacturerNumber": null,
            "ean": null,
            "productNumber": "SW10032",
            "stock": 20,
            "availableStock": 8,
            "available": true,
            "deliveryTimeId": null,
            "deliveryTime": null,
            "restockTime": 3,
            "isCloseout": false,
            "purchaseSteps": 1,
            "maxPurchase": null,
            "minPurchase": 1,
            "purchaseUnit": null,
            "referenceUnit": null,
            "shippingFree": false,
            "purchasePrice": null,
            "markAsTopseller": null,
            "weight": null,
            "width": null,
            "height": null,
            "length": null,
            "releaseDate": null,
            "categoryTree": [
                "b0504893db66451ab59293d5716bdde4",
                "77e9c5ad415c419c9e523fb17e6f7e3a"
            ],
            "optionIds": null,
            "propertyIds": [
                "012898efd28d4ab18866b9e277deb273",
                "08b5071307c341e8aba2de8319093e37"
            ],
            "additionalText": null,
            "name": "Practical Wool CompuBooth",
            "keywords": null,
            "description": "Ut ipsam labore dolore non. Illo nesciunt porro et qui voluptatem et eius. Eum dolore aut modi sit sint voluptatem veritatis. Cupiditate voluptas in ut nam alias temporibus.",
            "metaTitle": null,
            "packUnit": null,
            "variantRestrictions": null,
            "configuratorGroupConfig": null,
            "tax": {
                "taxRate": 19,
                "name": "19%",
                "products": null,
                "customFields": null,
                "_uniqueIdentifier": "26c8e711050e4f33afcf19dd23660e13",
                "versionId": null,
                "translated": [],
                "createdAt": "2019-07-15T06:44:32.534+00:00",
                "updatedAt": null,
                "extensions": {
                    "internal_mapping_storage": {
                        "_uniqueIdentifier": null,
                        "versionId": null,
                        "translated": [],
                        "createdAt": null,
                        "updatedAt": null,
                        "extensions": []
                    }
                },
                "id": "26c8e711050e4f33afcf19dd23660e13"
            },
            "manufacturer": null,
            "unit": null,
            "prices": [],
            "listingPrices": [
                {
                    "currencyId": "76f305fcfbf14d7fbf7e2d053f9042e0",
                    "ruleId": "75d913bfe7d444e7aa36d10a3801ed3d",
                    "from": {
                        "currencyId": "76f305fcfbf14d7fbf7e2d053f9042e0",
                        "net": 360.11,
                        "gross": 428.53,
                        "linked": true,
                        "extensions": []
                    },
                    "to": {
                        "currencyId": "76f305fcfbf14d7fbf7e2d053f9042e0",
                        "net": 954.39,
                        "gross": 1135.72,
                        "linked": true,
                        "extensions": []
                    },
                    "extensions": []
                }
            ],
            "cover": null,
            "parent": null,
            "children": null,
            "media": [
                {
                    "productId": "02bd7ff16e3c41c8ac79ac562522d3f9",
                    "mediaId": "b3611a5953af43dbab170fbb3b038a8f",
                    "position": 1,
                    "media": {
                        "userId": null,
                        "mimeType": "image\/jpeg",
                        "fileExtension": "jpg",
                        "fileSize": 14673,
                        "title": null,
                        "metaData": {
                            "type": 2,
                            "width": 624,
                            "height": 531
                        },
                        "mediaType": {
                            "name": "IMAGE",
                            "flags": [],
                            "extensions": []
                        },
                        "uploadedAt": "2019-07-15T06:44:51.943+00:00",
                        "alt": null,
                        "url": "http:\/\/shopware.local\/media\/43\/b3\/53\/1563173091\/69baf3fb3d3669519ace29782e4ccd60.jpg",
                        "fileName": "69baf3fb3d3669519ace29782e4ccd60",
                        "user": null,
                        "translations": null,
                        "categories": null,
                        "productManufacturers": null,
                        "productMedia": null,
                        "avatarUser": null,
                        "thumbnails": [],
                        "mediaFolderId": "fcf3facaf4834b1595e3664c9c554f16",
                        "mediaFolder": null,
                        "hasFile": true,
                        "private": false,
                        "propertyGroupOptions": null,
                        "mailTemplateMedia": null,
                        "customFields": null,
                        "tags": null,
                        "documentBaseConfigs": null,
                        "shippingMethods": null,
                        "paymentMethods": null,
                        "productConfiguratorSettings": null,
                        "orderLineItems": null,
                        "cmsBlocks": null,
                        "cmsPages": null,
                        "documents": null,
                        "_uniqueIdentifier": "b3611a5953af43dbab170fbb3b038a8f",
                        "versionId": null,
                        "translated": {
                            "alt": null,
                            "title": null,
                            "customFields": []
                        },
                        "createdAt": "2019-07-15T06:44:51.434+00:00",
                        "updatedAt": "2019-07-15T06:44:51.434+00:00",
                        "extensions": {
                            "internal_mapping_storage": {
                                "_uniqueIdentifier": null,
                                "versionId": null,
                                "translated": [],
                                "createdAt": null,
                                "updatedAt": null,
                                "extensions": []
                            }
                        },
                        "id": "b3611a5953af43dbab170fbb3b038a8f"
                    },
                    "product": null,
                    "customFields": null,
                    "_uniqueIdentifier": "0592e61d824c4d34992b7a2fa3fcb532",
                    "versionId": "0fa91ce3e96a4bc2be4bd9ce752c3425",
                    "translated": [],
                    "createdAt": "2019-07-15T06:45:02.397+00:00",
                    "updatedAt": null,
                    "extensions": [],
                    "id": "0592e61d824c4d34992b7a2fa3fcb532",
                    "productVersionId": "0fa91ce3e96a4bc2be4bd9ce752c3425"
                }
            ],
            "searchKeywords": null,
            "translations": null,
            "categories": null,
            "tags": null,
            "properties": null,
            "options": null,
            "configuratorSettings": null,
            "categoriesRo": null,
            "coverId": "0592e61d824c4d34992b7a2fa3fcb532",
            "blacklistIds": null,
            "whitelistIds": null,
            "customFields": null,
            "visibilities": null,
            "tagIds": null,
            "_uniqueIdentifier": "02bd7ff16e3c41c8ac79ac562522d3f9",
            "versionId": "0fa91ce3e96a4bc2be4bd9ce752c3425",
            "translated": {
                "additionalText": null,
                "name": "Practical Wool CompuBooth",
                "keywords": null,
                "description": "Ut ipsam labore dolore non. Illo nesciunt porro et qui voluptatem et eius. Eum dolore aut modi sit sint voluptatem veritatis. Cupiditate voluptas in ut nam alias temporibus.",
                "metaTitle": null,
                "packUnit": null,
                "customFields": []
            },
            "createdAt": "2019-07-15T06:45:02.398+00:00",
            "updatedAt": null,
            "extensions": [],
            "id": "02bd7ff16e3c41c8ac79ac562522d3f9",
            "parentVersionId": "0fa91ce3e96a4bc2be4bd9ce752c3425",
            "productManufacturerVersionId": "0fa91ce3e96a4bc2be4bd9ce752c3425",
            "productMediaVersionId": null
        }
    ],
    "aggregations": []
}
```

## Full schema

The full schema can be explored with swagger: `/api/v3/_info/swagger.html`
To access the full schema, you have to make sure, that the `APP_ENV` is set to `dev`.
