# Cursor Rule for MCP Tools

Cursor requires reading MCP tool schemas before every tool call. This adds a visible file-lookup step to each interaction, which is slow and confusing for users. You can eliminate this by creating a `.cursor/rules/` file that embeds the tool schemas directly.

## The problem

When an AI agent in Cursor uses an MCP tool, Cursor's system prompt instructs it to first read the tool's JSON descriptor file, then call the tool. This means every MCP interaction starts with a file search that the user can see but gains nothing from.

## The fix

Create a Cursor rule (`.mdc` file) with `alwaysApply: true` that lists all tool schemas inline. The agent receives this context automatically and can skip the descriptor lookup.

## Setup

Copy the example below into `.cursor/rules/shopware-mcp-tools.mdc` in your project root. If you have additional tools from plugins or apps, add them to the list.

## Example rule

```markdown
---
description: Shopware MCP tool schemas -- skip schema lookup, call tools directly
alwaysApply: true
---

# Shopware MCP Tools

When using Shopware MCP tools, call them directly without reading the schema files first.

## Tool Reference

### shopware-entity-search
Primary data retrieval. Search entities using Admin API criteria.
- **Required**: `entity` (string)
- **Optional**: `criteria` (string JSON, default "{}"), `limit` (int, default 25), `page` (int, default 1), `term` (string)

### shopware-entity-read
Read a single entity by UUID.
- **Required**: `entity` (string), `id` (string)
- **Optional**: `criteria` (string JSON, default "{}")

### shopware-entity-upsert
Create or update entity data. Always dryRun first.
- **Required**: `entity` (string), `payload` (string JSON)
- **Optional**: `dryRun` (bool, default true)

### shopware-entity-delete
Delete entities by UUIDs. Always dryRun first.
- **Required**: `entity` (string), `ids` (string)
- **Optional**: `dryRun` (bool, default true)

### shopware-entity-schema
Get field/association schema of an entity definition.
- **Required**: `entity` (string)

### shopware-order-summary
Look up order by number or UUID.
- **Optional**: `orderNumber` (string), `orderId` (string)

### shopware-order-cancel
Cancel order including transactions/deliveries. Always dryRun first.
- **Optional**: `orderNumber` (string), `orderId` (string), `refundTransactions` (bool, default false), `dryRun` (bool, default true)

### shopware-customer-lookup
Look up customer by email, number, or UUID.
- **Optional**: `email` (string), `customerNumber` (string), `customerId` (string)

### shopware-product-create
Create product with human-readable inputs. Always dryRun first.
- **Required**: `name` (string), `productNumber` (string), `grossPrice` (number)
- **Optional**: `taxRate` (number, default 19), `currencyCode` (string, default "EUR"), `stock` (int, default 0), `description` (string), `categories` (string), `active` (bool, default true), `dryRun` (bool, default true)

### shopware-bestseller-report
Top-selling products by quantity in a date range.
- **Required**: `from` (string ISO 8601), `to` (string ISO 8601)
- **Optional**: `limit` (int, default 10), `salesChannelId` (string)

### shopware-revenue-report
Revenue report for a date range.
- **Required**: `from` (string ISO 8601), `to` (string ISO 8601)
- **Optional**: `groupBy` (string: day/week/month, default "day"), `salesChannelId` (string)

### shopware-cart-manage
Manage storefront shopping cart. Actions: create, add, remove, update, get.
- **Required**: `salesChannelId` (string), `action` (string)
- **Optional**: `token` (string), `productId` (string), `quantity` (int, default 1), `lineItemId` (string), `customerId` (string|null)

### shopware-cart-checkout
Place order from existing cart. Always dryRun first.
- **Required**: `salesChannelId` (string), `token` (string), `customerId` (string)
- **Optional**: `paymentMethodId` (string), `shippingMethodId` (string), `dryRun` (bool, default true)

### shopware-checkout-methods
List payment/shipping methods for a sales channel.
- **Required**: `salesChannelId` (string)
- **Optional**: `type` (string: payment/shipping/all, default "all")

### shopware-storefront-search
Search products with storefront context (resolved prices, visibility). Supports human-readable property filters.
- **Required**: `salesChannelId` (string)
- **Optional**: `criteria` (string JSON, default "{}"), `customerId` (string|null), `properties` (string JSON, e.g. '{"Color": "Red", "Size": "42"}'), `term` (string)

### shopware-state-machine-transition
Transition entity state machine. Always dryRun first.
- **Required**: `entityName` (string), `entityId` (string), `actionName` (string)
- **Optional**: `stateFieldName` (string, default "stateId"), `dryRun` (bool, default true)

### shopware-system-config-read
Read system configuration values.
- **Required**: `key` (string, domain prefix or full key)
- **Optional**: `salesChannelId` (string|null)

### shopware-system-config-write
Write system configuration. Always dryRun first.
- **Required**: `key` (string), `value` (string)
- **Optional**: `salesChannelId` (string|null), `dryRun` (bool, default true)

### shopware-media-upload
Upload a media file from a public URL. Optionally assign as product cover image.
- **Required**: `url` (string)
- **Optional**: `fileName` (string), `mediaFolderId` (string), `productId` (string)

### shopware-theme-config
Read or update theme configuration for a sales channel.
- **Required**: `salesChannelId` (string), `action` (string: "get" or "update")
- **Optional**: `config` (string JSON), `dryRun` (bool, default true)
```

## Keeping it up to date

The rule only covers tools that exist when you create it. If you add new tools (via plugins or apps), add their schemas to the rule manually. Tools not listed in the rule still work -- Cursor just falls back to reading the JSON descriptor first.
