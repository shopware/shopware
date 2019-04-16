[titleEn]: <>(Management API usage)

This guide describes the default usage with the default schema, which almost all resources are using.
There are extended read possibilities for a few entities. Read [here](./40-management-extended-read.md) for more information about that.

## Usage

The examples use the simple JSON format for brevity.
To get the examples running, you have to set the Authorization and Accept Header as shown below:

```javascript
const headers = { 
    "Authorization": "Bearer " + token,
    "Accept": "application/json"
};
fetch(`${baseUrl}/api/v1/product`, { headers })
    .then((response) => response.json())
    .then((products) => console.log('Products', products));
```

### GET /api/v1/category?limit=1

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
                "createdAt": "2018-09-14T09:38:35+02:00",
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
 

### GET /api/v1/category/{id}

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
            "createdAt": "2018-09-14T09:38:35+02:00",
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

### POST /api/v1/category

Add a new category.

**Request body:**
```json
    {
        "name": "New category"
    }
```

**Response:**

    Status 204 No Content
    Location: http://localhost:8000/api/v1/category/20080911ffff4fffafffffff19830531
 

### PATCH /api/v1/category/{id}

Change attributes of the category.

**Request body:**
```json
    {
        "name": "Changed category name"
    }
```
**Response:**

    Status 204 No Content
    Location: http://localhost:8000/api/v1/category/20080911ffff4fffafffffff19830531

### DELETE /api/v1/category/{id}

Delete the category.

**Response:**

    Status 204 No Content

### GET /api/v1/category/{id}/products?limit=1

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
                "createdAt": "2018-09-14T09:38:37+02:00",
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
                    "createdAt": "2018-09-14T09:29:16+02:00",
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
                    "createdAt": "2018-09-14T09:38:35+02:00",
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
                    "createdAt": "2018-09-14T09:38:37+02:00",
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
                        "createdAt": "2018-09-14T09:38:37+02:00",
                        "updatedAt": "2018-09-14T09:38:58+02:00",
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
                                "createdAt": "2018-09-14T09:38:58+02:00",
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
                                "createdAt": "2018-09-14T09:38:58+02:00",
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

## Full schema

The full schema can be explored with the swagger client in the administration client under **Documentation → Platform API**.
