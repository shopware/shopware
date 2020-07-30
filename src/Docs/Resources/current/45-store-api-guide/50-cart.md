[titleEn]: <>(Store api cart routes)
[hash]: <>(article:store_api_cart)

## Cart
Here you can find all the available routes for the cart. You should always send the header `sw-context-token` to work on the same cart.

### Fetching cart

The api `/store-api/v3/checkout/cart` can be used to fetch the current state of the cart.

```
POST /store-api/v3/checkout/cart
{
    "includes": {
        "cart": ["price", "lineItems"],
        "cart_price": ["netPrice", "totalPrice"]
    }
}
{
  "price": {
    "netPrice": 0,
    "totalPrice": 0,
    "apiAlias": "cart_price"
  },
  "lineItems": [],
  "apiAlias": "cart"
}
```


### Deleting entire cart

The api `/store-api/v3/checkout/cart` can be used to delete the entire cart.

```
DELETE /store-api/v3/checkout/cart
```

### Adding new items to the cart

The api `POST /store-api/v3/checkout/cart/line-item` can be used to add multiple new line items.

#### Product

```
POST /store-api/v3/checkout/cart/line-item
{
    "items": [
        {
            "type": "product",
            "referencedId": "<productId>"
        },
        {
            "type": "product",
            "referencedId": "<productId>",
            "quantity": 2
        }
    ]
}
```

You can set following properties on a product line item: `referencedId`, `payload` and `quantity`. When the Line Item is wrong miss-configured, the cart will add an error. This error is in the `error` key in the response.

#### Promotion

```
POST /store-api/v3/checkout/cart/line-item
{
    "items": [
        {
                "type": "promotion",
                "referencedId": "<promotionCode>"
            }
    ]
}
```

### Error-Handling

When you pass invalid line item configuration to the api the cart calculation process will remove the line items again and add errors to the cart. This errors are in the `error` key in the cart response.
An example for invalid `referencedId` would be look like this:

```json
"errors": {
    "product-not-foundfc2376912354406d80dd8887fc30ffa8": {
      "id": "fc2376912354406d80dd8887fc30ffa8",
      "message": "The product %s could not be found",
      "code": 0,
      "line": 166,
      "key": "product-not-foundfc2376912354406d80dd8887fc30ffa8",
      "level": 10,
      "messageKey": "product-not-found"
    }
  }
```

### Updating items in the cart

The api `PATCH /store-api/v3/checkout/cart/line-item` can be used to update line items in to cart.

```
PATCH /store-api/v3/checkout/cart/line-item
{
    "items": [
        {
            "id": "<id>",
            "quantity": <quantity>,
            "referencedId": "<newReferenceId>"
        }
    ]
}
```

### Deleting items in the cart

The api `DELETE /store-api/v3/checkout/cart/line-item` can be used to remove line items to the cart

```
DELETE /store-api/v3/checkout/cart/line-item
{
    "ids": [
        "<id>"
    ]
}
```

### Creating an order from cart

The api `/store-api/v3/checkout/order` can be used to create an order from the cart. You will need items in the cart and you need to be logged in.

```
POST /store-api/v3/checkout/order
{
    "includes": {
        "order": ["orderNumber", "price", "lineItems"],
        "order_line_item": ["label", "price"]
    }
}
{
  "orderNumber": "10060",
  "price": {
    "netPrice": 557.94,
    "totalPrice": 597,
    "calculatedTaxes": [
      {
        "tax": 39.06,
        "taxRate": 7,
        "price": 597,
        "apiAlias": "cart_tax_calculated"
      }
    ],
    "taxRules": [
      {
        "taxRate": 7,
        "percentage": 100,
        "apiAlias": "cart_tax_rule"
      }
    ],
    "positionPrice": 597,
    "taxStatus": "gross",
    "apiAlias": "cart_price"
  },
  "lineItems": [
    {
      "label": "Aerodynamic Bronze Prawn Crystals",
      "price": {
        "unitPrice": 597,
        "quantity": 1,
        "totalPrice": 597,
        "calculatedTaxes": [
          {
            "tax": 39.06,
            "taxRate": 7,
            "price": 597,
            "apiAlias": "cart_tax_calculated"
          }
        ],
        "taxRules": [
          {
            "taxRate": 7,
            "percentage": 100,
            "apiAlias": "cart_tax_rule"
          }
        ],
        "referencePrice": null,
        "listPrice": null,
        "apiAlias": "calculated_price"
      },
      "apiAlias": "order_line_item"
    }
  ],
  "apiAlias": "order"
}
```

### Payment methods

The api `/store-api/v3/payment-method` can be used to list all payment methods of the sales channel.
With the parameter `onlyAvailable` you can restrict the result to only valid payments methods.

Additionally, the api basic parameters (`filter`, `aggregations`, etc.) can be used to restrict the result, see [Reading entities](./../40-admin-api-guide/20-reading-entities.md).

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

### Shipping methods

The api `/store-api/v3/shipping-method` can be used to list all payment methods of the sales channel.
With the parameter `onlyAvailable` you can restrict the result to only valid shipping methods.

Additionally, the api basic parameters (`filter`, `aggregations`, etc.) can be used to restrict the result, see [Reading entities](./../40-admin-api-guide/20-reading-entities.md).

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
