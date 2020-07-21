[titleEn]: <>(Sync api)
[hash]: <>(article:sync_api)

## Sync API
The Sync API is an add-on to the Admin API that allows you to perform multiple write operations simultaneously.
All entities that can be written via the Admin API can also be written via the Sync API.

### Writing entities
In contrast to the Admin API, the Sync API does not separate between `CREATE` and `UPDATE` but always performs a `UPSERT`.
With an `upsert`, the system checks whether the entity already exists in the system and updates it if an ID has been passed, otherwise a new entity is created with this ID.

A request always contains a list of operations. An operation defines the `action` to be executed (`upert` or `delete`), the `entity` it is and the `payload` which is an array of multiple records (for `upert`) or multiple IDs (for `delete`).
Within a request, different entities can therefore be written in batch.
The operations can be assigned a key, which makes debugging easier. The key is then used in the response to define which entities are written in which operation:

```
POST /api/v3/_action/sync
{
    "write-tax": {
        "entity": "tax",
        "action": "upsert",
        "payload": [
            { "name": "tax-1", "taxRate": 16 },
            { "name": "tax-2", "taxRate": 15 }    
        ]
    },
    "write-category": {
        "entity": "category",
        "action": "upsert",
        "payload": [
            { "name": "category-1" },
            { "name": "category-2" }
        ]
    },
    "write-country": {
        "entity": "country",
        "action": "upsert",
        "payload": [
            { "name": "country-1" },
            { "name": "country-2" }
        ]
    }
}

{
    "success": true,
    "data": {
        "write-tax": {
            "result": [
                {
                    "entities": {
                        "tax": ["bd59bcc0ceaa4acbbbb9e5e9d22b0312"]
                    }
                },
                {
                    "entities": {
                        "tax": ["38bfe5140d58429a840190c6ed43f0c4"]
                    }
                }
            ]
        },
        "write-category": {
            "result": [
                {
                    "entities": {
                        "category": ["0a2275ff38a747069fce697bc2582bdc"],
                        "category_translation": [
                            { "categoryId": "0a2275ff38a747069fce697bc2582bdc", "languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b" }
                        ]
                    }
                },
                {
                    "entities": {
                        "category": ["8df6114ba9c84116b6011d6b9ce1fa3a"],
                        "category_translation": [
                            { "categoryId": "8df6114ba9c84116b6011d6b9ce1fa3a", "languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b" }
                        ]
                    }
                }
            ]
        },
        "write-country": {
            "result": [
                {
                    "entities": {
                        "country": ["91738c2ee74a464e8ffe4f1d572449b3"],
                        "country_translation": [
                            { "countryId": "91738c2ee74a464e8ffe4f1d572449b3", "languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b" }
                        ]
                    }
                },
                {
                    "entities": {
                        "country": ["69b2d17d04364620ad9ded6b01f471cd"],
                        "country_translation": [
                            { "countryId": "69b2d17d04364620ad9ded6b01f471cd", "languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b" }
                        ]
                    }
                }
            ]
        }
    }
}
```

### Deleting entities
To delete entities via the Sync API, the `payload` of an operation contains the IDs. If the entity is a `MappingEntityDefinition` (e.g. `product_category`) the foreign keys, which are the primary key, must be passed:

```
{
    "delete-tax": {
        "entity": "category",
        "action": "delete",
        "payload": [
            { "id": "1d0943f296a94b06b785dfb4b017c18b" },
            { "id": "046e9574bdae4478b854f49a8f22c275" },
            { "id": "0a5bff83cbdf45968d37d30c31beac69" }
        ]
    },
    "delete-product-category": {
        "entity": "product_category",
        "action": "delete",
        "payload": [
            { "productId": "000bba26e2044b98a3ee4a84b03f9551", "categoryId": "0446a1eb394c4e729178699a7bc2833f" },
            { "productId": "5deed0c33b2a4866a6b2c88fa215561c", "categoryId": "0446a1eb394c4e729178699a7bc2833f" }
        ]
    }
}
```

#### Deleting Relations
To delete relations for an entity it is not possible to do an update on the main entity, instead you have to delete the relation on the relation entity `MappingEntityDefinition` (e.g. `product_property`).
The corresponding entries in the main entity (here `product`) will be updated with an indexer that will immediately run after the delete (for details on indexer behaviour see [More Performance](#more-performance))  

```
{
    "delete-product-property": {
        "entity": "product_property",
        "action": "delete",
        "payload": [
            { "productId": "000bba26e2044b98a3ee4a84b03f9551", "optionId": "0446a1eb394c4e729178699a7bc2833f" },
            { "productId": "5deed0c33b2a4866a6b2c88fa215561c", "optionId": "0446a1eb394c4e729178699a7bc2833f" }
        ]
    }
}
```

### More performance
When using the Sync Api, by default each record is written individually. In addition, various indexing processes are also triggered in the background, depending on 
which data was written. However, this leads to a high load on the server and can be a problem with large imports.
Therefore, it is possible that all data is written in a single transaction and the indexing is moved to the background.
To do this, you have to provide the following headers:
* `single-operation` - All data will be written in a single transaction
* `indexing-behavior` - Defines how the data indexing should behave
    * `null` or not passed - The data will be indexed immediately
    * `use-queue-indexing` - The data will be indexed over the message queue
    * `disable-indexing` - The data indexing is completely disabled

```
/api/v3/_action/sync
--header 'single-operation: 1'
--header 'indexing-behavior: use-queue-indexing'

{
    "write-tax": {
        "entity": "tax",
        "action": "upsert",
        "payload": [
            { "name": "tax-1", "taxRate": 16 },
            { "name": "tax-2", "taxRate": 15 }    
        ]
    },
    "write-category": {
        "entity": "category",
        "action": "upsert",
        "payload": [
            { "name": "category-1" },
            { "name": "category-2" }
        ]
    },
    "write-country": {
        "entity": "country",
        "action": "upsert",
        "payload": [
            { "name": "country-1" },
            { "name": "country-2" }
        ]
    }
}
```
