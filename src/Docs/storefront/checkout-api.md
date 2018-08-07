- [Checkout Storefront API](#checkout-storefront-api)
  * [Create a new cart](#create-a-new-cart)
    + [Request](#request)
    + [Response](#response)
  * [Read cart](#read-cart)
    + [Request](#request-1)
    + [Response](#response-1)
  * [Add line item to cart](#add-line-item-to-cart)
    + [Shorthand route for products](#shorthand-route-for-products)
  * [Change line item quantity](#change-line-item-quantity)
  * [Delete a line item](#delete-a-line-item)
  * [Customer login](#customer-login)
  * [Create order for cart](#create-order-for-cart)
  * [Examples](#examples)
    + [PHP](#php)
      - [Create a new cart](#create-a-new-cart-1)
      - [Read cart](#read-cart-1)
      - [Add line item to cart](#add-line-item-to-cart-1)
        * [Shorthand route for products](#shorthand-route-for-products-1)
      - [Change line item quantity](#change-line-item-quantity-1)
      - [Delete a line item](#delete-a-line-item-1)
      - [Customer login](#customer-login-1)
      - [Create order for cart](#create-order-for-cart-1)
    + [Python](#python)
      - [Create a new cart](#create-a-new-cart-2)
      - [Read cart](#read-cart-2)
      - [Add line item to cart](#add-line-item-to-cart-2)
        * [Shorthand route for products](#shorthand-route-for-products-2)
      - [Change line item quantity](#change-line-item-quantity-2)
      - [Delete a line item](#delete-a-line-item-2)
      - [Customer login](#customer-login-2)
      - [Create order for cart](#create-order-for-cart-2)
    + [Java](#java)
      - [Create a new cart](#create-a-new-cart-3)
      - [Read cart](#read-cart-3)
      - [Add line item to cart](#add-line-item-to-cart-3)
        * [Shorthand route for products](#shorthand-route-for-products-3)
      - [Change line item quantity](#change-line-item-quantity-3)
      - [Delete a line item](#delete-a-line-item-3)
      - [Customer login](#customer-login-3)
      - [Create order for cart](#create-order-for-cart-3)
    + [Javascript](#javascript)
      - [Create a new cart](#create-a-new-cart-4)
    + [Read cart](#read-cart-4)
      - [Add line item to cart](#add-line-item-to-cart-4)
        * [Shorthand route for products](#shorthand-route-for-products-4)
      - [Change line item quantity](#change-line-item-quantity-4)
      - [Delete a line item](#delete-a-line-item-4)
      - [Customer login](#customer-login-4)
      - [Create order for cart](#create-order-for-cart-4)
    + [jQuery](#jquery)
      - [Create a new cart](#create-a-new-cart-5)
      - [Read cart](#read-cart-5)
      - [Add line item to cart](#add-line-item-to-cart-5)
        * [Shorthand route for products](#shorthand-route-for-products-5)
      - [Change line item quantity](#change-line-item-quantity-5)
      - [Delete a line item](#delete-a-line-item-5)
      - [Customer login](#customer-login-5)
      - [Create order for cart](#create-order-for-cart-5)
    + [NodeJS Native](#nodejs-native)
      - [Create a new cart](#create-a-new-cart-6)
      - [Read cart](#read-cart-6)
      - [Add line item to cart](#add-line-item-to-cart-6)
        * [Shorthand route for products](#shorthand-route-for-products-6)
      - [Change line item quantity](#change-line-item-quantity-6)
      - [Delete a line item](#delete-a-line-item-6)
      - [Customer login](#customer-login-6)
      - [Create order for cart](#create-order-for-cart-6)
    + [Go](#go)
      - [Create a new cart](#create-a-new-cart-7)
      - [Read cart](#read-cart-7)
      - [Add line item to cart](#add-line-item-to-cart-7)
        * [Shorthand route for products](#shorthand-route-for-products-7)
      - [Change line item quantity](#change-line-item-quantity-7)
      - [Delete a line item](#delete-a-line-item-7)
      - [Customer login](#customer-login-7)
      - [Create order for cart](#create-order-for-cart-7)

# Checkout Storefront API

To be able to control the shopping cart of Shopware via the Storefront API, the route `/storefront-api/checkout` can be used. 

## Create a new cart
This route is used to create a new shopping cart. This is necessary to get a new context token for the checkout API which identifies a persisted shopping cart.

### Request
```
curl -X POST \
  http://shopware.development/storefront-api/checkout/cart \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw' \
```

### Response
```json
{
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed"
}
```

The created shopping cart is now available via this token. All further requests for this shopping cart have to send this token.

## Read cart
To read out a shopping cart, the `/storefront-api/checkout/cart` route can be addressed by GET. It is important, as with all other described example requests, that the `x-sw-context-token` is also sent.

### Request
```
curl -X GET \
  http://shopware.development/storefront-api/checkout/cart \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
```

### Response
```json
{
    "data": {
        "price": {
            "netPrice": 410.08,
            "totalPrice": 488,
            "calculatedTaxes": [
                {
                    "tax": 77.92,
                    "taxRate": 19,
                    "price": 488,
                    "extensions": []
                }
            ],
            "taxRules": [
                {
                    "rate": 19,
                    "percentage": 100,
                    "extensions": []
                }
            ],
            "positionPrice": 488,
            "taxStatus": "gross",
            "extensions": []
        },
        "cart": {
            "name": "shopware",
            "lineItems": [
                {
                    "identifier": "b5719dba30e84f4187248ce0b75ca68b",
                    "quantity": 1,
                    "type": "product",
                    "payload": {
                        "id": "b5719dba30e84f4187248ce0b75ca68b"
                    },
                    "priceDefinition": null,
                    "extensions": []
                }
            ],
            "token": "c439592b53ab4e769987bfe5ceb021ed",
            "errors": [],
            "extensions": []
        },
        "calculatedLineItems": [
            {
                "lineItem": {
                    "identifier": "b5719dba30e84f4187248ce0b75ca68b",
                    "quantity": 1,
                    "type": "product",
                    "payload": {
                        "id": "b5719dba30e84f4187248ce0b75ca68b"
                    },
                    "priceDefinition": null,
                    "extensions": []
                },
                "price": {
                    "unitPrice": 488,
                    "quantity": 1,
                    "totalPrice": 488,
                    "calculatedTaxes": [
                        {
                            "tax": 77.92,
                            "taxRate": 19,
                            "price": 488,
                            "extensions": []
                        }
                    ],
                    "taxRules": [
                        {
                            "rate": 19,
                            "percentage": 100,
                            "extensions": []
                        }
                    ],
                    "extensions": []
                },
                "identifier": "b5719dba30e84f4187248ce0b75ca68b",
                "quantity": 1,
                "delivery": null,
                "rule": null,
                "cover": null,
                "inStockDeliveryDate": {
                    "earliest": "2018-07-07T16:00:00+00:00",
                    "latest": "2018-07-09T16:00:00+00:00",
                    "extensions": []
                },
                "outOfStockDeliveryDate": {
                    "earliest": "2018-07-08T16:00:00+00:00",
                    "latest": "2018-07-10T16:00:00+00:00",
                    "extensions": []
                },
                "children": [],
                "extensions": []
            }
        ],
        "deliveries": [
            {
                "positions": [
                    {
                        "calculatedLineItem": {
                            "lineItem": {
                                "identifier": "b5719dba30e84f4187248ce0b75ca68b",
                                "quantity": 1,
                                "type": "product",
                                "payload": {
                                    "id": "b5719dba30e84f4187248ce0b75ca68b"
                                },
                                "priceDefinition": null,
                                "extensions": []
                            },
                            "price": {
                                "unitPrice": 488,
                                "quantity": 1,
                                "totalPrice": 488,
                                "calculatedTaxes": [
                                    {
                                        "tax": 77.92,
                                        "taxRate": 19,
                                        "price": 488,
                                        "extensions": []
                                    }
                                ],
                                "taxRules": [
                                    {
                                        "rate": 19,
                                        "percentage": 100,
                                        "extensions": []
                                    }
                                ],
                                "extensions": []
                            },
                            "identifier": "b5719dba30e84f4187248ce0b75ca68b",
                            "quantity": 1,
                            "delivery": null,
                            "rule": null,
                            "cover": null,
                            "inStockDeliveryDate": {
                                "earliest": "2018-07-07T16:00:00+00:00",
                                "latest": "2018-07-09T16:00:00+00:00",
                                "extensions": []
                            },
                            "outOfStockDeliveryDate": {
                                "earliest": "2018-07-08T16:00:00+00:00",
                                "latest": "2018-07-10T16:00:00+00:00",
                                "extensions": []
                            },
                            "children": [],
                            "extensions": []
                        },
                        "quantity": 1,
                        "price": {
                            "unitPrice": 488,
                            "quantity": 1,
                            "totalPrice": 488,
                            "calculatedTaxes": [
                                {
                                    "tax": 77.92,
                                    "taxRate": 19,
                                    "price": 488,
                                    "extensions": []
                                }
                            ],
                            "taxRules": [
                                {
                                    "rate": 19,
                                    "percentage": 100,
                                    "extensions": []
                                }
                            ],
                            "extensions": []
                        },
                        "identifier": "b5719dba30e84f4187248ce0b75ca68b",
                        "deliveryDate": {
                            "earliest": "2018-07-07T16:00:00+00:00",
                            "latest": "2018-07-09T16:00:00+00:00",
                            "extensions": []
                        },
                        "extensions": []
                    }
                ],
                "location": {
                    "country": {
                        "areaId": "5cff02b1029741a4891c430bcd9e3603",
                        "name": "Deutschland",
                        "iso": "DE",
                        "position": 1,
                        "shippingFree": false,
                        "taxFree": false,
                        "taxfreeForVatId": false,
                        "taxfreeVatidChecked": false,
                        "active": true,
                        "iso3": "DEU",
                        "displayStateInRegistration": false,
                        "forceStateInRegistration": false,
                        "createdAt": "2017-12-14T15:25:56+00:00",
                        "updatedAt": null,
                        "area": null,
                        "states": null,
                        "translations": null,
                        "taxAreaRules": null,
                        "orderAddresses": null,
                        "customerAddresses": null,
                        "salesChannels": null,
                        "id": "20080911ffff4fffafffffff19830531",
                        "tenantId": "20080911ffff4fffafffffff19830531",
                        "extensions": {
                            "translated": {
                                "name": true
                            }
                        },
                        "versionId": null,
                        "countryAreaVersionId": null
                    },
                    "state": null,
                    "address": null,
                    "extensions": []
                },
                "deliveryDate": {
                    "earliest": "2018-07-07T16:00:00+00:00",
                    "latest": "2018-07-09T16:00:00+00:00",
                    "extensions": []
                },
                "shippingMethod": {
                    "type": 0,
                    "bindShippingfree": false,
                    "bindLaststock": false,
                    "name": "Standard Versand",
                    "active": true,
                    "position": 1,
                    "calculation": 1,
                    "surchargeCalculation": 3,
                    "taxCalculation": 0,
                    "shippingFree": null,
                    "bindTimeFrom": null,
                    "bindTimeTo": null,
                    "bindInstock": false,
                    "bindWeekdayFrom": null,
                    "bindWeekdayTo": null,
                    "bindWeightFrom": null,
                    "bindWeightTo": 1,
                    "bindPriceFrom": null,
                    "bindPriceTo": null,
                    "bindSql": null,
                    "statusLink": "",
                    "calculationSql": null,
                    "createdAt": "2017-12-14T15:45:50+00:00",
                    "updatedAt": null,
                    "description": "",
                    "comment": "",
                    "prices": [],
                    "minDeliveryTime": 1,
                    "maxDeliveryTime": 2,
                    "translations": null,
                    "orderDeliveries": null,
                    "salesChannel": null,
                    "id": "20080911ffff4fffafffffff19830531",
                    "tenantId": "20080911ffff4fffafffffff19830531",
                    "extensions": {
                        "translated": {
                            "name": true,
                            "description": true,
                            "comment": true
                        }
                    },
                    "versionId": null
                },
                "shippingCosts": {
                    "unitPrice": 0,
                    "quantity": 1,
                    "totalPrice": 0,
                    "calculatedTaxes": [
                        {
                            "tax": 0,
                            "taxRate": 19,
                            "price": 0,
                            "extensions": []
                        }
                    ],
                    "taxRules": [
                        {
                            "rate": 19,
                            "percentage": 100,
                            "extensions": []
                        }
                    ],
                    "extensions": []
                },
                "endDeliveryDate": {
                    "earliest": "2018-07-08T16:00:00+00:00",
                    "latest": "2018-07-11T16:00:00+00:00",
                    "extensions": []
                },
                "extensions": []
            }
        ],
        "transactions": [
            {
                "amount": {
                    "unitPrice": 488,
                    "quantity": 1,
                    "totalPrice": 488,
                    "calculatedTaxes": [
                        {
                            "tax": 77.92,
                            "taxRate": 19,
                            "price": 488,
                            "extensions": []
                        }
                    ],
                    "taxRules": [
                        {
                            "rate": 19,
                            "percentage": 100,
                            "extensions": []
                        }
                    ],
                    "extensions": []
                },
                "paymentMethodId": "e84976ace9ab4928a3dcc387b66dbaa6",
                "extensions": []
            }
        ],
        "extensions": [],
        "shippingCosts": {
            "unitPrice": 0,
            "quantity": 1,
            "totalPrice": 0,
            "calculatedTaxes": [
                {
                    "tax": 0,
                    "taxRate": 19,
                    "price": 0,
                    "extensions": []
                }
            ],
            "taxRules": [
                {
                    "rate": 19,
                    "percentage": 100,
                    "extensions": []
                }
            ],
            "extensions": []
        }
    }
}
```

## Add line item to cart
To add a line item to the shopping cart, the route POST `/storefront-api/checkout/cart/line-item/{id}` can be used. This route allows you to put all kinds of items into the shopping cart, regardless of whether it's an item from Shopware or a plugin.

```
curl -X POST \
  http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed' \
  -d '{
	"type": "product",
	"quantity": 1,
	"payload": {
		"id": "b5719dba30e84f4187248ce0b75ca68b"
	}
}'
```

### Shorthand route for products
To add a product to the shopping cart, there is a separate route which abstracts the above parameters.

```
curl -X POST \
  http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
```

## Change line item quantity
Line items which added via API, can be changed in quantity by sending a PATCH request to `storefront-api/checkout/cart/line-item/{id]/quantity/{quantity}`.

```
curl -X PATCH \
  http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10 \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
``` 

## Delete a line item
Elements explicitly added to the shopping cart can also be deleted via the API. For items which dynamically added by the system, such as payment surcharges, this is not possible.
 
```
curl -X DELETE \
  http://shopware.development/storefront-api/checkout/line-item/b5719dba30e84f4187248ce0b75ca68b \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
```

## Customer login
In order to create an order for a shopping cart, it is necessary, according to current state of development, to have a logged in customer for the given `x-sw-context-token`. For this a request can be sent against the `/storefront-api/customer/login` route:

```
curl -X POST \
  http://shopware.development/storefront-api/customer/login \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed' \
  -d '{
	"username": "test@example.com",
	"password": "shopware"
}'
```

Notice: This request does not require the `x-sw-context-token`.

## Create order for cart
Once the customer is logged in, an order can be created for the shopping cart:
```
curl -X POST \
  http://shopware.development/storefront-api/checkout/order \
  -H 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
```

The created order is returned as a response:
```json
{
    "data": {
        "customerId": "ee81c3809a3f4f71a7f27c24a326f726",
        "stateId": "1194a493806742c9b85e61f1f2cf9be8",
        "paymentMethodId": "47160b00cd064b0188176451f9f3c247",
        "currencyId": "20080911ffff4fffafffffff19830531",
        "salesChannelId": "20080911ffff4fffafffffff19830531",
        "billingAddressId": "97a9404ef6c744a9bf3a10b9b393619c",
        "date": "2018-07-06T12:09:25+00:00",
        "amountTotal": 488,
        "positionPrice": 488,
        "shippingTotal": 0,
        "isNet": false,
        "isTaxFree": false,
        "createdAt": "2018-07-06T12:09:25+00:00",
        "updatedAt": null,
        "customer": {
            "groupId": "20080911ffff4fffafffffff19830531",
            "defaultPaymentMethodId": "47160b00cd064b0188176451f9f3c247",
            "salesChannelId": "20080911ffff4fffafffffff19830531",
            "lastPaymentMethodId": null,
            "defaultBillingAddressId": "a18b1fc617564025895a911808c4c58e",
            "defaultShippingAddressId": "4d3318e90be645d388221f48ecd78cbb",
            "number": "1337",
            "salutation": "Herr",
            "firstName": "Max",
            "lastName": "Mustermann",
            "password": "$argon2i$v=19$m=1024,t=2,p=2$eGI5cXRKeFhtOFo4ZzhBNQ$HdiiH3U7PhqIpiDG8Cu/M/yT7Pv0VOg59CpwhyK7mio",
            "email": "test@example.com",
            "title": null,
            "encoder": "md5",
            "active": true,
            "accountMode": 0,
            "confirmationKey": null,
            "firstLogin": null,
            "lastLogin": null,
            "sessionId": null,
            "newsletter": false,
            "validation": "",
            "affiliate": false,
            "referer": null,
            "internalComment": null,
            "failedLogins": 0,
            "lockedUntil": null,
            "birthday": null,
            "createdAt": "2018-07-06T07:35:34+00:00",
            "updatedAt": null,
            "group": {
                "name": "Shopkunden",
                "displayGross": true,
                "inputGross": true,
                "hasGlobalDiscount": false,
                "percentageGlobalDiscount": 0,
                "minimumOrderAmount": 10,
                "minimumOrderAmountSurcharge": 5,
                "createdAt": "2017-12-14T15:25:58+00:00",
                "updatedAt": null,
                "discounts": null,
                "translations": null,
                "taxAreaRules": null,
                "customers": null,
                "id": "20080911ffff4fffafffffff19830531",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": {
                    "translated": {
                        "name": true
                    }
                },
                "versionId": null
            },
            "defaultPaymentMethod": {
                "pluginId": null,
                "technicalName": "prepayment",
                "name": "Paid in advance",
                "additionalDescription": "The goods are delivered directly upon receipt of payment.",
                "template": "prepayment.tpl",
                "class": "Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment",
                "table": "",
                "hide": false,
                "percentageSurcharge": null,
                "absoluteSurcharge": null,
                "surchargeString": "",
                "position": 1,
                "active": true,
                "allowEsd": false,
                "usedIframe": "",
                "hideProspect": false,
                "action": null,
                "source": null,
                "mobileInactive": false,
                "riskRules": null,
                "createdAt": "2017-12-14T15:45:46+00:00",
                "updatedAt": null,
                "plugin": null,
                "translations": null,
                "transactions": null,
                "orders": null,
                "customers": null,
                "salesChannels": null,
                "id": "47160b00cd064b0188176451f9f3c247",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": {
                    "translated": {
                        "name": true,
                        "additionalDescription": true
                    }
                },
                "versionId": null
            },
            "salesChannel": {
                "languageId": "20080911ffff4fffafffffff19830531",
                "currencyId": "20080911ffff4fffafffffff19830531",
                "paymentMethodId": "e84976ace9ab4928a3dcc387b66dbaa6",
                "shippingMethodId": "20080911ffff4fffafffffff19830531",
                "countryId": "20080911ffff4fffafffffff19830531",
                "type": "storefront_api",
                "name": "Storefront API endpoint",
                "accessKey": "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
                "secretAccessKey": "$argon2i$v=19$m=1024,t=2,p=2$MTU3QmVaZ0hqOGhQRGpYVQ$Wq1BN48pRXyI+RTn7wBjcHpwtzrptrqXiBfc6uwmh50",
                "catalogIds": [
                    "20080911ffff4fffafffffff19830531"
                ],
                "currencyIds": [
                    "20080911ffff4fffafffffff19830531"
                ],
                "languageIds": [
                    "20080911ffff4fffafffffff19830531"
                ],
                "configuration": [],
                "active": true,
                "taxCalculationType": "vertical",
                "createdAt": "2018-07-06T07:11:41+00:00",
                "updatedAt": null,
                "currency": {
                    "factor": 1,
                    "symbol": "€",
                    "shortName": "EUR",
                    "name": "Euro",
                    "isDefault": true,
                    "symbolPosition": 0,
                    "position": 0,
                    "createdAt": "2017-12-14T15:25:57+00:00",
                    "updatedAt": null,
                    "translations": null,
                    "orders": null,
                    "salesChannels": null,
                    "productPriceRules": null,
                    "id": "20080911ffff4fffafffffff19830531",
                    "tenantId": "20080911ffff4fffafffffff19830531",
                    "extensions": {
                        "translated": {
                            "shortName": true,
                            "name": true
                        }
                    },
                    "versionId": null
                },
                "language": {
                    "parentId": null,
                    "localeId": "2f3663edb7614308a60188c21c7963d5",
                    "name": "Default language",
                    "localeVersionId": null,
                    "createdAt": null,
                    "updatedAt": null,
                    "locale": {
                        "code": "en_GB",
                        "name": "Englisch",
                        "territory": "Vereinigtes Königreich",
                        "createdAt": "2017-12-14T15:25:59+00:00",
                        "updatedAt": null,
                        "translations": null,
                        "users": null,
                        "languages": null,
                        "id": "2f3663edb7614308a60188c21c7963d5",
                        "tenantId": "20080911ffff4fffafffffff19830531",
                        "extensions": {
                            "translated": {
                                "name": true,
                                "territory": true
                            }
                        },
                        "versionId": null
                    },
                    "parent": null,
                    "children": null,
                    "salesChannels": null,
                    "mediaAlbumTranslations": null,
                    "countryAreaTranslations": null,
                    "categoryTranslations": null,
                    "countryStateTranslations": null,
                    "countryTranslations": null,
                    "currencyTranslations": null,
                    "customerGroupTranslations": null,
                    "listingFacetTranslations": null,
                    "listingSortingTranslations": null,
                    "localeTranslations": null,
                    "mediaTranslations": null,
                    "orderStateTranslations": null,
                    "paymentMethodTranslations": null,
                    "productManufacturerTranslations": null,
                    "productTranslations": null,
                    "shippingMethodTranslations": null,
                    "taxAreaRuleTranslations": null,
                    "unitTranslations": null,
                    "orderTransactionStateTranslations": null,
                    "configurationGroupTranslations": null,
                    "configurationGroupOptionTranslations": null,
                    "searchDocuments": null,
                    "snippets": null,
                    "id": "20080911ffff4fffafffffff19830531",
                    "tenantId": "20080911ffff4fffafffffff19830531",
                    "extensions": []
                },
                "paymentMethod": null,
                "shippingMethod": null,
                "country": null,
                "orders": null,
                "customers": null,
                "id": "20080911ffff4fffafffffff19830531",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": [],
                "currencyVersionId": null,
                "paymentMethodVersionId": null,
                "shippingMethodVersionId": null,
                "countryVersionId": null
            },
            "lastPaymentMethod": null,
            "defaultBillingAddress": {
                "customerId": "ee81c3809a3f4f71a7f27c24a326f726",
                "countryId": "ffe61e1c99154f9597014a310ab5482d",
                "countryStateId": null,
                "salutation": "Herr",
                "firstName": "Max",
                "lastName": "Mustermann",
                "zipcode": "10332",
                "city": "Berlin",
                "company": null,
                "department": null,
                "title": null,
                "street": "Bahnhofstraße 27",
                "vatId": null,
                "phoneNumber": null,
                "additionalAddressLine1": null,
                "additionalAddressLine2": null,
                "createdAt": "2018-07-06T07:35:34+00:00",
                "updatedAt": null,
                "country": {
                    "areaId": "dde2e7c598144e73ba03b093107ce5cf",
                    "name": "Griechenland",
                    "iso": "GR",
                    "position": 10,
                    "shippingFree": false,
                    "taxFree": false,
                    "taxfreeForVatId": false,
                    "taxfreeVatidChecked": false,
                    "active": false,
                    "iso3": "GRC",
                    "displayStateInRegistration": false,
                    "forceStateInRegistration": false,
                    "createdAt": "2017-12-14T15:25:56+00:00",
                    "updatedAt": null,
                    "area": null,
                    "states": null,
                    "translations": null,
                    "taxAreaRules": null,
                    "orderAddresses": null,
                    "customerAddresses": null,
                    "salesChannels": null,
                    "id": "ffe61e1c99154f9597014a310ab5482d",
                    "tenantId": "20080911ffff4fffafffffff19830531",
                    "extensions": {
                        "translated": {
                            "name": true
                        }
                    },
                    "versionId": null,
                    "countryAreaVersionId": null
                },
                "countryState": null,
                "customer": null,
                "id": "a18b1fc617564025895a911808c4c58e",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": [],
                "versionId": null,
                "customerVersionId": null,
                "countryVersionId": null,
                "countryStateVersionId": null
            },
            "defaultShippingAddress": {
                "customerId": "ee81c3809a3f4f71a7f27c24a326f726",
                "countryId": "ffe61e1c99154f9597014a310ab5482d",
                "countryStateId": null,
                "salutation": "Herr",
                "firstName": "Max",
                "lastName": "Mustermann",
                "zipcode": "48624",
                "city": "Schöppingen",
                "company": null,
                "department": null,
                "title": null,
                "street": "Ebbinghoff 10",
                "vatId": null,
                "phoneNumber": null,
                "additionalAddressLine1": null,
                "additionalAddressLine2": null,
                "createdAt": "2018-07-06T07:35:34+00:00",
                "updatedAt": null,
                "country": {
                    "areaId": "dde2e7c598144e73ba03b093107ce5cf",
                    "name": "Griechenland",
                    "iso": "GR",
                    "position": 10,
                    "shippingFree": false,
                    "taxFree": false,
                    "taxfreeForVatId": false,
                    "taxfreeVatidChecked": false,
                    "active": false,
                    "iso3": "GRC",
                    "displayStateInRegistration": false,
                    "forceStateInRegistration": false,
                    "createdAt": "2017-12-14T15:25:56+00:00",
                    "updatedAt": null,
                    "area": null,
                    "states": null,
                    "translations": null,
                    "taxAreaRules": null,
                    "orderAddresses": null,
                    "customerAddresses": null,
                    "salesChannels": null,
                    "id": "ffe61e1c99154f9597014a310ab5482d",
                    "tenantId": "20080911ffff4fffafffffff19830531",
                    "extensions": {
                        "translated": {
                            "name": true
                        }
                    },
                    "versionId": null,
                    "countryAreaVersionId": null
                },
                "countryState": null,
                "customer": null,
                "id": "4d3318e90be645d388221f48ecd78cbb",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": [],
                "versionId": null,
                "customerVersionId": null,
                "countryVersionId": null,
                "countryStateVersionId": null
            },
            "activeBillingAddress": null,
            "activeShippingAddress": null,
            "addresses": null,
            "orders": null,
            "autoIncrement": 51,
            "id": "ee81c3809a3f4f71a7f27c24a326f726",
            "tenantId": "20080911ffff4fffafffffff19830531",
            "extensions": [],
            "versionId": null,
            "customerGroupVersionId": null,
            "defaultPaymentMethodVersionId": null,
            "lastPaymentMethodVersionId": null
        },
        "state": {
            "description": "Offen",
            "position": 1,
            "hasMail": false,
            "createdAt": "2018-01-08T09:12:15+00:00",
            "updatedAt": null,
            "translations": null,
            "orders": null,
            "orderDeliveries": null,
            "id": "1194a493806742c9b85e61f1f2cf9be8",
            "tenantId": "20080911ffff4fffafffffff19830531",
            "extensions": {
                "translated": {
                    "description": true
                }
            },
            "versionId": null
        },
        "paymentMethod": {
            "pluginId": null,
            "technicalName": "prepayment",
            "name": "Paid in advance",
            "additionalDescription": "The goods are delivered directly upon receipt of payment.",
            "template": "prepayment.tpl",
            "class": "Shopware\\Core\\Checkout\\Payment\\Cart\\PaymentHandler\\PrePayment",
            "table": "",
            "hide": false,
            "percentageSurcharge": null,
            "absoluteSurcharge": null,
            "surchargeString": "",
            "position": 1,
            "active": true,
            "allowEsd": false,
            "usedIframe": "",
            "hideProspect": false,
            "action": null,
            "source": null,
            "mobileInactive": false,
            "riskRules": null,
            "createdAt": "2017-12-14T15:45:46+00:00",
            "updatedAt": null,
            "plugin": null,
            "translations": null,
            "transactions": null,
            "orders": null,
            "customers": null,
            "salesChannels": null,
            "id": "47160b00cd064b0188176451f9f3c247",
            "tenantId": "20080911ffff4fffafffffff19830531",
            "extensions": {
                "translated": {
                    "name": true,
                    "additionalDescription": true
                }
            },
            "versionId": null
        },
        "currency": {
            "factor": 1,
            "symbol": "€",
            "shortName": "EUR",
            "name": "Euro",
            "isDefault": true,
            "symbolPosition": 0,
            "position": 0,
            "createdAt": "2017-12-14T15:25:57+00:00",
            "updatedAt": null,
            "translations": null,
            "orders": null,
            "salesChannels": null,
            "productPriceRules": null,
            "id": "20080911ffff4fffafffffff19830531",
            "tenantId": "20080911ffff4fffafffffff19830531",
            "extensions": {
                "translated": {
                    "shortName": true,
                    "name": true
                }
            },
            "versionId": null
        },
        "salesChannel": {
            "languageId": "20080911ffff4fffafffffff19830531",
            "currencyId": "20080911ffff4fffafffffff19830531",
            "paymentMethodId": "e84976ace9ab4928a3dcc387b66dbaa6",
            "shippingMethodId": "20080911ffff4fffafffffff19830531",
            "countryId": "20080911ffff4fffafffffff19830531",
            "type": "storefront_api",
            "name": "Storefront API endpoint",
            "accessKey": "b1FTZGVSd2xoSmRBWnhlZldnOVhEZTBXandRb2M0VXA",
            "secretAccessKey": "$argon2i$v=19$m=1024,t=2,p=2$MTU3QmVaZ0hqOGhQRGpYVQ$Wq1BN48pRXyI+RTn7wBjcHpwtzrptrqXiBfc6uwmh50",
            "catalogIds": [
                "20080911ffff4fffafffffff19830531"
            ],
            "currencyIds": [
                "20080911ffff4fffafffffff19830531"
            ],
            "languageIds": [
                "20080911ffff4fffafffffff19830531"
            ],
            "configuration": [],
            "active": true,
            "taxCalculationType": "vertical",
            "createdAt": "2018-07-06T07:11:41+00:00",
            "updatedAt": null,
            "currency": {
                "factor": 1,
                "symbol": "€",
                "shortName": "EUR",
                "name": "Euro",
                "isDefault": true,
                "symbolPosition": 0,
                "position": 0,
                "createdAt": "2017-12-14T15:25:57+00:00",
                "updatedAt": null,
                "translations": null,
                "orders": null,
                "salesChannels": null,
                "productPriceRules": null,
                "id": "20080911ffff4fffafffffff19830531",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": {
                    "translated": {
                        "shortName": true,
                        "name": true
                    }
                },
                "versionId": null
            },
            "language": {
                "parentId": null,
                "localeId": "2f3663edb7614308a60188c21c7963d5",
                "name": "Default language",
                "localeVersionId": null,
                "createdAt": null,
                "updatedAt": null,
                "locale": {
                    "code": "en_GB",
                    "name": "Englisch",
                    "territory": "Vereinigtes Königreich",
                    "createdAt": "2017-12-14T15:25:59+00:00",
                    "updatedAt": null,
                    "translations": null,
                    "users": null,
                    "languages": null,
                    "id": "2f3663edb7614308a60188c21c7963d5",
                    "tenantId": "20080911ffff4fffafffffff19830531",
                    "extensions": {
                        "translated": {
                            "name": true,
                            "territory": true
                        }
                    },
                    "versionId": null
                },
                "parent": null,
                "children": null,
                "salesChannels": null,
                "mediaAlbumTranslations": null,
                "countryAreaTranslations": null,
                "categoryTranslations": null,
                "countryStateTranslations": null,
                "countryTranslations": null,
                "currencyTranslations": null,
                "customerGroupTranslations": null,
                "listingFacetTranslations": null,
                "listingSortingTranslations": null,
                "localeTranslations": null,
                "mediaTranslations": null,
                "orderStateTranslations": null,
                "paymentMethodTranslations": null,
                "productManufacturerTranslations": null,
                "productTranslations": null,
                "shippingMethodTranslations": null,
                "taxAreaRuleTranslations": null,
                "unitTranslations": null,
                "orderTransactionStateTranslations": null,
                "configurationGroupTranslations": null,
                "configurationGroupOptionTranslations": null,
                "searchDocuments": null,
                "snippets": null,
                "id": "20080911ffff4fffafffffff19830531",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": []
            },
            "paymentMethod": null,
            "shippingMethod": null,
            "country": null,
            "orders": null,
            "customers": null,
            "id": "20080911ffff4fffafffffff19830531",
            "tenantId": "20080911ffff4fffafffffff19830531",
            "extensions": [],
            "currencyVersionId": null,
            "paymentMethodVersionId": null,
            "shippingMethodVersionId": null,
            "countryVersionId": null
        },
        "billingAddress": {
            "countryId": "ffe61e1c99154f9597014a310ab5482d",
            "countryStateId": null,
            "salutation": "Herr",
            "firstName": "Max",
            "lastName": "Mustermann",
            "street": "Bahnhofstraße 27",
            "zipcode": "10332",
            "city": "Berlin",
            "company": null,
            "department": null,
            "title": null,
            "vatId": null,
            "phoneNumber": null,
            "additionalAddressLine1": null,
            "additionalAddressLine2": null,
            "createdAt": "2018-07-06T12:09:25+00:00",
            "updatedAt": null,
            "country": {
                "areaId": "dde2e7c598144e73ba03b093107ce5cf",
                "name": "Griechenland",
                "iso": "GR",
                "position": 10,
                "shippingFree": false,
                "taxFree": false,
                "taxfreeForVatId": false,
                "taxfreeVatidChecked": false,
                "active": false,
                "iso3": "GRC",
                "displayStateInRegistration": false,
                "forceStateInRegistration": false,
                "createdAt": "2017-12-14T15:25:56+00:00",
                "updatedAt": null,
                "area": null,
                "states": null,
                "translations": null,
                "taxAreaRules": null,
                "orderAddresses": null,
                "customerAddresses": null,
                "salesChannels": null,
                "id": "ffe61e1c99154f9597014a310ab5482d",
                "tenantId": "20080911ffff4fffafffffff19830531",
                "extensions": {
                    "translated": {
                        "name": true
                    }
                },
                "versionId": null,
                "countryAreaVersionId": null
            },
            "countryState": null,
            "orders": null,
            "orderDeliveries": null,
            "id": "97a9404ef6c744a9bf3a10b9b393619c",
            "tenantId": "20080911ffff4fffafffffff19830531",
            "extensions": [],
            "versionId": null,
            "countryVersionId": null,
            "countryStateVersionId": null
        },
        "deliveries": null,
        "lineItems": null,
        "transactions": null,
        "autoIncrement": 51,
        "id": "fb5722980c0c4b9691fb55bf0ce732c5",
        "tenantId": "20080911ffff4fffafffffff19830531",
        "extensions": [],
        "versionId": null,
        "customerVersionId": null,
        "orderStateVersionId": null,
        "paymentMethodVersionId": null,
        "currencyVersionId": null,
        "billingAddressVersionId": null
    }
}
```

## Examples

### PHP

#### Create a new cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/cart",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

#### Read cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/cart",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "GET",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

#### Add line item to cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "{\n\t\"type\": \"product\",\n\t\"quantity\": 1,\n\t\"payload\": {\n\t\t\"id\": \"b5719dba30e84f4187248ce0b75ca68b\"\n\t}\n}",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw",
    "Content-Type: application/json",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

##### Shorthand route for products
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

#### Change line item quantity
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "PATCH",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA",
    "Content-Type: application/json",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

#### Delete a line item
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "DELETE",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA",
    "Content-Type: application/json",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

#### Customer login
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/customer/login",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_POSTFIELDS => "{\n\t\"username\": \"test@example.com\",\n\t\"password\": \"shopware\"\n}",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA",
    "Content-Type: application/json",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

#### Create order for cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => "http://shopware.development/storefront-api/checkout/order",
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => "",
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => "POST",
  CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA",
    "Content-Type: application/json",
    "x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed"
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo "cURL Error #:" . $err;
} else {
  echo $response;
}
```

### Python

#### Create a new cart
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
    }

conn.request("POST", "storefront-api,checkout,cart", headers=headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

#### Read cart
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
    }

conn.request("GET", "storefront-api,checkout,cart", headers=headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

#### Add line item to cart
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

payload = "{\"type\":\"product\",\"quantity\":1,\"payload\":{\"id\":\"b5719dba30e84f4187248ce0b75ca68b\"}}"

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
    }

conn.request("POST", "storefront-api,checkout,cart,line-item,b5719dba30e84f4187248ce0b75ca68b", payload, headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

##### Shorthand route for products
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
    }

conn.request("POST", "storefront-api,checkout,cart/product,b5719dba30e84f4187248ce0b75ca68b", headers=headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

#### Change line item quantity
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
    }

conn.request("PATCH", "storefront-api,checkout,cart,line-item,b5719dba30e84f4187248ce0b75ca68b,quantity,10", headers=headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

#### Delete a line item
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
    }

conn.request("DELETE", "storefront-api,checkout,b5719dba30e84f4187248ce0b75ca68b", headers=headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

#### Customer login
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

payload = "{\"username\": \"test@example.com\", \"password\": \"shopware\"}"

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
    }

conn.request("POST", "storefront-api,customer,login", payload, headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

#### Create order for cart
```
import http.client

conn = http.client.HTTPConnection("shopware,development")

headers = {
    'x-sw-context-token': "c439592b53ab4e769987bfe5ceb021ed",
    'Content-Type': "application/json",
    'Authorization': "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
    }

conn.request("POST", "storefront-api,checkout,order", headers=headers)

res = conn.getresponse()
data = res.read()

print(data.decode("utf-8"))
```

### Java

#### Create a new cart
```
OkHttpClient client = new OkHttpClient();

Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/cart")
  .post(null)
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")
  .build();

Response response = client.newCall(request).execute();
```

#### Read cart
```
OkHttpClient client = new OkHttpClient();

Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/cart")
  .get()
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")
  .build();

Response response = client.newCall(request).execute();
```

#### Add line item to cart
```
OkHttpClient client = new OkHttpClient();

MediaType mediaType = MediaType.parse("application/json");
RequestBody body = RequestBody.create(mediaType, "{\"type\":\"product\",\"quantity\":1,\"payload\":{\"id\":\"b5719dba30e84f4187248ce0b75ca68b\"}}");
Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b")
  .post(body)
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")
  .build();

Response response = client.newCall(request).execute();
```

##### Shorthand route for products
```
OkHttpClient client = new OkHttpClient();

Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b")
  .post(null)
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")
  .build();

Response response = client.newCall(request).execute();
```

#### Change line item quantity
```
OkHttpClient client = new OkHttpClient();

MediaType mediaType = MediaType.parse("application/json");
Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10")
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")
  .build();

Response response = client.newCall(request).execute();
```

#### Delete a line item
```
OkHttpClient client = new OkHttpClient();

Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b")
  .delete(null)
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")
  .build();

Response response = client.newCall(request).execute();
```

#### Customer login
```
OkHttpClient client = new OkHttpClient();

MediaType mediaType = MediaType.parse("application/json");
RequestBody body = RequestBody.create(mediaType, "{\"username\": \"test@example.com\", \"password\": \"shopware\"}");
Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/customer/login")
  .post(body)
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")
  .build();

Response response = client.newCall(request).execute();
```

#### Create order for cart
```
OkHttpClient client = new OkHttpClient();

Request request = new Request.Builder()
  .url("http://shopware.development/storefront-api/checkout/order")
  .post(null)
  .addHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
  .addHeader("Content-Type", "application/json")
  .addHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")
  .build();

Response response = client.newCall(request).execute();
```

### Javascript

#### Create a new cart
```
var data = null;

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/checkout/cart");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw");

xhr.send(data);
```

### Read cart
```
var data = null;

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("GET", "http://shopware.development/storefront-api/checkout/cart");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA");

xhr.send(data);
```

#### Add line item to cart
```
var data = JSON.stringify({
  "type": "product",
  "quantity": 1,
  "payload": {
    "id": "b5719dba30e84f4187248ce0b75ca68b"
  }
});

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw");

xhr.send(data);
```

##### Shorthand route for products
```
var data = null;

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA");

xhr.send(data);
```

#### Change line item quantity
```
var data = null;

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("PATCH", "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA");

xhr.send(data);
```

#### Delete a line item
```
var data = null;

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("DELETE", "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA");

xhr.send(data);
```

#### Customer login
```
var data = JSON.stringify({
  "username": "test@example.com",
  "password": "shopware"
});

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/customer/login");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA");

xhr.send(data);
```

#### Create order for cart
```
var data = null;

var xhr = new XMLHttpRequest();
xhr.withCredentials = true;

xhr.addEventListener("readystatechange", function () {
  if (this.readyState === 4) {
    console.log(this.responseText);
  }
});

xhr.open("POST", "http://shopware.development/storefront-api/checkout/order");
xhr.setRequestHeader("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed");
xhr.setRequestHeader("Content-Type", "application/json");
xhr.setRequestHeader("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA");

xhr.send(data);
```

### jQuery

#### Create a new cart
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/cart",
  "method": "POST",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

#### Read cart
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/cart",
  "method": "GET",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

#### Add line item to cart
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b",
  "method": "POST",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
  },
  "processData": false,
  "data": "{\"type\":\"product\",\"quantity\":1,\"payload\":{\"id\":\"b5719dba30e84f4187248ce0b75ca68b\"}}"
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

##### Shorthand route for products
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b",
  "method": "POST",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

#### Change line item quantity
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10",
  "method": "PATCH",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

#### Delete a line item
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b",
  "method": "DELETE",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

#### Customer login
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/customer/login",
  "method": "POST",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  },
  "processData": false,
  "data": "{\"username\": \"test@example.com\", \"password\": \"shopware\"}"
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

#### Create order for cart
```
var settings = {
  "async": true,
  "crossDomain": true,
  "url": "http://shopware.development/storefront-api/checkout/order",
  "method": "POST",
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
}

$.ajax(settings).done(function (response) {
  console.log(response);
});
```

### NodeJS Native

#### Create a new cart
```
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "cart"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.end();
```

#### Read cart
```
var http = require("http");

var options = {
  "method": "GET",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "cart"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.end();
```

#### Add line item to cart
```
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "cart",
    "line-item",
    "b5719dba30e84f4187248ce0b75ca68b"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.write(JSON.stringify({ type: 'product',
  quantity: 1,
  payload: { id: 'b5719dba30e84f4187248ce0b75ca68b' } }));
req.end();
```

##### Shorthand route for products
```
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "cart",
    "product",
    "b5719dba30e84f4187248ce0b75ca68b"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.end();
```

#### Change line item quantity
```
var http = require("http");

var options = {
  "method": "PATCH",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "cart",
    "b5719dba30e84f4187248ce0b75ca68b"
    "quantity",
    "quantity",
    "10",
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.write(JSON.stringify({ quantity: 10 }));
req.end();
```

#### Delete a line item
```
var http = require("http");

var options = {
  "method": "DELETE",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "line-item",
    "b5719dba30e84f4187248ce0b75ca68b"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.end();
```

#### Customer login
```
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "customer",
    "login"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.write(JSON.stringify({ username: 'test@example.com', password: 'shopware' }));
req.end();
```

#### Create order for cart
```
var http = require("http");

var options = {
  "method": "POST",
  "hostname": [
    "shopware",
    "development"
  ],
  "path": [
    "storefront-api",
    "checkout",
    "order"
  ],
  "headers": {
    "x-sw-context-token": "c439592b53ab4e769987bfe5ceb021ed",
    "Content-Type": "application/json",
    "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA"
  }
};

var req = http.request(options, function (res) {
  var chunks = [];

  res.on("data", function (chunk) {
    chunks.push(chunk);
  });

  res.on("end", function () {
    var body = Buffer.concat(chunks);
    console.log(body.toString());
  });
});

req.end();
```

### Go

#### Create a new cart
```
package main

import (
	"fmt"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/cart"

	req, _ := http.NewRequest("POST", url, nil)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

#### Read cart
```
package main

import (
	"fmt"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/cart"

	req, _ := http.NewRequest("GET", url, nil)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

#### Add line item to cart
```
package main

import (
	"fmt"
	"strings"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b"

	payload := strings.NewReader("{\"type\":\"product\",\"quantity\":1,\"payload\":{\"id\":\"b5719dba30e84f4187248ce0b75ca68b\"}}")

	req, _ := http.NewRequest("POST", url, payload)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

##### Shorthand route for products
```
package main

import (
	"fmt"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b"

	req, _ := http.NewRequest("POST", url, nil)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

#### Change line item quantity
```
package main

import (
	"fmt"
	"strings"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10"

	req, _ := http.NewRequest("PATCH", url, nil)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

#### Delete a line item
```
package main

import (
	"fmt"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b"

	req, _ := http.NewRequest("DELETE", url, nil)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

#### Customer login
```
package main

import (
	"fmt"
	"strings"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/customer/login"

	payload := strings.NewReader("{\"username\": \"test@example.com\", \"password\": \"shopware\"}")

	req, _ := http.NewRequest("POST", url, payload)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```

#### Create order for cart
```
package main

import (
	"fmt"
	"net/http"
	"io/ioutil"
)

func main() {

	url := "http://shopware.development/storefront-api/checkout/order"

	req, _ := http.NewRequest("POST", url, nil)

	req.Header.Add("x-sw-context-token", "c439592b53ab4e769987bfe5ceb021ed")
	req.Header.Add("Content-Type", "application/json")
	req.Header.Add("Authorization", "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4In0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6ImJkNWIyMTBjZWIwNWU2ZmNiNWQ4ZDdjYmViODdjMWIxMmMxNWQ2NmZjYzQxMDlmOTkwM2Y4MzI0OGRiYWM4NWQzMTU3ODg2ZjI2ZjM2YzI4IiwiaWF0IjoxNTMwODc4NTE0LCJuYmYiOjE1MzA4Nzg1MTQsImV4cCI6MTUzMDg4MjExNCwic3ViIjoiIiwic2NvcGVzIjpbXX0.bDQpOgWj6iFJS9sd_WtyCkqC1Xverqg6l_l57upDEt03kcQcbh1fevSutitpMD1hoE4xxj7GqxOJ2zf-czP8mrtf7AQSIKgDq-WWggUrNQGs_yAe5JhKcOUgHN1LXxjS22XcvCnBY530dVVXFtZf7Web-qi7T3Hw-zDbSz147UGqlamaguijHj9gKv1Mz9I8yijMDP1tCrRzfMCt8mi90nFrzgwzllNBSTItPpca0RHBINuRCCS2dnS9q32bTgszmCxtoO0eCSuU__5-Cu9Pl0Yjm8My9lvsnAc1itiH0f2bbOfGljwAsUB_HpSCZwFdir94LTZiZG6qF21kvnPhJA")

	res, _ := http.DefaultClient.Do(req)

	defer res.Body.Close()
	body, _ := ioutil.ReadAll(res.Body)

	fmt.Println(res)
	fmt.Println(string(body))

}
```
