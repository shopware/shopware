---
title: Changed Store-API Response from Collection to Search Result
issue: NEXT-10272
---
# Core

*  Changed the constructor of following classes to `\Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult`:
    * `\Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse`
    * `\Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute`
    * `\Shopware\Core\Content\Seo\SalesChannel\SeoUrlRouteResponse`
    * `\Shopware\Core\System\Language\SalesChannel\LanguageRouteResponse`
    * `\Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse`
    * `\Shopware\Core\System\Currency\SalesChannel\CurrencyRouteResponse`
___
# API

*  Changed the response from following routes to return a search result instead a collection
    * /store-api/v{version}/payment-method
    * /store-api/v{version}/shipping-method
    * /store-api/v{version}/seo-url
    * /store-api/v{version}/language
    * /store-api/v{version}/currency

## Before

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

## After

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
    "total": 2,
    "aggregations": [],
    "elements": [
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
]
```
