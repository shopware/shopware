---
title: Checkout gateway
date: 2024-04-01
area: checkout
tags: [checkout, app, payment, shipping, cart]
---

# ADR: Enhanced Checkout Gateway Feature
## Context
In response to the evolving landscape of checkout decision-making, we propose the introduction of a centralized and opinionated solution. 
This solution aims to facilitate informed decisions during the checkout process based on both the cart contents and the current sales channel context. 
The app-system, in particular, stands to benefit significantly, enabling seamless communication with the app server. 
Presently, achieving such functionality is constrained to app scripts, limiting the capacity for making nuanced decisions during checkout based on app server logic.

Moreover, payment and shipping providers necessitate specific criteria for determining the availability of their respective methods. 
These criteria include considerations such as risk assessment related to the current customer and cart, unavailability criteria, 
merchant connection status validation (e.g., checking for correct credentials), and service availability testing (e.g., detecting provider outages). 
Additionally, these providers require the ability to block carts during checkout based on risk assessment decisions.

While this ADR focuses on the aforementioned features, the implementation is designed to allow for seamless future extensions.

## Decision
### CheckoutGatewayInterface
To address the outlined challenges, we propose the introduction of the CheckoutGatewayInterface.
This interface will be invoked during the checkout process to determine a response tailored to the current cart and sales channel context.

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway;

use Shopware\Core\Checkout\Gateway\Command\Struct\CheckoutGatewayPayloadStruct;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface CheckoutGatewayInterface
{
    /**
    * The input struct consists of the cart, sales channel context and currently available payment and shipping methods.
    */
    public function process(CheckoutGatewayPayloadStruct): CheckoutGatewayResponse;
}
```

Plugin developers are encouraged to create custom implementations of the `CheckoutGatewayInterface` for their specific checkout logic based on decisions from external systems (e.g., ERP, PIM).

The `CheckoutGatewayResponse` will include an `EntityCollection` of payment and shipping methods suitable for the current context, along with a collection of `CartErrors`.
The input struct and the response is designed for future extension, allowing for more intricate decision-making during checkout.

#### Store-API
A new store API route, `CheckoutGatewayRoute` '/store-api/checkout/gateway', will be introduced.
This route will call a `CheckoutGatewayInterface` implementation and respond accordingly,
and is integral to `CartOrderRoute` requests, ensuring the cart's validity for checkout during the order process.

#### Storefront
The default invocation of the `CheckoutGatewayRoute` will occur during the checkout-confirm page and edit-order page (so-called "after order").
Any changes to the context (e.g., language, currency) will trigger a reload of the payment method selection, calling the app server again.

#### Checkout Gateway Commands
For streamlined response manipulation by plugins and app servers alike, we propose an executable chain of `CheckoutGatewayCommands`.
The implementation of the app-system will heavily rely on the command structure.
However, it is encouraged, but not mandatory for a custom implementation plugin-system implementation of the `CheckoutGatewayInterface` to follow the command structure.

These commands, chosen from a predefined set, can be responded with by plugins and app servers.
The initial release will include the following commands: `add-payment-method`, `remove-payment-method`, `add-shipping-method`, `remove-shipping-method`, and `add-cart-error`.
Depending on the command, the payload may differ, necessitating updates to the documentation.
We propose the use of a handler pattern, to facilitate the execution of these commands.
Commands will be executed in the order provided in the response.

### App-System
For the initial release, Shopware will support a single implementation of the `CheckoutGatewayInterface`, provided by the app-system.
The `AppCheckoutGateway` will sequentially call active apps, but only if the app has a defined `checkout-gateway-url` in its manifest.xml file.

#### App Manifest
To address challenges for apps, a new app endpoint can be defined in the manifest.xml.
A new key `gateways` will be added to the manifest file, with a sub-key `checkout` to define the endpoint.
The `gateways` key signalizes possible future similar endpoints for different purposes.
The checkout gateway endpoint is configured using a new element called `checkout`.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest>
    <!-- ... -->

    <gateways>
        <checkout>https://example.com/checkout/gateway</checkout>
    </gateways>
</manifest>
```

#### Checkout Gateway App Payload
The app server will receive the current `SalesChannelContext`, `Cart`, and available payment and shipping methods as part of the payload.
The `AppCheckoutGateway` will call the app server with this payload.

```json
{
    "salesChannelContext": SalesChannelContextObject,
    "cart": CartObject,
    "paymentMethods": [
        "payment-method-technical-name-1",
        "payment-method-technical-name-2",
        "payment-method-technical-name-3",
        ...
    ],
    "shippingMethods": [
        "shipping-method-technical-name-1",
        "shipping-method-technical-name-2",
        "shipping-method-technical-name-3",
        ...
    ]
}
```

Note that the paymentMethods and shippingMethods arrays will only contain the technical names of the methods, not the full entities.

#### Checkout Gateway App Response

```json
[
  {
    "command": "remove-payment-method",
    "payload": {
      "paymentMethodTechnicalName": "payment-myApp-payment-method"
    }
  },
  {
    "command": "add-cart-error",
    "payload": {
      "reason": "Payment method not available for this cart.",
      "level": 20,
      "blockOrder": true
    }
  }
]
```

#### Event
A new event `CheckoutGatewayCommandsCollectedEvent` will be introduced.
This event is dispatched after the `AppCheckoutGateway` has collected all commands from all app servers.
It allows plugins to manipulate the commands before they are executed, based on the same payload the app servers retrieve.

## Consequences
### App PHP SDK
The app-php-sdk will be enhanced to support the new endpoint and data types, ensuring seamless integration with the command structure. 
The following adaptations will be made:

Checkout gateway requests with payload will be deserialized into a `CheckoutGatewayRequest` object.
Checkout gateway responses will be deserialized into a `CheckoutGatewayResponse` object.
Every possible checkout gateway command will have a class representing it, facilitating easy manipulation of its payload.
