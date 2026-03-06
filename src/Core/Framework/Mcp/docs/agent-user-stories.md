# Agent User Stories

This document defines user stories that describe what an AI agent should be able to accomplish via the Shopware MCP server. Each story is rated by current feasibility and references gaps that block or complicate it.

## Category 1: Admin Data Exploration

**Status: GOOD** -- covered by generic entity tools and outcome tools.

- **US-1**: "Show me the 10 most recent orders with their status and totals"
  - Tools: `shopware-order-summary` for individual orders, or `shopware-entity-search` with sort/limit for lists
  - Feasibility: Fully covered. For individual orders, `shopware-order-summary` returns all data in one call

- **US-2**: "What products are low on stock (below 5 units)?"
  - Tools: `shopware-entity-search` with range filter on `stock`
  - Feasibility: Works, same criteria complexity

- **US-3**: "Show me the schema of the customer entity"
  - Tools: `shopware-entity-schema`
  - Feasibility: Fully covered, single tool call

## Category 2: Admin Operations

**Status: PARTIAL** -- write tools exist but complex payloads have high hallucination risk.

- **US-4**: "Create a new product with price 29.99, tax rate 19%, and assign it to category 'Clothing'"
  - Tools: `shopware-product-create` with `name`, `productNumber`, `grossPrice`, `taxRate`, `categories`
  - Feasibility: Fully covered. Tax/currency/category resolution is automatic

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

**Status: GOOD** -- can discover events/actions and create single-action flows.

- **US-10**: "Set up an automation: when order status changes to 'shipped', send email to customer"
  - Tools: `shopware://business-events` resource + `shopware://flow-actions` resource for discovery, then `shopware-flow-create` to create the flow
  - Feasibility: Fully covered for single-action flows. Complex multi-action flows still require the admin UI

- **US-11**: "What automations are currently configured?"
  - Tools: `shopware-entity-search` on `flow` entity with `sequences` association
  - Feasibility: Works, but `flow_sequence` entity structure is complex to interpret

## Category 5: Storefront / Customer-Facing

**Status: GOOD** -- storefront search, cart management, checkout, and method listing all covered.

- **US-12**: "Help a customer find red shoes in size 42"
  - Tools: `shopware-storefront-search` with property group filters
  - Feasibility: Possible but requires complex criteria with property group associations

- **US-13**: "Create a cart, add 2x product SW-001, and start checkout"
  - Tools: `shopware-cart-manage` (create + add) then `shopware-cart-checkout` (dryRun + order)
  - Feasibility: Fully covered. Complete checkout flow in 4-5 tool calls

- **US-14**: "What payment/shipping methods are available in sales channel X?"
  - Tools: `shopware-checkout-methods` with type "all"
  - Feasibility: Fully covered. Returns IDs, names, and descriptions for all available methods

- **US-15**: "Track order status for customer email john@example.com"
  - Tools: `shopware-customer-lookup` with `email` -- returns profile with recent orders and statuses
  - Feasibility: Fully covered in a single call

## Category 6: Analytics / Reporting

**Status: PARTIAL** -- revenue reporting is covered, product-level analytics still requires manual criteria.

- **US-16**: "What was the revenue for last month?"
  - Tools: `shopware-revenue-report` with `from`, `to`, and optional `groupBy`
  - Feasibility: Fully covered. Returns total revenue, order count, average order value, and timeline

- **US-17**: "Which products are bestsellers this week?"
  - Tools: `shopware-entity-search` with aggregation on `order_line_item` grouped by product
  - Feasibility: Requires complex aggregation criteria (no dedicated tool yet)

## Postponed Improvements

No outstanding gaps. All identified user stories are fully or partially covered.
