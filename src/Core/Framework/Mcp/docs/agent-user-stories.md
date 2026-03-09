# Agent User Stories

This document defines user stories that describe what an AI agent should be able to accomplish via the Shopware MCP server. Each story has a status and references the tools that cover it.

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

## Category 3: Configuration and Troubleshooting

- **US-7** [COVERED]: "What are the current listing settings and how do I change the default sorting?"
  - Tools: `shopware-system-config-read` + `shopware-system-config-write` with dryRun

- **US-8** [COVERED]: "Which plugins are installed and what version are they?"
  - Tools: `shopware-console-command` with `plugin:list --format=json`

- **US-9** [COVERED]: "Clear the cache and recompile the theme"
  - Tools: `shopware-console-command` with `cache:clear` and `theme:compile`

## Category 4: Flow/Automation Discovery

- **US-10** [GAP]: "Set up an automation: when order status changes to 'shipped', send email to customer"
  - Tools: `shopware://business-events` resource + `shopware://flow-actions` resource for discovery
  - Gap: Can advise which event/action to use, but cannot create flows programmatically. Postponed until event/action validation and multi-action support are ready

- **US-11** [PARTIAL]: "What automations are currently configured?"
  - Tools: `shopware-entity-search` on `flow` entity with `sequences` association
  - Note: Works, but `flow_sequence` entity structure is complex to interpret

## Category 5: Storefront / Customer-Facing

- **US-12** [PARTIAL]: "Help a customer find red shoes in size 42"
  - Tools: `shopware-storefront-search` with property group filters
  - Note: Requires complex criteria with property group associations

- **US-13** [COVERED]: "Create a cart, add 2x product SW-001, and start checkout"
  - Tools: `shopware-cart-manage` (create + add) then `shopware-cart-checkout` (dryRun + order)

- **US-14** [COVERED]: "What payment/shipping methods are available in sales channel X?"
  - Tools: `shopware-checkout-methods` with type "all"

- **US-15** [COVERED]: "Track order status for customer email john@example.com"
  - Tools: `shopware-customer-lookup` with `email`

## Category 6: Analytics / Reporting

- **US-16** [COVERED]: "What was the revenue for last month?"
  - Tools: `shopware-revenue-report` with `from`, `to`, and optional `groupBy`

- **US-17** [COVERED]: "Which products are bestsellers this week?"
  - Tools: `shopware-bestseller-report` with `from`, `to`, and optional `limit`

## Postponed Improvements

- **US-10** (flow creation): Postponed until event/action validation and multi-action flow support are implemented together
