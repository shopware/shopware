# MCP Tools

## Purpose
Each file in this directory is a single MCP tool -- an action that AI clients can invoke via the MCP protocol.

## Naming
- Tool names use `shopware-` prefix with kebab-case: `shopware-entity-search`, `shopware-entity-upsert`
- Class names use PascalCase suffix `Tool`: `EntitySearchTool`, `EntityUpsertTool`
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
- Entity tools returning DAL data should use the `McpEntityIncludes` trait and call `applyDefaultIncludes()` to keep responses compact (see below)

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

## Search vs. aggregate: why they are separate tools

`EntitySearchTool` and `EntityAggregateTool` look similar but serve different purposes and have different output sizes:

| | `shopware-entity-search` | `shopware-entity-aggregate` |
|---|---|---|
| Returns | Entity records | Aggregation results only |
| Entity rows | Up to `limit` (default 25) | Always 0 (`limit: 0` internally) |
| Aggregations in response | Never | Always |
| Response risk | Large on wide entities | Bounded — no row data |
| Typical use | "Show me the last 10 orders" | "How many opt-in newsletter recipients?" |

**Why not combine them?** The 100 KB response guard exists because MCP transports serialise the full response before the AI client receives it. Entity rows already fill most of this budget for typical searches. Bucket aggregations — like `terms` on product names or `date-histogram` by day — can produce hundreds or thousands of result entries on their own. Mixing both in one response means the tool would need to truncate either the records or the buckets to stay within the limit, which silently corrupts the result. Separate tools remove the ambiguity: `entity-search` is always bounded by `limit`, `entity-aggregate` is always bounded by the aggregation definitions.

**Rule:** Never add aggregation output to `EntitySearchTool`. If you need both records and metrics for the same entity, make two sequential tool calls.

## Smart default includes (McpEntityIncludes trait)

Entity tools (`EntitySearchTool`, `EntityReadTool`, `StorefrontSearchTool`) auto-apply `includes` when the caller hasn't specified them. This dramatically reduces response size by only serializing the fields AI clients actually need.

**How it works:**
1. The tool introspects the `EntityDefinition` to find all scalar fields (id, name, price, etc.)
2. Only associations explicitly requested in the criteria are included
3. Unrequested auto-loaded associations (thumbnails, extensions, translated duplicates) are stripped
4. The `translated` pseudo-field is always injected for entities with `TranslatedField` instances, ensuring inherited/resolved values (e.g. variant product names) are never lost
5. The caller can always override by passing their own `includes` in the criteria -- `translated` is still auto-injected

**Usage in tools:**
```php
use McpEntityIncludes;

// After building the criteria, before searching -- single call handles everything
$this->applyDefaultIncludes($definition, $criteriaObj);
```

`applyDefaultIncludes()` handles two cases:
- **No includes provided**: builds smart defaults from the definition (scalar fields + requested associations + `translated`)
- **User-provided includes**: injects `translated` into the includes for any entity with translated fields, recursing into loaded associations

All three entity read tools use `JsonEntityEncoder` for serialization (not the Store API serializer), so `includes`/`excludes` filtering works consistently regardless of whether the data comes from a regular or sales channel repository.

## Read tools
- `EntitySchemaTool` (`shopware-entity-schema`) -- entity field/association introspection
- `EntitySearchTool` (`shopware-entity-search`) -- criteria-based search; returns records only, **never aggregations** (see below)
- `EntityAggregateTool` (`shopware-entity-aggregate`) -- aggregation-only queries (`limit: 0` internally, no entity rows in response)
- `EntityReadTool` (`shopware-entity-read`) -- single entity read by ID
- `SystemConfigReadTool` (`shopware-system-config-read`) -- read shop configuration
- `StorefrontSearchTool` (`shopware-storefront-search`) -- search products with sales channel context

## Write tools
- `EntityUpsertTool` (`shopware-entity-upsert`) -- create/update entities (dryRun wraps in transaction + rollback)
- `EntityDeleteTool` (`shopware-entity-delete`) -- delete entities (dryRun shows cascade impact)
- `SystemConfigWriteTool` (`shopware-system-config-write`) -- update configuration values
- `StateMachineTransitionTool` (`shopware-state-machine-transition`) -- transition entity states
- `MediaUploadTool` (`shopware-media-upload`) -- upload media from URL, optionally assign to product as cover image
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
4. Register in `src/Core/Framework/DependencyInjection/mcp.php` with `mcp.tool` and `shopware.feature` (flag: `MCP_SERVER`) tags
5. Add unit test in `tests/unit/Core/Framework/Mcp/Tool/`
6. Add the tool name to `expectedTools()` in `McpCapabilityDiscoveryTest` (see below)

## Validating that a tool is actually reachable

There are two separate layers between writing a tool class and it appearing in a client like Cursor:

| Layer | What can go wrong | Caught by |
|---|---|---|
| DI registration | Missing tag, wrong tag name | `McpServiceConfigTest`, `McpFeatureFlagTest` |
| SDK attribute scanner | `mcp.yaml` `scan_dirs` missing the bundle | `McpCapabilityDiscoveryTest` |

**The unit tests only check the DI layer.** If a tool is correctly tagged but its directory is not in `mcp.yaml`'s `scan_dirs`, the MCP SDK never finds the `#[McpTool]` attribute and the tool is silently absent from `tools/list`. This is what happened with `shopware-theme-config`.

`McpCapabilityDiscoveryTest` (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) covers both layers:
- It boots the full Symfony kernel
- Calls the live `/api/_mcp` endpoint using JSON-RPC (`initialize` → `tools/list` / `prompts/list` / `resources/list`)
- Asserts every expected capability name is present

This is the same thing the MCP Inspector does interactively. Add the tool name to `expectedTools()` in that test when adding a new tool.

Quick manual check: `bin/console debug:mcp` lists all currently registered capabilities.
