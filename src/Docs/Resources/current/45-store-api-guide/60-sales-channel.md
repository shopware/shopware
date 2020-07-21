[titleEn]: <>(Store api sales channel routes)
[hash]: <>(article:store_api_sales_channel)

## Sales Channel
On this page you can find all the api routes that are able to give you information about the Sales Channel

### Get current context
If you want to get the current context you can use the following route `store-api.context`.

```
GET /store-api/v3/context

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

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v3/language

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

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v3/currency

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

When you want to switch the context you can use this route: `store-api.switch-context`

This route needs the following parameters:
* `currencyId`: id of the currency 
* `languageId`: id of the language
* `billingAddressId`: id of the billing address id
* `shippingAddressId`: id of the billing address
* `paymentMethodId`: id of the payment method
* `shippingMethodId`: id of the shipping method
* `countryId`: id of the country
* `countryStateId`: id of the country state

Note, for this route to work the customer has to be logged in.

```
PATCH /store-api/v3/context

{
    "currencyId": "9f42e5f57d834c509541068ef3344683",
    "languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b",
    "paymentMethodId": "388ea8341ae64e4daa45d613110245f6",
    "shippingMethodId": "b6adaa7ae5c043f58c2c49a046605e99",
    "countryId": "70f1db3abab542239b4681359975dd7e",
    "countryStateId": "3e3ea21ccb41424eab273ad0cab7fcee"
}

{
    "contextToken": "C8hV2VOfzGhGAuSBiXYusAxQVMxJaAHe",
    "apiAlias": "array_struct"
}
```

### Seo resolving

When you want to get Information about your SEO Urls then you can use this route: `store-api.seo.url`

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
GET /store-api/v3/seo-url

{
    "includes": {
        "seo_url": [
            "routeName",
            "pathInfo",
            "id"
        ]
    }
}

[
    {
        "routeName": "frontend.navigation.page",
        "pathInfo": "/navigation/4d7ec66a7b854e59b8cf1b8b90fc651e",
        "id": "013b993661f44cfb9ab2880ab8e00843",
        "apiAlias": "seo_url"
    },
    {
        "routeName": "frontend.navigation.page",
        "pathInfo": "/navigation/298d3206940a48a3aab5b5e5919f18e4",
        "id": "03ce5a4f4a35447288e5df2f39ad0975",
        "apiAlias": "seo_url"
    },
]
```

### Payment methods

The api `/store-api/v3/payment-method` can be used to list all payment methods of the sales channel.
With the parameter `onlyAvailable` you can restrict the result to only valid payments methods.
Additionally, the api basic parameters (`filter`, `aggregations`, etc.) can be used to restrict the result, for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v3/payment-method

{
    "includes": {
        "payment_method": ["name", "description", "active"]
    }
}

[
    {
        "name": "Cash on delivery",
        "description": "Payment upon receipt of goods.",
        "active": true,
        "apiAlias": "payment_method"
    },
    {
        "name": "Paid in advance",
        "description": "Pay in advance and get your order afterwards",
        "active": true,
        "apiAlias": "payment_method"
    }
]
```

### Available Shipping methods

The api `/store-api/v3/shipping-method` can be used to list all payment methods of the sales channel.
With the parameter `onlyAvailable` you can restrict the result to only valid shipping methods.
Additionally, the api basic parameters (`filter`, `aggregations`, etc.) can be used to restrict the result, for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v3/shipping-method

{
    "includes": {
        "shipping_method": ["name", "active", "deliveryTime"],
        "delivery_time": ["name", "unit"]
    }
}

[
    {
        "name": "Express",
        "active": true,
        "deliveryTime": {
            "name": "1-3 days",
            "unit": "day",
            "apiAlias": "delivery_time"
        },
        "apiAlias": "shipping_method"
    }
]
```
