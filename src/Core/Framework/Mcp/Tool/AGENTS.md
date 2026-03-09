# MCP Tools

## Purpose
Each file in this directory is a single MCP tool -- an action that AI clients can invoke via the MCP protocol.

## Naming
- Tool names use `shopware-` prefix with kebab-case: `shopware-entity-search`, `shopware-console-command`
- Class names use PascalCase suffix `Tool`: `EntitySearchTool`, `ConsoleCommandTool`
- Plugin tools: `{plugin-name}-{tool-name}`
- App tools: `{app-name}-{tool-name}`
- Names must only contain `a-zA-Z0-9_-` (no dots) for consistency across all MCP capability types

## Patterns
- Use constructor injection for dependencies
- Place the `#[McpTool]` attribute on the class with `name` and `description`
- Use the `McpToolResponse` trait and return via `$this->success()` or `$this->error()` from `__invoke`
- Use `McpContextProvider` to get the authenticated `Context`
- Write operations must accept a `bool $dryRun = true` parameter
- Entity tools that return DAL data must inject `JsonEntityEncoder` and use it instead of `jsonSerialize()` to respect `includes`/`excludes`

## Response format convention
All tools must use the `McpToolResponse` trait. It provides two helpers:

**Success**: `$this->success(array $data, array $meta = [])`
```json
{"success": true, "data": [...], "_meta": {"total": 42, "page": 1}}
```

**Error**: `$this->error(string $message)`
```json
{"success": false, "error": "Human-readable message"}
```

Rules:
- `success` (bool) is always present at root
- `data` holds the actual result (structure is tool-specific)
- `_meta` is optional, used for pagination (`total`, `page`, `limit`), context (`salesChannelId`), and write metadata (`dryRun`)
- `error` (string) only appears when `success` is false
- The trait includes a response size guard (100 KB) that truncates oversized responses
- A `McpToolResponseConventionTest` enforces that all `#[McpTool]` classes use the trait

## Read tools
- `EntitySchemaTool` (`shopware-entity-schema`) -- entity field/association introspection
- `EntitySearchTool` (`shopware-entity-search`) -- criteria-based search with flattened term/limit/page params
- `EntityReadTool` (`shopware-entity-read`) -- single entity read by ID
- `SystemConfigReadTool` (`shopware-system-config-read`) -- read shop configuration
- `StorefrontSearchTool` (`shopware-storefront-search`) -- search products with sales channel context
- `ConsoleCommandTool` (`shopware-console-command`) -- execute allowlisted console commands

## Write tools
- `EntityUpsertTool` (`shopware-entity-upsert`) -- create/update entities (dryRun wraps in transaction + rollback)
- `EntityDeleteTool` (`shopware-entity-delete`) -- delete entities (dryRun shows cascade impact)
- `SystemConfigWriteTool` (`shopware-system-config-write`) -- update configuration values
- `StateMachineTransitionTool` (`shopware-state-machine-transition`) -- transition entity states
## Outcome tools
Outcome tools encapsulate common multi-step workflows into a single call with human-readable parameters:
- `OrderSummaryTool` (`shopware-order-summary`) -- look up an order by number or UUID, returns customer info, line items, payment/delivery status
- `CustomerLookupTool` (`shopware-customer-lookup`) -- look up a customer by email, customer number, or UUID with order history
- `ProductCreateTool` (`shopware-product-create`) -- create a product with auto-resolution of tax, currency, and categories
- `RevenueReportTool` (`shopware-revenue-report`) -- generate revenue reports with date range, aggregations, and timeline
- `OrderCancelTool` (`shopware-order-cancel`) -- cancel an order with all transactions and deliveries in one call, with optional refund
- `BestsellerReportTool` (`shopware-bestseller-report`) -- top-selling products by quantity in a date range with revenue data

## Storefront tools
Storefront tools use the Store API / SalesChannelContext layer for customer-facing operations:
- `CartManageTool` (`shopware-cart-manage`) -- create, add to, remove from, update, and view shopping carts
- `CartCheckoutTool` (`shopware-cart-checkout`) -- place an order from an existing cart (dryRun for preview)
- `CheckoutMethodsTool` (`shopware-checkout-methods`) -- list available payment and shipping methods for a sales channel

## Adding a new tool
1. Create a class in this directory
2. Add `#[McpTool(name: 'shopware-{tool-name}', description: '...')]` on the class
3. Add `use McpToolResponse;` and return via `$this->success()` / `$this->error()`
4. Register in `src/Core/Framework/DependencyInjection/mcp.php` with `mcp.tool` and `shopware.feature` tags
5. Add unit test in `tests/unit/Core/Framework/Mcp/Tool/`
