---
title: Multi sales channel sessions
date: 2026-10-09
area: framework
tags: [core, context, checkout, multi, sales channel, session, sessions, token, tokens]
---

## Context

Currently, a customer can only have one active session for a sales channel at a time, which is shared across all devices and browsers.

This means that if a context token is upgraded/changed to a new one (e.g., by logging out and back in on one device), the previous token becomes invalid, and any other device or browser using that token will lose the connection to the customer's context and drop it so they log out.

This leads to a poor customer experience, especially for customers who use multiple devices or browsers to shop.

Also the customer impersonation feature in the administration is affected by this, as it also relies on the same context token / session system, which logs out a customer, if a user used the imitate customer login finishes his work and logs out, which can be very frustrating for the customer, as they have to log in again on all devices and browsers.

In this ADR the **context token** is `SalesChannelContext::getToken()`, which is shared across devices and keys the cart. The **session token** is per device / browser, is what `sw-context-token` carries and resolves to a context.

## Decision

We will implement a multi sales channel session system, where a customer can have multiple active sessions for the same sales channel, allowing them to maintain their shopping context across multiple devices and browsers even better without the log out interruption.

Each session token is now backed by a `sales_channel_api_session` entry pointing to a context, which allows for multiple sessions which all share the same base context data, but can have their own imitating user id and session payload, which allows for per session additional / custom data.

Until release of Shopware 6.8 we will support both the old single session system and the new multi session system, toggleable via the `MULTI_CONTEXT_SESSIONS` feature flag, to ensure a smooth transition and allow us to gather feedback and address any issues before fully rolling out the new system.

With the flag off there is exactly one session per context with the same token value as the context, so the flag can be switched in both directions. The migration creates one session per existing context with the same token value.

### API

With the flag on, the session token is the only token a client knows:

- `sw-context-token` and the `token` of the context in the store-api JSON carry the session token, `jsonSerialize` maps it
- `ContextTokenResponse` and the header emitters hand out the session token
- `SalesChannelContextTokenChangeEvent` means rotation of the session token
- a context token is not accepted as a client token anymore, only sessions resolve, except for migrated rows where both are equal until the first rotation

### Checkout/Cart

For the checkout area, we will still use the context token of the sales channel context which is shared across all sessions of the same customer, allowing the customer to maintain the same cart across multiple devices and browsers, while still having a separate session token per device / browser.

`getCart($context->getToken(), $context)` keeps returning the shared cart, so we do not introduce a separate cart token. A cart always goes hand in hand with a context, several carts for one customer are several contexts and a session points at one of them.

### Customer impersonation

Currently the customer impersonation feature works by storing the impersonation information in the current session of the page and loading it from there in a context scope, which is then used to impersonate the customer in the storefront.

With the new multi session system, we will change this to store the impersonation information next to the session payload in a new table, which correctly separates the session data from the context data.

### Storage

We will add the `sales_channel_api_session` table to support multiple sessions per customer and sales channel.

Current storage structure:

```
sales_channel_api_context
- token
- payload
- sales_channel_id
- customer_id
- updated_at
```

The current structure only allows for one session per customer and sales channel, as we only allow one entry per customer and sales channel combination.

New storage structure:

```
sales_channel_api_context
- token
- payload
- sales_channel_id
- customer_id
- updated_at

sales_channel_api_session
- token
- sales_channel_api_context_token
- imitating_user_id
- session_payload
- updated_at
```

The tables are linked by the `sales_channel_api_context_token` foreign key in `sales_channel_api_session` to `token` in `sales_channel_api_context`, which allows for multiple sessions per customer and sales channel, as each session is now stored in the `sales_channel_api_session` table and can have its own session payload, while still sharing the same base payload and cart token from the `sales_channel_api_context` table.

### Token generation and management

When a new session token is created, e.g. by initiating a new sales channel context for a device or browser which does not have an active session yet, a new entry will be created in the `sales_channel_api_session` table with a reference to the corresponding `sales_channel_api_context` entry and the generated token.

When a customer logs in, by default we will check if there is already an active context for the customer and sales channel, and if so, we will just link the new session token to the existing context, which allows the customer to keep using the same cart across all devices and browsers without interruption. This is the default, not a rule: a customer can have several contexts (e.g. sub-carts or B2B employees), the session points at one of them and extensions can change the resolution.

At the moment a customer logs out, we just drop the session token from the `sales_channel_api_session` table and keep the `sales_channel_api_context` entry, which allows the customer to keep their cart and context data intact, while just removing the session token which is used to access the context over the session.

A logout-all deletes all sessions of the context. Revoking the tokens of a customer (password change, customer delete) means revoking all sessions.

### Expiry

A session expires based on `sales_channel_api_session.updated_at`, which is refreshed on use. The cleanup task deletes expired sessions and afterwards the contexts no session points at anymore, e.g. the guest context after a login.

### Session payload

Each session can now contain an optional session payload, which allows for additional session data per sesion. This can be used to store any additional information that is specific to a certain device or browser. Refering fields such as the `imitating_user_id` for customer impersonation, will be stored next to the session payload instead of inside of it to keep e.g. foreign key relations intact and allow for easier querying of the data.

The session payload is never merged into the context payload or the factory options, the context payload is the only input for building the context and calculating the cart.

On the PHP side the session is a typed struct `SalesChannelContextSession` on the `SalesChannelContext`. `getImitatingUserId()` and `setImitatingUserId()` on the `SalesChannelContext` delegate to it and get deprecated. `SalesChannelContext::getSessionToken()` delegates to the session token, while `getToken()` keeps returning the context token.

```php
class SalesChannelContextSession extends Struct
{
    public function __construct(
        protected string $token,
        protected ?string $imitatingUserId = null,
        protected array $payload = [],
    ) {
    }

    public function getToken(): string;

    public function getImitatingUserId(): ?string;

    public function setImitatingUserId(?string $imitatingUserId): void;

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void;
}
```

`token` and `imitatingUserId` are hydrated from the explicit columns, `payload` from `session_payload`. Plugins can attach further per session data as extensions of the struct.

## Consequences

- Add `MULTI_CONTEXT_SESSIONS` feature flag to enable/disable the multi session system until it is fully rolled out and stable in Shopware release 6.8
- Introduce `sales_channel_api_session` table
- `imitatingUserId` will be moved from the session data to the session table
- `session_payload` column in `sales_channel_api_session` allows for per session additional / custom data, which is never merged into the context payload
- The session token becomes the client facing token (`sw-context-token`, store-api `token`), the context token keeps keying the cart
- Migration creates one session per existing context
- Introduce `SalesChannelContextSession` struct on the `SalesChannelContext` with `getSessionToken()` as delegate
- `SalesChannelContext::getImitatingUserId()` and `setImitatingUserId()` are deprecated and delegate to the session
- Cleanup task expires sessions and removes contexts without sessions
