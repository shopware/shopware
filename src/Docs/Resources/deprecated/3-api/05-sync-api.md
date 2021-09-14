[titleEn]: <>(Sync API)
[hash]: <>(article:api_sync)

The Sync API allows you to perform multiple write operations in one request. This is available at the URL `POST /api/_action/sync`.
As request body several operations can be sent which can be of type `upsert` or `delete`.

## Request body format
```json
[
    {
        "action": "upsert",
        "entity": "tax",
        "payload": [
            {"id": "98432def39fc4624b33213a56b8c944f", "name": "example", "taxRate": 19},
            {"name": "example", "taxRate": 19},
            {}
        ]
    },
    {
        "action": "delete",
        "entity": "product",
        "payload": [
            {"id": "98432def39fc4624b33213a56b8c944f"}
        ]
    }
]
```

## Response body format
The response contains a result for each record. At the root level of the response there is a flag `success` which can be used to determine whether all operations have been performed successfully.
```json
{
    "success":false,
    "data":[
        {
            "key":"0",
            "success":true,
            "result":[
                {
                    "error":null,
                    "entities": {
                        "product_manufacturer":["366ef339415a492cb66f9ef8890439c9"],
                        "product_manufacturer_translation":[
                            {"productManufacturerId":"366ef339415a492cb66f9ef8890439c9","languageId":"2fbb5fe2e29a4d70aa5854ce7ce3e20b"}
                        ]
                    }
                },
                {
                    "error":null,
                    "entities": {
                        "product_manufacturer":["c6dcf67e3caf47c58989a7259c834756"],
                        "product_manufacturer_translation": [
                            {"productManufacturerId":"c6dcf67e3caf47c58989a7259c834756","languageId":"2fbb5fe2e29a4d70aa5854ce7ce3e20b"}
                        ]
                    }
                }
            ],
            "extensions":[]
        },
        {
            "key":"1",
            "success":false,
            "result":[
                {
                    "error":null,
                    "entities": {
                        "tax":["366ef339415a492cb66f9ef8890439c9"]
                    }
                },
                {
                    "error":null,
                    "entities": {
                        "tax":["c6dcf67e3caf47c58989a7259c834756"]
                    }
                },
                {
                    "error": "There are 2 error(s) while writing data.\n\n1. [/0/taxRate] This value should not be blank.\n2. [/0/name] This value should not be blank.",
                    "entities": []
                }
            ],
            "extensions":[]
        }
    ],
    "extensions":[]
}
```

## Fail on error
It is possible to control the behavior in case of an error. This happens via the header `fail-on-error`. By default it is set to `true`, so that all operations are only taken over into the database if no errors occurred.
If the value is set to `false`, successful operations will still be transferred to the database even if an error occurs.
