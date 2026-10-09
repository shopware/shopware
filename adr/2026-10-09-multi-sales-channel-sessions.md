---
title: Multi sales channel sessions
date: 2026-10-09
area: framework
tags: [core, context, checkout, multi, sales channel, session, sessions, token, tokens]
---

## Context

Currently, a customer can only have one active session for a sales channel at a time, which is shared across all devices and browsers.

This means that if a context token / session is upgraded/changed to a new one (e.g., by logging out and back in on one device), the previous token / session becomes invalid, and any other device or browser using that token / session will lose the connection to the customer's context and drop it so they log out.

This leads to a poor customer experience, especially for customers who use multiple devices or browsers to shop.

Also the customer impersonation feature in the administration is affected by this, as it also relies on the same context token / session system, which logs out a customer, if a user used the imitate customer login finishes his work and logs out, which can be very frustrating for the customer, as they have to log in again on all devices and browsers.

## Decision

We will implement a multi sales channel session system, where a customer can have multiple active sessions for the same sales channel, allowing them to maintain their shopping context across multiple devices and browsers even better without the log out interruption.

Each context is now backed by a `sales_channel_api_session` entry, which allows for multiple sessions which all share the same base context data, but can have their own imitating user id and session payload, which allows for per session additional / custom data.

Until release of Shopware 6.8 we will support both the old single session system and the new multi session system, toggleable via the `MULTI_CONTEXT_SESSIONS` feature flag, to ensure a smooth transition and allow us to gather feedback and address any issues before fully rolling out the new system.

### Checkout/Cart

For the checkout area, we will still use the context token of the sales channel context which is shared across all sessions of the same customer, allowing the customer to maintain the same cart across multiple devices and browsers, while still having a separate session token per device / browser.

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

When a new context session token is created, e.g. by initiating a new sales channel context for a device or browser which does not have an active session yet, a new entry will be created in the `sales_channel_api_session` table with a reference to the corresponding `sales_channel_api_context` entry and the generated token.

When a customer logs in, we will check if there is already an active context for the customer and sales channel, and if so, we will just link the new session token to the existing context, which allows the customer to keep using the same cart across all devices and browsers without interruption.

At the moment a customer logs out, we just drop the session token from the `sales_channel_api_session` table and keep the `sales_channel_api_context` entry, which allows the customer to keep their cart and context data intact, while just removing the session token which is used to access the context over the session.

### Session payload

Each session can now contain an optional session payload, which allows for additional session data per sesion. This can be used to store any additional information that is specific to a certain device or browser. Refering fields such as the `imitating_user_id` for customer impersonation, will be stored next to the session payload instead of inside of it to keep e.g. foreign key relations intact and allow for easier querying of the data.

## Consequences

- Add `MULTI_CONTEXT_SESSIONS` feature flag to enable/disable the multi session system until it is fully rolled out and stable in Shopware release 6.8
- Introduce `sales_channel_api_session` table
- `imitatingUserId` will be moved from the session data to the session table
- `session_payload` column in `sales_channel_api_session` allows for per session additional / custom data
