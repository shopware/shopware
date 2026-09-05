---
title: Multi sales channel context token
date: 2026-04-17
area: framework
tags: [core, context, checkout, multi, sales channel, token]
---

## Context

Currently, a customer can only have one active context token for a sales channel at a time, which is shared across all devices and browsers.

This means that if a context token is upgraded/changed to a new one (e.g., by logging out and back in on one device), the previous token becomes invalid, and any other device or browser using that token will lose the connection to the customer's context.

This leads to a poor customer experience, especially for customers who use multiple devices or browsers to shop.

Also the customer impersonation feature in the administration is affected by this, as it also relies on the same context token, which logs out a customer, if a user used the imitate customer login, finishes his work and logs out, which can be very frustrating for the customer, as they have to log in again on all devices and browsers.

## Decision

We will implement a multi sales channel context token system, where a customer can have multiple active context tokens for the same sales channel, allowing them to maintain their shopping context across multiple devices and browsers without interruption.

Each context token can now contain an optional additional payload to allow per token overrides or custom data for a context.

Until release of Shopware 6.8 we will support both the old single token system and the new multi token system, toggleable via the `MULTI_CONTEXT_TOKENS` feature flag, to ensure a smooth transition and allow us to gather feedback and address any issues before fully rolling out the new system.

### Checkout/Cart

For the checkout area, we will introduce a new `cartToken` on the sales channel context which is shared across all context tokens of the same customer, allowing the customer to maintain the same cart across multiple devices and browsers, while still having separate context tokens for each device and browser.

When loading or writing a cart, developers will now have to use the new `cartToken` of a sales channel context instead of the `token` to ensure that the correct cart is used, as the `token` now only refers to the context token which can be different for each device and browser, while the `cartToken` is shared across all tokens for the same customer and sales channel.

### Customer impersonation

Currently the customer impersonation feature works by storing the impersonation information in the current session of the page and loading it from there in a context scope, which is then used to impersonate the customer in the storefront.

With the new multi token system, we will change this to store the impersonation information in the additional payload of the context token, which unifies the context data and correctly separates the session data from the context data.

### Storage

We will deprecate the `sales_channel_api_context` table (will be completely removed in Shopware release 6.8) and introduce the `sales_channel_context` and `sales_channel_context_token` tables to support multiple context tokens per customer and sales channel.

Current storage structure:

```
sales_channel_api_context
- token
- payload
- sales_channel_id
- customer_id
- updated_at
```

The current structure only allows for one token per customer and sales channel, as we only allow one entry per customer and sales channel combination.

New storage structure:

```
sales_channel_context
- id
- cart_token
- payload
- sales_channel_id
- customer_id
- updated_at

sales_channel_context_token
- token
- sales_channel_context_id
- additional_payload
- updated_at
```

The `sales_channel_context` looks quite similar to the old `sales_channel_api_context`, but it now contains a `cart_token` which is shared across all context tokens of the same customer. It also no longer contains the `token` field, as the token is now stored in the `sales_channel_context_token` table, which allows for multiple tokens per customer and sales channel.

The tables are linked by the `sales_channel_context_id` foreign key in `sales_channel_context_token` to `id` in `sales_channel_context`, which allows for multiple tokens per customer and sales channel, as each token is now stored in the `sales_channel_context_token` table and can have its own additional payload, while still sharing the same base payload and cart token from the `sales_channel_context` table.

### Token generation and management

When a new context token is created, e.g. by initiating a new sales channel context for a device or browser which does not have an active token yet, a new entry will be created in the `sales_channel_context` table and a new entry will be created in the `sales_channel_context_token` table with a reference to the corresponding `sales_channel_context` entry and the generated token.

When a customer logs in, we will check if there is already an active context for the customer and sales channel, and if so, we will just link the new token to the existing context by updating the `sales_channel_context_token` table to point to the existing `sales_channel_context` entry, which allows the customer to keep using the same cart across all devices and browsers without interruption.

At the moment a customer logs out, we just drop the token from the `sales_channel_context_token` table and keep the `sales_channel_context` entry, which allows the customer to keep their cart and context data intact, while just removing the token which is used to access the context.

### Additional payload

Each context token can now contain an optional additional payload, which allows for per token overrides or custom data for a context. This can be used to store any additional information that is specific to a certain device or browser, such as the `imitatingUserId` for customer impersonation, or any other custom data that is needed for a specific use case.

You should carefully consider, which data should be overridden per token, as it can lead to different behavior on different devices and browsers for the same customer, depending on which data you override in the additional payload of the token. It is not recommended to override cart related data in the additional payload, as it is shared across all tokens for the same customer and sales channel via the `cartToken` in the `sales_channel_context` table, which ensures that the cart is consistent across all devices and browsers for the same customer.

## Consequences

- Add `MULTI_CONTEXT_TOKENS` feature flag to enable/disable the multi context token system until it is fully rolled out and stable in Shopware release 6.8
- Deprecate `sales_channel_api_context` table
- Introduce `sales_channel_context` and `sales_channel_context_token` tables
- For loading or writing a cart, developers will now have to use the new `cartToken` of a sales channel context instead of the `token` to ensure that the correct cart is used
- `imitatingUserId` will be moved from the session data to the additional payload of the context token
- `additional_payload` field in `sales_channel_context_token` allows for per token overrides or custom data
