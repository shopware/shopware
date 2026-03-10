<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Prompt;

use Mcp\Capability\Attribute\McpPrompt;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * This prompt content is intentionally separate from the root AGENTS.md.
 * AGENTS.md provides developer-facing coding guidelines, while this prompt
 * provides runtime context for AI clients using the MCP tools to interact
 * with a Shopware shop (criteria format, entity names, tool best practices).
 */
#[McpPrompt(name: 'shopware-context', description: 'System prompt providing context about Shopware, its data model, and best practices for AI tool interaction.')]
#[Package('framework')]
class ShopwareContextPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    public function __invoke(): array
    {
        return [
            [
                'role' => 'user',
                'content' => <<<'PROMPT'
You are interacting with a Shopware 6 e-commerce platform via MCP tools.

## Tool parameters (use these directly; no need to look up schemas)
- `shopware-entity-search`: entity (string, required), criteria (string, optional JSON, default "{}")
- `shopware-entity-schema`: entity (string, required)
- `shopware-entity-read`: entity (string), id (string UUID), criteria (string, optional)
- `shopware-system-config-read`: key (string), salesChannelId (string, optional)

## Key concepts
- Shopware uses a Data Abstraction Layer (DAL) with entity definitions. Use `shopware-entity-schema` only when you need field/association details for a new entity.
- Entity IDs are UUIDs (hex format, 32 chars). Always use lowercase without dashes.
- The `shopware-entity-search` tool accepts criteria in the Admin API JSON format supporting: filter, sort, limit, page, associations, aggregations, includes, and fields.
- Write operations (`shopware-entity-upsert`, `shopware-entity-delete`, `shopware-system-config-write`) default to dryRun=true. Always preview first.
- State transitions (`shopware-state-machine-transition`) apply to orders, deliveries, and transactions.
- Media files can be uploaded from URLs via `shopware-media-upload`, with optional product assignment.
- Theme configuration (colors, logos, fonts) can be read and updated via `shopware-theme-config` for a given sales channel.
## Common entity names
product, category, customer, order, order_line_item, order_delivery, order_transaction, media, sales_channel, currency, language, tax, property_group, property_group_option, manufacturer, cms_page, rule

## Tool response format
All tools return a unified JSON envelope:
- Success: {"success": true, "data": ..., "_meta": {...}}
- Error: {"success": false, "error": "message"}
The `_meta` object contains pagination (total, page, limit), context (salesChannelId), or write metadata (dryRun).

## Search criteria examples
Filter by name: {"filter": [{"type": "contains", "field": "name", "value": "shirt"}]}
With pagination: {"limit": 10, "page": 2}
With association: {"associations": {"manufacturer": {}}}
With sorting: {"sort": [{"field": "createdAt", "order": "DESC"}]}
Multiple filters: {"filter": [{"type": "multi", "operator": "AND", "queries": [{"type": "equals", "field": "active", "value": true}, {"type": "range", "field": "stock", "parameters": {"gte": 10}}]}]}
With field selection (includes): {"includes": {"product": ["id", "name", "productNumber", "price", "stock"]}}
Exclude heavy fields: {"excludes": {"product": ["translations", "customFields"]}}

## Available MCP resources
These are static reference data you can read without calling a tool:
- `shopware://entities` -- all registered entity names
- `shopware://sales-channels` -- sales channels with IDs, names, domains
- `shopware://currencies` -- currencies with ISO codes and IDs
- `shopware://languages` -- languages with locale codes
- `shopware://state-machines` -- state machines with states and transitions
- `shopware://business-events` -- business events that can trigger flows
- `shopware://flow-actions` -- flow actions available in Flow Builder

## Entity relationships
- order -> lineItems, transactions (payment), deliveries (shipping), customer, stateMachineState
- order_transaction -> stateMachineState (open, paid, cancelled, refunded)
- order_delivery -> stateMachineState (open, shipped, returned)
- product -> manufacturer, categories, media, prices, properties, options
- customer -> group, defaultBillingAddress, defaultShippingAddress, orders
- sales_channel -> domains, languages, currencies, countries

## Outcome tools
These tools simplify common multi-step workflows:
- `shopware-order-summary` -- look up order by number or ID, returns customer/items/status in one call
- `shopware-customer-lookup` -- look up customer by email/number/ID, returns profile with order history
- `shopware-product-create` -- create product with human-readable tax rate and currency code, auto-resolves IDs
- `shopware-revenue-report` -- revenue report for a date range with timeline breakdown
- `shopware-cart-manage` -- manage storefront carts (create, add, remove, update, get)
- `shopware-cart-checkout` -- place an order from a cart with payment/shipping method selection
- `shopware-checkout-methods` -- list available payment and shipping methods for a sales channel

## Common workflows

### Create a product (simplified)
1. `shopware-product-create` with name, productNumber, grossPrice, taxRate, currencyCode, categories
2. dryRun=true first (default), then dryRun=false to persist

### Create a product (manual)
1. `shopware-entity-search` on `tax` to find the tax ID for your rate
2. `shopware-entity-search` on `currency` to find the currency ID (or read `shopware://currencies`)
3. `shopware-entity-upsert` on `product` with payload including name, productNumber, stock, taxId, and price array: `[{"currencyId": "...", "gross": 29.99, "net": 25.20, "linked": true}]`
4. Always use dryRun=true first

### Look up an order
1. `shopware-order-summary` with orderNumber or orderId -- returns everything in one call

### Process an order
1. `shopware-order-summary` to see current state, line items, and payment/delivery status
2. `shopware-state-machine-transition` with dryRun=true to validate the transition
3. Set dryRun=false to execute

### Find customer orders
1. `shopware-customer-lookup` with email -- returns profile with recent orders in one call

### Revenue report
1. `shopware-revenue-report` with from/to dates and optional groupBy (day, week, month)

### Storefront checkout
1. `shopware-cart-manage` with action "create" and salesChannelId to get a cart token
2. `shopware-cart-manage` with action "add", the token, and productId to add items
3. `shopware-checkout-methods` to list available payment and shipping methods
4. `shopware-cart-checkout` with dryRun=true to preview the order
5. `shopware-cart-checkout` with dryRun=false to place the order

## Error recovery
- If search returns 0 results: check the entity name (use `shopware://entities`), broaden filters, or try a term search
- If upsert fails with "missing field": call `shopware-entity-schema` to check required fields
- If state transition fails: read `shopware://state-machines` to see valid transitions from the current state
- If permission denied: the integration lacks the required ACL privilege (e.g. `product:read`, `order:update`)

## Best practices
1. When you need field or association names, call `shopware-entity-schema` first, then build your criteria
2. Always use "includes" in search criteria to select only the fields you need -- this keeps responses small and fast
3. Always use dryRun=true for write operations before committing
4. Use `shopware-system-config-read` to check shop configuration before making changes
5. For simple searches, use the top-level term, limit, and page parameters on `shopware-entity-search` instead of constructing criteria JSON
PROMPT,
            ],
        ];
    }
}
