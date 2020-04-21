[titleEn]: <>(Store api sales channel routes)
[hash]: <>(article:store_api_sales_channel)

## Sales Channel
On this page you can find all the api routes that are able to give you information about the Sales Channel

### Get current context
If you want to get the current context you can use the following route `store-api.context`.

```
GET /store-api/v1/context

{
    "includes": {
        "sales_channel_context": ["currentCustomerGroup"]
    }
}

{
    "currentCustomerGroup": {
        "name": "Standard customer group",
        "displayGross": true,
        "translations": null,
        "customFields": null,
        "_uniqueIdentifier": "cfbd5018d38d41d8adca10d94fc8bdd6",
        "versionId": null,
        "translated": {
            "name": "Standard customer group",
            "customFields": []
        },
        "createdAt": "2020-04-07T14:09:25+00:00",
        "updatedAt": null,
        "extensions": {
            "foreignKeys": {
                "apiAlias": "array_struct"
            }
        },
        "id": "cfbd5018d38d41d8adca10d94fc8bdd6",
        "apiAlias": "customer_group"
    },
    "apiAlias": "sales_channel_context"
}
```

### Available languages
To get all languages of an Sales Channel you can use the following route `store-api.language`

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v1/language

{
    "includes": {
        "language": [
            "id",
            "name"
        ]
    }
}

[
    {
        "name": "English",
        "id": "2fbb5fe2e29a4d70aa5854ce7ce3e20b",
        "apiAlias": "language"
    },
    {
        "name": "Deutsch",
        "id": "77afb1f1401447a7b23ef13ba6d19bdc",
        "apiAlias": "language"
    }
]
```

### Available currencies
With `store-api.currency` you can fetch all currencies that are available in this Sales Channel.

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v1/currency

{
    "includes": {
        "currency": [
            "id",
            "factor",
            "shortName",
            "name"
        ]
    }
}

[
    {
        "factor": 0.89157,
        "shortName": "GBP",
        "name": "Pound",
        "id": "01913e4cbe604f45be84cbabd5966239",
        "apiAlias": "currency"
    },
    {
        "factor": 10.51,
        "shortName": "SEK",
        "name": "Swedish krone",
        "id": "3dfbaa78994b4f1cac491f1a992646fd",
        "apiAlias": "currency"
    }
]
```

### Switch context

### Seo resolving
