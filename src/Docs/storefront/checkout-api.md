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
  * [Create a guest order](#create-guest-order)
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

# Checkout Storefront API

To be able to control the shopping cart of Shopware via the Storefront API, the route `/storefront-api/checkout` can be used. 

## Create a new cart
This route is used to create a new shopping cart. This is necessary to get a new context token for the checkout API which identifies a persisted shopping cart.

### Request
```
curl -X POST \
  http://shopware.development/storefront-api/checkout/cart \
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
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
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
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
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
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
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
```

## Change line item quantity
Line items which added via API, can be changed in quantity by sending a PATCH request to `storefront-api/checkout/cart/line-item/{id]/quantity/{quantity}`.

```
curl -X PATCH \
  http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10 \
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
``` 

## Delete a line item
Elements explicitly added to the shopping cart can also be deleted via the API. For items which dynamically added by the system, such as payment surcharges, this is not possible.
 
```
curl -X DELETE \
  http://shopware.development/storefront-api/checkout/line-item/b5719dba30e84f4187248ce0b75ca68b \
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
```

## Customer login
In order to create an order for a shopping cart, it is necessary, according to current state of development, to have a logged in customer for the given `x-sw-context-token`. For this a request can be sent against the `/storefront-api/customer/login` route:

```
curl -X POST \
  http://shopware.development/storefront-api/customer/login \
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
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
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
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
            "guest": 0,
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
                    "placedInFront": 0,
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
            "placedInFront": 0,
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
                "placedInFront": 0,
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

## Create a guest order
It is also possible to create an order as a guest:
```
curl -X POST \
  http://shopware.development/storefront-api/checkout/guest-order \
  -H 'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ' \
  -H 'Content-Type: application/json' \
  -H 'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    -d '{
    "email": "test@example.com",
    "firstName": "max",
    "lastName": "mustermann",
    "billingCountry": "Germany",
    "billingCity": "Berlin",
    "billingZipcode": "10350",
    "billingStreet": "Examplestreet 123"
}'
```

The created order is returned as response. Please see above for an example response.

You can also define a separate shipping address by using shippingCountry, shippingCity... 
If you don't provide shipping details, the billing details will be used.

## Examples

### PHP

#### Create a new cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/cart',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

#### Read cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/cart',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => [
        'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

#### Add line item to cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => "{\n\t\"type\": \"product\",\n\t\"quantity\": 1,\n\t\"payload\": {\n\t\t\"id\": \"b5719dba30e84f4187248ce0b75ca68b\"\n\t}\n}",
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIn0.eyJhdWQiOiJmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZmZiIsImp0aSI6IjVlMWUzOGIyNjI0MjAzM2U1NmEzNGExMjJmMjA4NWM5MWVkMjFkMzI3MGI5MTk4NzJkZjRmMTgwYzM0OTgxODM4ZmMwNjE4ZjMzM2RkN2ZmIiwiaWF0IjoxNTMwODY3NTEzLCJuYmYiOjE1MzA4Njc1MTMsImV4cCI6MTUzMDg3MTExMywic3ViIjoiIiwic2NvcGVzIjpbXX0.Rk0r2FFUPe14h830DCIgB-QcnDvf9KSAuxNGNpLFfW6KD_cRAdSX3JQm0sju4L0YgUugyXPZZLsLHkSmMP-yWD4t87EI_f2ODJl99ak7RWXzA_MF7e0LsE9knvApR3BIJavxVPjNWjSyvt6QvPNALAcGK5yamjdVRTUooHEmgSOKLHKOoYtUIOEUqRzU_q9UdHELN3UUDa3vZfqmPxBflsG0G5EhnSSpHMJrVZ3rwPu0vRCJ3anS1nfl3xeohSoxlooRv2iOsl2B_xkbLGYu2JpY9-eiWKkHIFaLHMtAvIIsHhOrfzM2hQyKhQh7niwkJYpcyEh1l7nZ6q7MhaSKqw',
        'Content-Type: application/json',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

##### Shorthand route for products
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/cart/product/b5719dba30e84f4187248ce0b75ca68b',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

#### Change line item quantity
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b/quantity/10',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'PATCH',
    CURLOPT_HTTPHEADER => [
        'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ',
        'Content-Type: application/json',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

#### Delete a line item
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/cart/line-item/b5719dba30e84f4187248ce0b75ca68b',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_HTTPHEADER => [
        'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ',
        'Content-Type: application/json',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

#### Customer login
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/customer/login',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => "{\n\t\"username\": \"test@example.com\",\n\t\"password\": \"shopware\"\n}",
    CURLOPT_HTTPHEADER => [
        'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ',
        'Content-Type: application/json',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```

#### Create order for cart
```php
<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'http://shopware.development/storefront-api/checkout/order',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'x-sw-access-key: SWSCSFB2VUQ4QTRKUHBVMEZNTQ',
        'Content-Type: application/json',
        'x-sw-context-token: c439592b53ab4e769987bfe5ceb021ed'
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #:' . $err;
} else {
    echo $response;
}
```
