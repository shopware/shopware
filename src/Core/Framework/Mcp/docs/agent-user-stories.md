# Agent User Stories

This document defines user stories that describe what an AI agent should be able to accomplish via the Shopware MCP server. Each story has a status and references the tools that cover it.

## Scope

**In scope**: Merchant and operator tasks -- managing products, orders, customers, carts, reports, configuration, and shop appearance via AI assistants (Claude Desktop, Cursor, etc.).

**Out of scope**: Developer tasks such as code generation, testing, linting, cache clearing, and deployment. For developer-facing MCP tools, see [shopwareLabs/ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools).

**Relation to shopware-admin-mcp**: This built-in MCP server supersedes the external [shopware/shopware-admin-mcp](https://github.com/shopware/shopware-admin-mcp) Node.js server by providing the same capabilities natively with proper ACL, dryRun safety, and extensibility.

**Status legend:** COVERED = fully working, PARTIAL = possible but limited, GAP = not yet possible

## Category 1: Admin Data Exploration

- **US-1** [COVERED]: "Show me the 10 most recent orders with their status and totals"
  - Tools: `shopware-order-summary` for individual orders, or `shopware-entity-search` with sort/limit for lists
- **US-2** [COVERED]: "What products are low on stock (below 5 units)?"
  - Tools: `shopware-entity-search` with range filter on `stock`
- **US-3** [COVERED]: "Show me the schema of the customer entity"
  - Tools: `shopware-entity-schema`

## Category 2: Admin Operations

- **US-4** [COVERED]: "Create a new product with price 29.99, tax rate 19%, and assign it to category 'Clothing'"
  - Tools: `shopware-product-create` with `name`, `productNumber`, `grossPrice`, `taxRate`, `categories`
- **US-5** [COVERED]: "Ship order #12345 and send the customer a notification"
  - Tools: `shopware-state-machine-transition` for the state change, `shopware-entity-search` on `flow` to verify notification flows exist
- **US-6** [COVERED]: "Cancel all line items from order X and process the refund"
  - Tools: `shopware-order-cancel` with `orderNumber` or `orderId`, optional `refundTransactions=true` for refunding paid transactions

## Category 3: Configuration

- **US-7** [COVERED]: "What are the current listing settings and how do I change the default sorting?"
  - Tools: `shopware-system-config-read` + `shopware-system-config-write` with dryRun

## Category 4: Flow/Automation Discovery

- **US-8** [GAP]: "Set up an automation: when order status changes to 'shipped', send email to customer"
  - Tools: `shopware://business-events` resource + `shopware://flow-actions` resource for discovery
  - Gap: Can advise which event/action to use, but cannot create flows programmatically. Postponed until event/action validation and multi-action support are ready
- **US-9** [PARTIAL]: "What automations are currently configured?"
  - Tools: `shopware-entity-search` on `flow` entity with `sequences` association
  - Note: Works, but `flow_sequence` entity structure is complex to interpret

## Category 5: Storefront / Customer-Facing

- **US-10** [COVERED]: "Help a customer find red shoes in size 42"
  - Tools: `shopware-storefront-search` with `properties: '{"Color": "Red", "Size": "42"}'` and `term: "shoes"` -- the tool resolves human-readable names to UUIDs automatically
- **US-11** [COVERED]: "Create a cart, add 2x product SW-001, and start checkout"
  - Tools: `shopware-cart-manage` (create + add) then `shopware-cart-checkout` (dryRun + order)
- **US-12** [COVERED]: "What payment/shipping methods are available in sales channel X?"
  - Tools: `shopware-checkout-methods` with type "all"
- **US-13** [COVERED]: "Track order status for customer email john@example.com"
  - Tools: `shopware-customer-lookup` with `email`

## Category 6: Analytics / Reporting

- **US-14** [COVERED]: "What was the revenue for last month?"
  - Tools: `shopware-revenue-report` with `from`, `to`, and optional `groupBy`
- **US-15** [COVERED]: "Which products are bestsellers this week?"
  - Tools: `shopware-bestseller-report` with `from`, `to`, and optional `limit`

## Category 7: Media Management

- **US-16** [COVERED]: "Upload a product image from a URL and assign it to product X"
  - Tools: `shopware-media-upload` with `url` and `productId`
- **US-17** [COVERED]: "Upload a new shop logo from a URL"
  - Tools: `shopware-media-upload` with `url`

## Category 8: Theme / Appearance

- **US-18** [COVERED]: "Change the primary brand color of my shop to blue"
  - Tools: `shopware-theme-config` with `action: "update"` and config `{"sw-color-brand-primary": {"value": "#0000ff"}}`
- **US-19** [COVERED]: "Update the shop logo in the theme"
  - Tools: `shopware-media-upload` to upload the logo, then `shopware-theme-config` to set the media ID in `sw-logo-desktop`

## Category 9: Promotions & Marketing

- **US-20** [COVERED]: "What promotions are active right now?"
  - Tools: `shopware-entity-search` on `promotion` with `active: true` and date range filters on `validFrom`/`validUntil`
- **US-21** [COVERED]: "How many newsletter subscribers do we have?"
  - Tools: `shopware-entity-search` on `newsletter_recipient` with `count` aggregation

## Category 10: Product Quality & Content

- **US-22** [COVERED]: "Which products have no images?"
  - Tools: `shopware-entity-search` on `product` with `media` association count filter
- **US-23** [COVERED]: "Change the price of product SW-001 to 39.99"
  - Tools: `shopware-entity-upsert` on `product` with nested price array
- **US-24** [COVERED]: "Assign product X to category Y"
  - Tools: `shopware-entity-upsert` on `product` with `categories` association

## Category 11: Customer Insights

- **US-25** [COVERED]: "Show me all 1-star reviews from last month"
  - Tools: `shopware-entity-search` on `product_review` with `points` and `createdAt` filters
- **US-26** [COVERED]: "How many customers haven't ordered in 6 months?"
  - Tools: `shopware-entity-search` on `customer` with `lastOrderDate` range filter
- **US-27** [COVERED]: "What's the average order value this month?"
  - Tools: `shopware-entity-search` on `order` with `avg` aggregation on `amountTotal`

## Category 12: Sales Channel Operations

- **US-28** [COVERED]: "Put my shop in maintenance mode"
  - Tools: `shopware-entity-upsert` on `sales_channel` with `maintenance: true`

## Postponed Improvements

- **US-8** (flow creation): Postponed until event/action validation and multi-action flow support are implemented together
