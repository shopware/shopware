---
title: Context Gateway  
date: 2025-04-01
area: checkout
tags: [checkout, app, context, personalization, gateway, storefront]  
---

# ADR: Context Gateway Feature

## Context

Previously, we introduced the `CheckoutGateway` to allow app servers to influence the checkout process based on the current cart and sales channel context.  
This was a significant step toward enabling dynamic, app-driven decision-making during checkout.

However, this approach does not support modifying the storefront experience **outside** of the checkout flow.  
There is a growing need to allow app servers to influence the **sales channel context** — 
e.g. customer data, language, and currency — based on external logic or user-specific criteria.

In particular, some use cases require **context manipulation before a cart or checkout process begins**.  
To address this, we propose the introduction of a dedicated `ContextGateway`,
which provides a secure and structured communication channel between the storefront and the app server.

The gateway is initiated from the storefront client and allows apps to request context changes based on external decision logic.

### Example Use Cases

- Registering customers with custom or prefilled data (including guest and full customer registration)
- Logging in existing customers
- Switching the current language or currency
- Updating the active customer address

## Decision

### AppContextGateway

To encapsulate the logic for context-driven decisions, a new `AppContextGateway` will be introduced.

It receives a `ContextGatewayPayloadStruct`, which includes:

- The current `SalesChannelContext`
- The current `Cart`
- A `RequestDataBag` containing all custom data provided by the client

The gateway will return a `ContextTokenResponse`, which includes the updated token of the `SalesChannelContext` after executing all applicable commands.

Example implementation:

```php
<?php declare(strict_types=1);

class AppCheckoutGateway
{
    public function process(ContextGatewayPayloadStruct $payload): ContextTokenResponse;
}
```

#### Store-API

A new store API route, `ContextGatewayRoute` '/store-api/context/gateway', will be introduced.
This route will call the `AppContextGateway` implementation and respond accordingly.

Example implementation:

```php
<?php declare(strict_types=1);

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ContextGatewayRoute extends AbstractContextGatewayRoute
{
    public function __construct(
        private readonly AppContextGateway $contextGateway,
    ) {
    }

    public function getDecorated(): AbstractContextGatewayRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/context/gateway', name: 'store-api.context.gateway', methods: ['GET', 'POST'])]
    public function load(Request $request, Cart $cart, SalesChannelContext $context): ContextTokenResponse
    {
        $data = new RequestDataBag($request->request->all());

        return $this->contextGateway->process(new ContextGatewayPayloadStruct($cart, $context, $data));
    }
}
```

#### Storefront

A new `ContextGatewayController` for the Storefront will be introduced.
This controller will be the entry point for the Storefront client to interact with the `ContextGatewayRoute`.

Example implementation:

```php
<?php declare(strict_types=1);

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class ContextGatewayController extends StorefrontController
{
    public function __construct(
        private readonly AbstractContextGatewayRoute $contextGatewayRoute,
        private readonly CartService $cartService,
    ) {
    }

    #[Route('/gateway/context', name: 'frontend.gateway.context', defaults: ['XmlHttpRequest' => true], methods: ['GET', 'POST'])]
    public function gateway(Request $request, SalesChannelContext $context): Response
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        try {
            $response = $this->contextGatewayRoute->load($request, $cart, $context);
        } catch (\Throwable $e) {
            $this->addFlash(self::DANGER, $e->getMessage());
            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);
        }

        return $response;
    }
}
```

#### Storefront SDK

Even though the client is free to initiate the context workflow in any manner,
as long as an `XMLHttpRequest` is made to the `/store-api/gateway/context` endpoint,
we propose the introduction of a helper class, `ContextGatewayClient`, within the Storefront SDK.

This client provides a convenient and consistent interface for apps to interact with the context gateway,
reducing the need for manual request setup and improving developer experience.

This client will work similarly to the existing `AppClient`,
but is specifically designed for initiating context gateway flows from the storefront.

The `ContextGatewayClient` will be instantiated with the app name
and expose a `request()` method to trigger the `/store-api/gateway/context` endpoint,
optionally including custom parameters.

Example implementation:

```typescript
export default class ContextGatewayClientService {
    private readonly name: string;

    constructor(name: string) {
        this.name = name;
    }
    
    public async request(options: Record<string, any> = {}, handleRedirect: boolean = true): Promise<Response> {
        const body = { appName: this.name, ...options };
        
        const requestOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        }
        
        const response = await fetch(window.router['frontend.gateway.context'], requestOptions);
        const result = await response.json();

        if (result.redirectUrl) {
            window.location.href = result.redirectUrl;
        }
        
        window.location.reload();
    }
}
```

#### App Manifest

To enable support for the `ContextGateway`, apps must declare a new endpoint in their `manifest.xml` file.
This is done by defining a `context` sub-key under the existing `<gateways>` section.
The provided URL will be called by the `AppContextGateway` when the context flow is initiated from the storefront.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<manifest>
    <!-- ... -->

    <gateways>
        <!-- Optional: existing checkout gateway -->
        <!-- <checkout>https://example.com/checkout/gateway</checkout> -->

        <!-- Required for context gateway -->
        <context>https://example.com/context/gateway</context>
    </gateways>
</manifest>
```

### Command Structure

The `ContextGateway` will implement a command pattern inspired by the established structure used in the `CheckoutGateway`.

It will support a predefined set of commands that can be returned by the app server in the response.

Commands are executed **in the order they are provided**, with the following exception:

- The `context_register-customer` and `context_login-customer` commands are executed **first**, as they establish a new or updated context.
- All remaining commands will be executed **sequentially**, based on the order in the response, and operate on the resulting context.

This structure ensures that commands depending on user identity, language, or currency changes always act on the correct state.

#### Context Gateway App Payload

The app server receives a structured payload when invoked through the `AppContextGateway`.

The payload contains:

- The app source
- The current `SalesChannelContext`
- The current `Cart`
- Any **custom data** sent from the client (e.g., app name, user metadata, intent flags)

This payload enables app servers to make contextual decisions based on both the current session and additional input from the storefront.
Example payload sent to app-server:

```json5
{
    "source": {
        // information about the app source (version, shopId, etc.)
    },
    "salesChannelContext": SalesChannelContextObject,
    "cart": CartObject,
    "custom": {
        "anyCustomKey": "anyCustomValue"
        // "anyOtherCustomDataKey": "anyOtherCustomDataValue"
    },
    
}
```

Note that custom data sent by the client will be sent as a key-value pair under `custom` to the app-server.

#### Context Gateway App Response

Example response from app-server:

```json
[
  {
    "command": "context_register-customer",
    "payload": RegisterCustomerData,
  },
  {
    "command": "context_switch-language",
    "payload": {
        "iso": "de-DE"
    }
  },
  {
    "command": "context_switch-currency",
    "payload": {
        "iso": "USD"
    }
  }
]
```

Shopware will apply validation rules to ensure consistency and prevent conflicting command execution.

- The response must not contain more than **one `register-customer` or `login-customer` command**.
- All other command types must appear **at most once** in the response.

These constraints help prevent ambiguous or conflicting state changes during context manipulation.

### Command Structure

Each command in the context gateway is composed of two key elements:

- **Command class**  
  A class extending `AbstractContextGatewayCommand`.  
  This class holds the command’s payload and is uniquely identified by its command name.

- **Command handler class**  
  A class extending `AbstractContextGatewayCommandHandler`.  
  It contains the logic to execute supported commands and may handle multiple command types by implementing the `getSupportedCommands()` method.

This separation of data and execution logic ensures modularity and extensibility of the command processing system.

Example command class:

```php
<?php declare(strict_types=1);

class ChangeCurrencyCommand extends AbstractContextGatewayCommand
{
    public const COMMAND_KEY = 'context_change-currency';

    public function __construct(
        public readonly string $iso,
    ) {
    }

    public static function getDefaultKeyName(): string
    {
        return self::COMMAND_KEY;
    }
}
```

Example command handler class:

```php
<?php declare(strict_types=1);

class ChangeCurrencyCommandHandler extends AbstractContextGatewayCommandHandler
{
    public function __construct(
        private readonly EntityRepository $currencyRepository
    ) {
    }

    /**
     * @param ChangeCurrencyCommand $command
     */
    public function handle(AbstractContextGatewayCommand $command, SalesChannelContext $context, array &$parameters): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('isoCode', $command->iso));

        $currencyId = $this->currencyRepository->searchIds($criteria, $context->getContext())->firstId();

        if ($currencyId === null) {
            return;
        }

        // $parameters will be used by ContextSwitchRoute to update the context
        $parameters['currencyId'] = $currencyId;
    }

    public static function supportedCommands(): array
    {
        return [ChangeCurrencyCommand::class];
    }
```

#### Command Registry

Command handlers will be registered in the `ContextGatewayCommandRegistry` using a registry pattern.

All implementations of `AbstractContextGatewayCommandHandler` must be tagged with the `shopware.context.gateway.command` service tag.
This ensures they are automatically discovered and made available to the app system for command execution.

#### Event

A new event, `ContextGatewayCommandsCollectedEvent`, will be introduced.
This event is dispatched after the `AppContextGateway` collects all command responses from registered app servers, but before the commands are executed.

It allows plugins to inspect, modify, or append commands based on the full context and payload provided to the app servers.

## Consequences

### App PHP SDK

The `app-php-sdk` will be extended to support the new gateway and its data contract.

Enhancements include:

- Context gateway requests can be deserialized into a `ContextGatewayAction` object.
- Responses can easily be created with a `ContextGatewayResponse` object.
- Each supported context command will have a dedicated class, allowing strongly-typed manipulation and validation of its payload.

### App Bundle (Symfony)

The App Bundle will be updated to support the new gateway endpoint automatically.

Incoming context gateway requests and outgoing responses will be automatically resolved to the appropriate DTOs (`ContextGatewayAction`, `ContextGatewayResponse`), 
making it seamless for app developers to implement handlers using standard Symfony controllers.

## Security Considerations

The `ContextGateway` introduces new responsibilities and associated risks due to its ability to manipulate the `SalesChannelContext`.

Potential concerns include:

- **Customer impersonation**: App servers have the ability to log in existing customers, even without knowing their passwords.
- **Trust boundary risks**: If an app server is compromised or malicious, it could exploit the gateway to impersonate users,
  access sensitive customer data, or perform unauthorized actions. However, this issue has always existed with the plugin system.

Particularly, allowing login based solely on an email address — without verifying credentials — poses a significant security risk.

### Mitigation

- Gateway command execution must enforce strict validation, logging, and safeguards (e.g., rate limiting).
- The ability to log in a customer should be gated by additional checks or explicit trust settings per app (a proposal could be custom ACL permissions per app and action).
- All actions performed via the context gateway should be auditable and traceable for monitoring and incident response.
