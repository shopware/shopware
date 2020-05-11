[titleEn]: <>(Store api cart routes)
[hash]: <>(article:store_api_cart)

## Cart
Here you can find all the available routes for the cart.

### Payment methods

The api `/store-api/v1/payment-method` can be used to list all payment methods of the sales channel.
With the parameter `onlyAvailable` you can restrict the result to only valid payments methods.

Additionally, the api basic parameters (`filter`, `aggregations`, etc.) can be used to restrict the result, see [Reading entities](./../40-admin-api-guide/20-reading-entities.md).

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

### Shipping methods

The api `/store-api/v1/shipping-method` can be used to list all payment methods of the sales channel.
With the parameter `onlyAvailable` you can restrict the result to only valid shipping methods.

Additionally, the api basic parameters (`filter`, `aggregations`, etc.) can be used to restrict the result, see [Reading entities](./../40-admin-api-guide/20-reading-entities.md).

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
