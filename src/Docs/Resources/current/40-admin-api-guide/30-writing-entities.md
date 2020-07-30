[titleEn]: <>(Writing entities)
[hash]: <>(article:admin_api_write)

## Writing entities
The Admin API is designed so that all entities of the system can be written in the same way. 
Once an entity is registered in the system, it can also be written via API.
The appropriate routes for the entity are generated automatically and follow the rest pattern. 

The entity `customer_group` is available under the endpoint `api/v1/customer-group`.
For an entity, the system automatically generates the following routes where the entity can be written

| Name | Method | Route | Usage
| ----- | ------ | ----------------------- | ------ |
| api.customer_group.update | PATCH  | /api/v{version}/customer-group/{id} | Update the entity with the provided ID |
| api.customer_group.delete | DELETE | /api/v{version}/customer-group/{id} | Delete the entity |
| api.customer_group.create | POST   | /api/v{version}/customer-group      | Create a new entity |                 

A list of all routes and registered entities in the system can be read out via the `/api/v3/_info/*` routes:

### Routes
A complete listing of all routes is available via the OpenAPI schema: 

`/api/v3/_info/openapi3.json`

A complete list of all entities in the system including their fields is available via the OpenAPI or entity schema:

`/api/v3/_info/open-api-schema.json` / `api/v1/_info/entity-schema.json`

### Payload
If it is not clear how the data has to be sent despite the scheme, it is also possible to open the administration and to have a look at the corresponding requests. 
To do this, simply open the network tab in the developer tools of your browser, which lists all requests and payloads sent by the administration.

### UUIDv4
Shopware 6 works with UUIDv4 as Ids instead of auto increments. We have added this standard for the following reasons:
* Ids can be given when creating an entity
* Very low probability of generating double Ids
* Data can be easily transferred from one system to another 

The UUIDv4 format allows you to generate the IDs before using the API, so you don't have to wait for an API response to get the generated ID.

### Creating entities
When creating an entity, all `required` fields must be provided in the request body. 
If one or more fields have not been passed or contain incorrect data, the API outputs all errors for an entity:

```
POST /api/v3/product/
{
    "name" : "test"
}

{
    "errors": [
        {
            "code": "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
            "status": "400",
            "detail": "This value should not be blank.",
            "source": {
                "pointer": "/0/taxId"
            }
        },
        {
            "code": "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
            "status": "400",
            "detail": "This value should not be blank.",
            "source": {
                "pointer": "/0/stock"
            }
        },
        {
            "code": "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
            "status": "400",
            "detail": "This value should not be blank.",
            "source": {
                "pointer": "/0/price"
            }
        },
        {
            "code": "c1051bb4-d103-4f74-8988-acbcafc7fdc3",
            "status": "400",
            "detail": "This value should not be blank.",
            "source": {
                "pointer": "/0/productNumber"
            }
        }
    ]
}
```

If the entity has been successfully created, the API responds with a `204 No Content` status code.

```
POST /api/v3/product/
{
    "name" : "test",
    "productNumber" : "random",
    "stock" : 10,
    "price" : [
        {
            "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", 
            "gross": 15, 
            "net": 10, 
            "linked" : false
        }
    ],
    "tax" : {
        "name": "test", 
        "taxRate": 15
    }    
}
```

### Updating entities
Updating an entity can, and should, be done partially. This means that only the fields to be updated should be sent in the request.
This is recommended because the system reacts differently in the background to certain field changes.

For example, to update the stock of a product and update the price at the same time, we recommend the following payload:

```
PATCH /api/v3/product/021523dde52d42c9a0b005c22ac85043
{
    "stock": 10,
    "price": [
        {
            "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
            "gross": 99.99,
            "net": 89.99,
            "linked": false
        }    
    ]
}
```

### Deleting entities
To delete an entity the route `DELETE /api/v3/product/{id}` can be used. If the entity has been successfully deleted, the API returns a `204 - No Content` response.

When deleting data, it can happen that this is prevented by foreign key restrictions. This happens if the entity is still linked to another entity which requires the relation.
For example, if you try to delete a tax record which is marked as required for a product, the delete request will be prevented with a `409 - Conflict`:

```
DELETE /api/v3/tax/5840ff0975ac428ebf7838359e47737f

{
    "errors": [
        {
            "status": "409",
            "code": "FRAMEWORK__DELETE_RESTRICTED",
            "title": "Conflict",
            "detail": "The delete request for tax was denied due to a conflict. The entity is currently in use by: product (32)"
        }
    ]
}
```

### Cloning an entity
To clone an entity the route `POST /api/v3/_action/clone/{entity}/{id}` can be used. The API clones all àssociations which is marked with `CascadeDelete`.
The `CascadeDelete` flag allows to disable this behavior by providing `false` in the constructor: `new CascadeDelete(false)`.
Some entities have a `ChildrenAssociationField`. The children are also considered in a clone request. However, since this results in large amounts of data, the parameter `cloneChildren: false` can be sent in the payload so that they are no longer duplicated.
It is also possible to overwrite fields in the clone using the payload parameter 'overwrites'. This is especially helpful if the entity has an unique constraint in the database.
As response, the API returns the new id of the entity:
```
POST /api/v3/_action/clone/product/53be6fb93e4b44ed877736cbe01a47b8
{
	"overwrites": {
		"name" : "New name",
		"productNumber" : "new number"
	},
	"cloneChildren": false
}

{
    "id": "cddde8ad9f81497b9a280c7eb5c6bd2e"
}
```


## Writing associations
The Admin API allows you to create several data records simultaneously within one request. This is possible by using associations. 
For example, when a product is written, the prices can be written at the same time. This is not limited to entities that are directly related to the main entity
but can be continued for as long as you wish and another association is defined.

> **Note:** When writing association via API the following applies: Only data is written, not deleted. So writing a `OneToMany` or `ManyToMany` association only adds new data, the existing data will not be deleted.
 
> **Note:** In general, when writing a field or association, the API expects the format that it returns when reading the record. 

> **Note:** In general, if no ID is given for an association, the API creates a new record

#### Writing ManyToMany associations
`ManyToMany` Associations is an association which is supposed to link two independent entities. The table that serves as the mapping table for the relationship contains only the foreign keys for the entities to be linked.
An example:
* The `ProductEntity` has a `ManyToMany` association with the `CategoryEntity`
* The association is available under the property `categories`
* The data for this association is stored in the `product_category` table.

There are three ways in which `ManyToMany` associations can be used in the API 

**1: The entity to be linked should be created in the same request.**
In this case all required fields are sent with the entity. 

```
PATCH /api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "categories": [
        { "name": "First category" },
        { "name": "Second category" },
        { "name": "Third category" }
    ]
}
```

**2: The entity to be linked should be updated in the same request.**
In this case, the entity already exists in the system, but it can be updated in the same request like all other associations.
For this purpose, the corresponding ID of the entity is sent with the request. If the ID does not exist in the system, the API creates a new entity with this id.

```
PATCH /api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "categories": [
        { "id": "98432def39fc4624b33213a56b8c944d", "name": "First category" },
        { "id": "2fbb5fe2e29a4d70aa5854ce7ce3e20b", "name": "Second category" },
        { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "name": "Third category" }
    ]
}
```

**3: Records should only be linked *(performant)***
If both data records already exist in the system and are to be linked to the PATCH request exclusively, it is recommended that you send only the ID of the entity.
This has the advantage that there is no update of the linked entity, which means less load on the system:

```
PATCH /api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "categories": [
        { "id": "98432def39fc4624b33213a56b8c944d" },
        { "id": "2fbb5fe2e29a4d70aa5854ce7ce3e20b" },
        { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca" }
    ]
}
```

#### Writing ManyToOne associations
`ManyToOne` associations are associations where the foreign key is stored in the root entity.
An example: 
* The `ProductEntity` has a `ManyToOneAssociation` to `ProductManufacturerEntity`
* The association is available under the property `manufacturer`
* The foreign key is stored in the property `manufacturerId`.

There are three ways in which `ManyToOne` associations can be used in the API

**1: The entity to be linked is to be created directly with**
In this case all required fields of the entity must be given:

```
PATCH {{host}}/api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca

{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "manufacturer": { 
        "name": "My manufcaturer" 
    }
}
```
With the above payload, the system creates a new manufacturer in the system and links it to the product `b7d2554b0ce847cd82f3ac9bd1c0dfca`.

**2: The entity to be linked should be updated in the same request.**
In this case it is necessary to send the ID of the existing entity.

```
PATCH {{host}}/api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "manufacturer": { 
        "id": "98432def39fc4624b33213a56b8c944d", 
        "name": "My manufcaturer" 
    }
}
```

With the above payload, the system first checks whether a manufacturer with the id `98432def39fc4624b33213a56b8c944d` exists. If this is not the case, a new manufacturer with this ID is created.
If the manufacturer already exists, the name of the manufacturer is updated. Then the manufacturer will be linked to the product.

**3: The entity should be linked exclusively *(performant)***
With this option, the manufacturer already exists and should only be linked with the product. For this, either only the `id` can be sent, or the foreign key can be specified directly:

```
PATCH {{host}}/api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "manufacturer": { 
        "id": "98432def39fc4624b33213a56b8c944d" 
    }
}
```

```
PATCH {{host}}/api/v3/product/b7d2554b0ce847cd82f3ac9bd1c0dfca
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "manufacturerId": "98432def39fc4624b33213a56b8c944d"
}
```

Both payloads lead to the same result. This type of use is preferable because only the product entity is updated and not the manufacturer entity with every product update, which leads to less load on the server.

#### Writing OneToMany associations
Unlike the `ManyToOne` and `ManyToMany` association, data in a `OneToMany` association is usually not data that should be linked, but data that belongs to the main entity.
This association is the counterpart of the `ManyToOne` association. The foreign key is therefore located in the table of the entity to which the association refers. 

For example: 
* The `CountryEntity` has a `OneToMany` association with the `CountryStateEntity`
* The association is available under the `states` property
* The foreign key is located in the `CountryStateEntity::countryId` property.

There are two ways to use `OneToMany` associations in the API.

**1: A new record should be created in the association.**
In this case all fields marked as required must be given. An ID can also be given here if it is not to be generated on server side:

```
POST /api/v3/country
{
    "name" : "new country",
    "states": [
        { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "name": "state-a", "shortCode": "A" },
        { "name": "state-b", "shortCode": "B" },
        { "name": "state-c", "shortCode": "C" }
    ]    
    
}
```

**2: An already existing entity of the association has to be updated**
In this case, it is necessary that the ID of the entity is also given. If this is not done, the system tries to create a new entity:

```
PATCH /api/v3/country
{
    "id": "98432def39fc4624b33213a56b8c944d",
    "name" : "new country",
    "states": [
        { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "name": "new name" }
    ]    
}
```

If an error occurs while writing the data, the API returns a `400 Bad Request` response in which all errors are listed.
The affected records and fields can be identified via `source.pointer`:
```
POST /api/v3/country

{
    "name" : "new country",
    "states": [
        { "name": "state-a", "shortCode": "A" },
        { "name": "state-b", "shortCode": 1 },
        { "name": "state-c" }
    ]    
    
}

{
    "errors": [
        {
            "status": "400",
            "detail": "This value should be of type string.",
            "source": {
                "pointer": "/0/states/1/shortCode"
            }
        },
        {
            "status": "400",
            "detail": "This value should not be blank.",
            "source": {
                "pointer": "/0/states/2/shortCode"
            }
        }
    ]
}
```

#### Writing translations
In Shopware 6 translatable fields of an entity can be written directly at the entity itself. For example, the `name` of a product is a translatable field. 
If no other language is set per header, the default language of the system is used for reading and writing. 
When an entity object is created in the system, it must have a translation in the default language of the system. This translation is used as a fallback if the entity is displayed in another
language for which there is no translation.
When writing an entity, it is possible to write several languages at the same time. This is done via the `translations` association:

```
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "translations": {
        "2fbb5fe2e29a4d70aa5854ce7ce3e20b": {
            "name": "english name",
            "description": "english description"
        },
        "6d7b97a0f3504824bd0e77b021312c33": {
            "name": "german name",
            "description": "german description"
        }
    }
}
```

Within the `translations` property the language id, for which this translation is used, is then passed as key. All translatable fields can be specified within the object. 
If the language id is not known, the locale code can be used instead of the id:

```
{
    "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
    "translations": {
        "en-GB": {
            "name": "english name by code",
            "description": "english description by code"
        },
        "de-DE": {
            "name": "german name by code",
            "description": "german description by code"
        }
    }
}
```

Unlike the other types of associations, an update of a translation does not require an ID of the translation entity to be provided. This 
entities are an exception in the system and are uniquely identified by the language ID.

## Writing products
In this section the handling of the product data structure is explained, because the product contains a few special fields which might not be very clear at first looking at the API schema.

### Simple payload
A product has only a handful of required fields:
* `name` [string]
* `productNumber` [string]
* `taxId` [string]
* `price` [object]
* `stock` [int]

The smallest required payload for a product can therefore be as follows:

```json
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "db6f3ed762d14b0395a3fd2dc460db42",
    "price": [
        {
            "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", 
            "gross": 15, 
            "net": 10, 
            "linked" : false
        }
    ]
}
```

The following payload examples contain UUIDs for various entities such as currencies, tax rates, manufacturers or properties. These IDs are different on each system and must be adjusted accordingly.

### Price handling
Price handling is one of the edge cases in the product data structure. There are three different prices for a product, which can be queried via API:
* `product.price`
* `product.prices`
* `product.listingPrices`

Only the first two can be written via API (`product.price`, `product.prices`). 
The `product.price` is the "simple" price of a product. It does not contain any quantity information nor is it bound to any `rule`. 

### Currency price structure
Within the price, the different currency prices are available. Each of these currency prices includes the following properties:

* `currencyId`  - ID of the currency to which the price belongs
* `gross`       - This price is displayed to customers who see gross prices in the shop
* `net`         - This price is shown to customers who see net prices in the shop
* `linked`      - This is a flag for the administration. If it is set to `true`, the gross or net counterpart is calculated when a price is entered in the administration.

To define prices for a product in different currencies, this is an exemplary payload:
``` 
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "db6f3ed762d14b0395a3fd2dc460db42",
    "price": [
        {
            // euro price
            "currencyId" : "db6f3ed762d14b0395a3fd2dc460db42", 
            "gross": 15, 
            "net": 10, 
            "linked" : false
        },
        {
            // dollar price
            "currencyId" : "16a190bd85b741c08873cfeaeb0ad8e1", 
            "gross": 120, 
            "net": 100.84, 
            "linked" : true
        },
        {
            // pound price
            "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", 
            "gross": 66, 
            "net": 55.46, 
            "linked" : true
        }
    ]
}
```

### Quantity and rule price structure
As an extension to the `product.price` there are `product.prices`. These are prices that are bound to a `rule`.
Rules (`rule` entity) are prioritised. If there are several rules for a customer, the customer will see the rule price with the highest priority.
In addition to the dependency on a rule, a quantity discount can be defined using these prices. 

Each price in `product.prices` has the following properties:
* `quantityStart` [int]     - Indicates the quantity from which this price applies
* `quantityEnd` [int|null]  - Specifies the quantity until this price is valid. 
* `ruleId` [string]         - Id of the rule to which the price applies
* `price` [object[]]        - Includes currency prices (same structure as `product.price`)

To define prices for a rule including a quantity discount, this is an exemplary payload:

``` 
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "db6f3ed762d14b0395a3fd2dc460db42",
    "price": [
        { 
            "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca", 
            "gross": 15, 
            "net": 10, 
            "linked": false 
        }
    ],
    "prices": [
        { 
            "id": "9fa35118fe7c4502947986849379d564",
            "quantityStart": 1,
            "quantityEnd": 10,
            "ruleId": "43be477b241448ecacd7ea2a266f8ec7",
            "price": [
                { 
                    "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca", 
                    "gross": 20, 
                    "net": 16.81, 
                    "linked": true 
                }
            ]
            
        },
        { 
            "id": "db6f3ed762d14b0395a3fd2dc460db42",
            "quantityStart": 11,
            "quantityEnd": null,
            "ruleId": "43be477b241448ecacd7ea2a266f8ec7",
            "price": [
                { 
                    "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca", 
                    "gross": 19, 
                    "net": 15.97, 
                    "linked": true 
                }
            ]
        }
    ]
}
``` 
    
### Listing price handling
The third price property that is available on the product is the `product.listingPrices`.
These prices are determined automatically by the system.
The price ranges for the corresponding product are available here.
The prices are determined on the base of all variants of prices that could be displayed to the customer in the shop.

Each price within this object contains the following properties:
* `currencyId` [string] - The currency to which this price applies
* `ruleId` [string]     - The rule to which this price applies
* `from` [price-obj]    - The lowest price possible for the product in this currency
* `to` [price-obj]      - The highest price that is possible for the product in this currency

### Assigning of properties and categories
The product has various `many-to-many` associations. This type of association is a link between the records.
Examples are the `properties` and `categories` of a product.

For assigning several `properties` and `categories` this is an exemplary payload:

``` 
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "db6f3ed762d14b0395a3fd2dc460db42",
    "properties": [
        { "id": "b6dd111fff0f4e3abebb88d02fe2021e"},
        { "id": "b9f4908785ef4902b8d9e64260f565ae"}
    ],
    "categories": [
        { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca" },
        { "id": "cdea94b4f9452254a20b91ec1cd538b9" }
    ]
}
``` 

To remove these `properties` and `categories`, the corresponding routes can be used for the mapping entities: 
* `DELETE /api/v3/product/{productId}/properties/{optionId}`
* `DELETE /api/v3/product/{productId}/categories/{categoryId}`

To delete several assignments at once, the `/_action/sync` route can be used:

``` 
{
    // This key can be defined individually
    "unassign-categories": {
        "entity": "product_category",
        "action": "delete",
        "payload": [
            { "productId": "069d109b9b484f9d992ec5f478f9c2a1", "categoryId": "1f3cf89039e44e67aa74cccd90efb905" },
            { "productId": "073db754b4d14ecdb3aa6cefa2ba98a7", "categoryId": "a6d1212c774546db9b54f05d355376c1" }
        ]
    },
    
    // This key can be defined individually
    "unassign-properties": {
        "entity": "product_property",
        "action": "delete",
        "payload": [
            { "productId": "069d109b9b484f9d992ec5f478f9c2a1", "optionId": "2d858284d5864fe68de046affadb1fc3" },
            { "productId": "069d109b9b484f9d992ec5f478f9c2a1", "optionId": "17eb3eb8f77f4d87835abb355e41758e" },
            { "productId": "073db754b4d14ecdb3aa6cefa2ba98a7", "optionId": "297b6bd763c94210b5f8ee5e700fadde" }
        ]
    }
}
```

#### `CategoriesRo` Association
The `product.categories` association contains the assignment of products and their categories.
This table is not queried in the storefront, because all products of subcategories should be displayed in listings as well.
Therefore there is another association: `product.categoriesRo`.
This association is read-only and is filled automatically by the system.
This table contains all assigned categories of the product as well as all parent categories. 

### Media handling
Media of products are maintained via the association `product.media` and `product.cover`. 
The `product.media` association is a `one-to-many` association on the `product_media` entity. To assign a media to a product, a new `product_media` entity must be created, in which the foreign key for the corresponding `media` entity is defined. In addition to the foreign key, a `position` can be specified, which defines the display order.

```
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "5f78f2d4b19f49648eb1b38881463da0",
    "price": [
        { "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", "gross": 15, "net": 10, "linked" : false }
    ],
    "media": [
        {
            "id": "5f78f2d4b19f49648eb1b38881463da0",
            "mediaId": "00a9742db2e643ccb9d969f5a30c2758",
            "position": 1
        }
    ]
}
```

To delete a media assignment, the ID of the `product_media` entity is required. In the above case this is the `5f78f2d4b19f49648eb1b38881463da0`. The corresponding route `DELETE /api/v3/product/{productId}/media/{productMediaId}` can be used for this. To delete multiple assignments, the `/_action/sync` route can also be used here:

```
{
    // This key can be defined individually
    "unassign-media": {
        "entity": "product_media",
        "action": "delete",
        "payload": [
            { "id": "5f78f2d4b19f49648eb1b38881463da0" },
            { "id": "18ada8e085d240369d06bb4b11eed3b5" }
        ]
    }
}
```

### Setting the cover
The `cover` of a product is controlled via `coverId` and the `cover` association. This contains a direct reference to a `media` entity. To set the cover of a product the following payload can be used:

``` 
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "5f78f2d4b19f49648eb1b38881463da0",
    "price": [
        { "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", "gross": 15, "net": 10, "linked" : false }
    ],
    "coverId": "00a9742db2e643ccb9d969f5a30c2758"
}
``` 

To reset the cover, the value `null` can be passed instead of a UUID.

### Visibility handling
The `visibilities` control in which sales channel the product should be visible.
This association is a `one-to-many` association.
Instead of just assigning a sales channel, the data structure allows a specification where the product should be displayed inside the sales channel using the `visibility` property.
This can be set to three different values:
* `10` - The product is only available via a direct link. It does not appear in listings or searches.
* `20` - The product is only available via a direct link or search. The product is not displayed in listings.
* `30` - The product is displayed everywhere.

Since visibility can be configured per sales channel, the entity also has its own ID. This is needed to delete or update the assignment later. To assign a product to several sales channels, the following payload can be used:

``` 
{
    "name": "test",
    "productNumber": "random",
    "stock": 10,
    "taxId": "5f78f2d4b19f49648eb1b38881463da0",
    "price": [
        { "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", "gross": 15, "net": 10, "linked" : false }
    ],
    "visibilities": [
        { "id": "5f78f2d4b19f49648eb1b38881463da0", "salesChannelId": "98432def39fc4624b33213a56b8c944d", "visibility": 20 },
        { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca", "salesChannelId": "ddcb57c32d6e4b598d8b6082a9ca7b42", "visibility": 30 }
    ]
}
``` 

Deleting a sales channel assignment is done via the route `/api/v3/product/{productId}/visibilities/{visibilityId}`.
To delete several assignments at once, the `/_action/sync` route can be used:

``` 
{
    // This key can be defined individually
    "unassign-media": {
        "entity": "product_visibility",
        "action": "delete",
        "payload": [
            { "id": "5f78f2d4b19f49648eb1b38881463da0" },
            { "id": "b7d2554b0ce847cd82f3ac9bd1c0dfca" }
        ]
    }
}
``` 

### Variant handling
Variants are child elements of a product. As soon as a product is configured with variants, the parent product is only a kind of container. To create a variant, the following properties are required:

* `parentId` [string]      - Defines for which product the variant should be created
* `stock` [int]            - Defines the stock of the variant
* `productNumber` [string] - Defines the unique product number
* `options` [array]        - Defines the characteristic of the variant.

``` 
{
    "id": "0d0adf2a3aa1488eb177288cfac9d47e",
    "parentId": "17f255e0a12848c38b7ec6767a6d6adf",
    "productNumber": "child.1",
    "stock": 10,
    "options": [
        {"id": "0584efb5f86142aaac44cc3beeeeb84f"},    // red
        {"id": "0a30f132eb1b4f34a05dcb1c6493ced7"}  // xl
    ]
}
``` 

### Inheritance
Data that is not defined in a variant, is inherited from the parent product. If the variants have not defined their own `price`, the `price` of the parent product is displayed. This logic applies to different fields, but also to associations like `product.prices`, `product.categories` and many more.

To define a separate `price` for a variant, the same payload can be used as for a non-variant products:

``` 
{
    "id": "0d0adf2a3aa1488eb177288cfac9d47e",
    "parentId": "17f255e0a12848c38b7ec6767a6d6adf",
    "productNumber": "child.1",
    "stock": 10,
    "options": [
        {"id": "0584efb5f86142aaac44cc3beeeeb84f"},    // red
        {"id": "0a30f132eb1b4f34a05dcb1c6493ced7"}  // xl
    ],
    "price": [
        { "currencyId" : "b7d2554b0ce847cd82f3ac9bd1c0dfca", "gross": 15, "net": 10, "linked" : false }
    ]
}
``` 

To restore inheritance, the value `null` can be passed for simple data fields:

``` 
// PATCH /api/v3/product/0d0adf2a3aa1488eb177288cfac9d47e
{
    "price": null
}
```

In order to have an association such as `product.prices` inherited again from the parent product, the corresponding entities must be deleted.

If a variant is read via `/api`, only the not inherited data is returned. The data of the parent is not loaded here. In the `store-api`, however, the variant is always read with the inheritance, so that all information is already available  to display the variant in a shop.

However, it is also possible to resolve the inheritance in the `/api` by providing the `sw-inheritance` header.

### Configurator handling
To create a complete product with variants, not only the variants have to be created but also the corresponding `options` have to be configured. 
For the variants this is done via the `options` association. This association defines the characteristics of the variant, i.e. whether it is the yellow or red t-shirt.
For the parent product, the `configuratorSettings` association must be defined. This defines which options are generally available. The Admin UI and the Storefront UI are built using this data. 
The following payload can be used to generate a product with the variants: red-xl, red-l, yellow-xl, yellow-l.

``` 
{
    "stock": 10,
    "productNumber": "random",
    "name": "random",
    "taxId": "9d4a11eeaf3a41bea44fdfb599d57058",
    "price": [
        {
            "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
            "net": 1,
            "gross": 1,
            "linked": true
        }
    ],
    "configuratorGroupConfig": [
        {
            "id": "d1f3079ffea34441b0b3e3096ac4821a",       //group id for "color"
            "representation": "box",
            "expressionForListings": true                   // display all colors in listings
        },
        {
            "id": "e2d24e55b56b4a4a8f808478fbd30333",       // group id for "size"
            "representation": "box",
            "expressionForListings": false
        }
    ],
    "children": [
        {
            "productNumber": "random.4",
            "stock": 10,
            // own pricing
            "price": [
                {
                    "currencyId": "b7d2554b0ce847cd82f3ac9bd1c0dfca",
                    "net": 1,
                    "gross": 1,
                    "linked": true
                }
            ],
            "options": [
                { "id": "4053fb11b4114d2cac7381c904651b6b" },   // size:  L
                { "id": "ae821a4395f34b22b6dea9963c7406f2" }    // color: yellow
            ]
        },
        {
            "productNumber": "random.3",
            "stock": 10,
            "options": [
                { "id": "ea14a701771148d6b04045f99c502829" },   // size:  XL
                { "id": "ae821a4395f34b22b6dea9963c7406f2" }    // color: yellow
            ]
        },
        {
            "productNumber": "random.1",
            "stock": 10,
            "options": [
                { "id": "ea14a701771148d6b04045f99c502829" },   // size:  XL
                { "id": "0b9627a94fc2446498ec6abac0f03581" }    // color: red
            ]
        },
        {
            "productNumber": "random.2",
            "stock": 10,
            "options": [
                { "id": "4053fb11b4114d2cac7381c904651b6b" },   // size:  L
                { "id": "0b9627a94fc2446498ec6abac0f03581" }    // color: red
            ]
        }
    ],
    "configuratorSettings": [
        { "optionId": "0b9627a94fc2446498ec6abac0f03581" },     // color: red
        { "optionId": "4053fb11b4114d2cac7381c904651b6b" },     // size:  L
        { "optionId": "ae821a4395f34b22b6dea9963c7406f2" },     // color: yellow
        { "optionId": "ea14a701771148d6b04045f99c502829" }      // size:  XL
    ]
}
