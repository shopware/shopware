# Agent User Stories

This document defines user stories that describe what an AI agent should be able to accomplish via the Shopware MCP server. Each story is rated by current feasibility and references gaps that block or complicate it.

## Category 1: Admin Data Exploration

**Status: GOOD** -- covered by generic entity tools, but requires the AI to construct criteria JSON.

- **US-1**: "Show me the 10 most recent orders with their status and totals"
  - Tools: `shopware-entity-search` with sort, limit, and associations for `stateMachineState`
  - Feasibility: Works, but the AI must know the order schema and build nested criteria JSON

- **US-2**: "What products are low on stock (below 5 units)?"
  - Tools: `shopware-entity-search` with range filter on `stock`
  - Feasibility: Works, same criteria complexity

- **US-3**: "Show me the schema of the customer entity"
  - Tools: `shopware-entity-schema`
  - Feasibility: Fully covered, single tool call

## Category 2: Admin Operations

**Status: PARTIAL** -- write tools exist but complex payloads have high hallucination risk.

- **US-4**: "Create a new product with price 29.99, tax rate 19%, and assign it to category 'Clothing'"
  - Tools: `shopware-entity-search` (find tax, currency, category IDs) + `shopware-entity-upsert`
  - Feasibility: Multiple steps, nested price structure `[{currencyId, gross, net, linked}]` is error-prone
  - Gap: No outcome-oriented `shopware-product-create` tool (postponed)

- **US-5**: "Ship order #12345 and send the customer a notification"
  - Tools: `shopware-state-machine-transition`
  - Feasibility: State transition works. Email sending depends on flow configuration -- no tool to verify flows exist for this event

- **US-6**: "Cancel all line items from order X and process the refund"
  - Tools: Multiple `shopware-entity-upsert` + `shopware-state-machine-transition` calls
  - Feasibility: High failure risk, requires coordinated multi-entity operations
  - Gap: No orchestration tool (postponed)

## Category 3: Configuration and Troubleshooting

**Status: GOOD** -- well covered by existing tools.

- **US-7**: "What are the current listing settings and how do I change the default sorting?"
  - Tools: `shopware-system-config-read` + `shopware-system-config-write` with dryRun
  - Feasibility: Fully covered

- **US-8**: "Which plugins are installed and what version are they?"
  - Tools: `shopware-console-command` with `plugin:list --format=json`
  - Feasibility: Fully covered

- **US-9**: "Clear the cache and recompile the theme"
  - Tools: `shopware-console-command` with `cache:clear` and `theme:compile`
  - Feasibility: Fully covered

## Category 4: Flow/Automation Discovery

**Status: PARTIAL** -- can discover events and actions but cannot create flows.

- **US-10**: "Set up an automation: when order status changes to 'shipped', send email to customer"
  - Tools: `shopware://business-events` resource + `shopware://flow-actions` resource for discovery
  - Feasibility: Can advise which event/action to use, but cannot create the flow
  - Gap: No `shopware-flow-create` tool (postponed)

- **US-11**: "What automations are currently configured?"
  - Tools: `shopware-entity-search` on `flow` entity with `sequences` association
  - Feasibility: Works, but `flow_sequence` entity structure is complex to interpret

## Category 5: Storefront / Customer-Facing

**Status: WEAK** -- only `shopware-storefront-search` exists.

- **US-12**: "Help a customer find red shoes in size 42"
  - Tools: `shopware-storefront-search` with property group filters
  - Feasibility: Possible but requires complex criteria with property group associations

- **US-13**: "Create a cart, add 2x product SW-001, and start checkout"
  - Feasibility: **Not possible.** No cart/checkout tools exist
  - Gap: Storefront checkout flow (postponed)

- **US-14**: "What payment/shipping methods are available in sales channel X?"
  - Tools: `shopware-entity-search` on `payment_method` / `shipping_method` with sales channel associations
  - Feasibility: Possible but indirect, no dedicated listing tool
  - Gap: Storefront tools (postponed)

- **US-15**: "Track order status for customer email john@example.com"
  - Tools: `shopware-entity-search` on `customer` by email, then `shopware-entity-search` on `order` by customerId
  - Feasibility: Requires two coordinated searches
  - Gap: No `shopware-customer-lookup` outcome tool (postponed)

## Category 6: Analytics / Reporting

**Status: WEAK** -- technically possible via aggregations but very complex criteria.

- **US-16**: "What was the revenue for last month?"
  - Tools: `shopware-entity-search` with aggregations on `order`
  - Feasibility: Requires complex aggregation criteria with date range filters
  - Gap: No dedicated reporting tool (postponed)

- **US-17**: "Which products are bestsellers this week?"
  - Tools: `shopware-entity-search` with aggregation on `order_line_item` grouped by product
  - Feasibility: Very complex aggregation criteria
  - Gap: No dedicated reporting tool (postponed)

## Postponed Improvements

The following gaps have been identified but deferred to separate tasks:

- **Outcome tools**: `shopware-order-summary`, `shopware-customer-lookup`, `shopware-product-create`, `shopware-revenue-report`
- **Storefront checkout flow**: `cart-create`, `cart-add-item`, `cart-checkout`, `payment-methods-list`, `shipping-methods-list`
- **Flow creation**: `shopware-flow-create` for simple single-action flow automation
