[titleEn]: <>(Store api cart routes)
[hash]: <>(article:store_api_cart)

## Cart
Here you can find all the available routes for the cart.

### Available Payment methods
To get all available payment methods you can use this route: `store-api.payment.method`
You can use the `onlyAvailable` to list only ...

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v1/payment-method

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
You can get all available shipping methods via this route: `store-api.shipping.method`
For this route you also have the `onlyAvailable` parameter to fetch only ...

Additionally can use the api basic parameters (`filter`,  `aggregations`, etc.) for more information look [here](./../40-admin-api-guide/20-reading-entities.md).

```
POST /store-api/v1/shipping-method

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
