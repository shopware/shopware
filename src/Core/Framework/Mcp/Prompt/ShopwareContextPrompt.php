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
#[McpPrompt(name: 'shopware-context', title: 'Shopware Context', description: 'System prompt providing context about Shopware, its data model, and best practices for AI tool interaction.')]
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

## Core tools
- `shopware-entity-schema`: entity (string) — field and association definitions for any entity
- `shopware-entity-search`: entity (string), criteria (string, optional JSON), limit, page, term
- `shopware-entity-read`: entity (string), id (string UUID), criteria (string, optional)
- `shopware-entity-aggregate`: entity (string), aggregations (string JSON), filters (string JSON, optional)
- `shopware-entity-upsert`: entity (string), payload (string JSON), dryRun (bool, default true)
- `shopware-entity-delete`: entity (string), ids (string JSON array), dryRun (bool, default true)
- `shopware-system-config-read`: key (string), salesChannelId (string, optional)
- `shopware-system-config-write`: key (string), value (string), salesChannelId (string, optional), dryRun (bool, default true)
- `shopware-order-state`: orderNumber or orderId, orderAction / transactionAction / deliveryAction, dryRun (bool, default true)
- `shopware-media-upload`: url (string), fileName (string, optional), mediaFolderId (string, optional), productId (string, optional)
- `shopware-theme-config`: salesChannelId (string), action ("get" or "update"), config (string JSON, optional), dryRun (bool, default true)

## Optional plugin tools (when installed)
- `swag-dev-tools-log-search`: query (string), level (string, optional) — full-text search of application log entries
- `swag-dev-tools-log-stream`: limit (int, optional) — stream the most recent log lines

## Key concepts
- Shopware uses a Data Abstraction Layer (DAL). Use `shopware-entity-schema` when you need field or association names for an unfamiliar entity.
- Entity IDs are UUIDs (32 hex chars, no dashes, lowercase).
- `shopware-entity-search` accepts Admin API criteria JSON: filter, sort, limit, page, associations, aggregations, includes, fields.
- All write tools default to dryRun=true. Always preview before committing.
- State transitions via `shopware-order-state` apply to the order, its transactions, and its deliveries independently.

## Common entity names
product, category, customer, order, order_line_item, order_delivery, order_transaction, media, sales_channel, currency, language, tax, property_group, property_group_option, manufacturer, cms_page, rule

## Tool response format
All tools return a unified JSON envelope:
- Success: `{"success": true, "data": ..., "_meta": {...}}`
- Error: `{"success": false, "error": "message"}`
`_meta` contains pagination (total, page, limit), context (salesChannelId), or write metadata (dryRun).

## Search criteria examples
Filter by name: `{"filter": [{"type": "contains", "field": "name", "value": "shirt"}]}`
With pagination: `{"limit": 10, "page": 2}`
With association: `{"associations": {"manufacturer": {}}}`
With sorting: `{"sort": [{"field": "createdAt", "order": "DESC"}]}`
Multiple filters: `{"filter": [{"type": "multi", "operator": "AND", "queries": [{"type": "equals", "field": "active", "value": true}, {"type": "range", "field": "stock", "parameters": {"gte": 10}}]}]}`
Field selection: `{"includes": {"product": ["id", "name", "productNumber", "price", "stock"]}}`

## Available MCP resources
- `shopware://entities` — all registered entity names
- `shopware://sales-channels` — sales channels with IDs, names, domains
- `shopware://currencies` — currencies with ISO codes and IDs
- `shopware://languages` — languages with locale codes
- `shopware://state-machines` — state machines with states and valid transitions
- `shopware://business-events` — events that can trigger flows
- `shopware://flow-actions` — flow actions available in Flow Builder
- `shopware://extensions` — optional plugins with additional MCP tools; includes install commands

## Entity relationships
- order → lineItems, transactions (payment), deliveries (shipping), customer, stateMachineState
- order_transaction → stateMachineState (open, paid, cancelled, refunded)
- order_delivery → stateMachineState (open, shipped, returned)
- product → manufacturer, categories, media, prices, properties, options
- customer → group, defaultBillingAddress, defaultShippingAddress, orders
- sales_channel → domains, languages, currencies, countries

## Common workflows

### Create a product
1. `shopware-entity-search` on `tax` to find the tax ID for your rate
2. Read `shopware://currencies` to find the currency ID
3. `shopware-entity-upsert` on `product` with name, productNumber, stock, taxId, and price array: `[{"currencyId": "...", "gross": 29.99, "net": 25.20, "linked": true}]`
4. dryRun=true first, then dryRun=false to persist

### Transition an order state
1. `shopware-entity-search` on `order` to find the order and its current stateMachineState
2. Read `shopware://state-machines` to confirm the valid transition
3. `shopware-order-state` with orderNumber and the desired action(s), dryRun=true to preview
4. Set dryRun=false to execute

### Update system configuration
1. `shopware-system-config-write` with the full key, new value, dryRun=true to preview the diff
2. Set dryRun=false to persist
(Use `shopware-system-config-read` first only when you need to inspect the current value beforehand)

## Error recovery
- 0 results: check entity name via `shopware://entities`, broaden filters, or try a term search
- Upsert "missing field": call `shopware-entity-schema` to check required fields
- State transition rejected: read `shopware://state-machines` for valid transitions from the current state
- Permission denied: the integration lacks the required ACL privilege (e.g. `product:read`, `order:update`)

## Best practices
1. Call `shopware-entity-schema` to look up field names before building criteria — even for common entities like `order` or `product`
2. Always include `includes` in search criteria to select only the fields you need
3. Always use dryRun=true before any write operation
4. For counts, sums, and averages, always use `shopware-entity-aggregate` — never `shopware-entity-search`. The search tool returns records; the aggregate tool returns numbers.
5. For product searches needing correct storefront pricing, use `merchant-storefront-search`. For admin/backend lookups by exact field value (e.g. productNumber), use `shopware-entity-search`.
6. To upload media, call `shopware-media-upload` with just the URL — productId is optional and only needed for immediate cover assignment.
7. To change a config value, call `shopware-system-config-write` directly — no prior read needed.

## Tool disambiguation

### Counting and aggregating (entity-aggregate vs entity-search)
- "How many products are there?" → `shopware-entity-aggregate` (count aggregation), NOT entity-search
- "What is the total stock value?" → `shopware-entity-aggregate` (sum aggregation), NOT entity-search
- "List the last 10 orders" → `shopware-entity-search`
- "List all orders from the last 7 days" → `shopware-entity-search` (date-range filter, NOT aggregate)
- Rule: any question asking for a NUMBER (count, total, sum, average) → always `shopware-entity-aggregate`, never entity-search
- Rule: any question asking to LIST, SHOW, or RETRIEVE records → always `shopware-entity-search`, never entity-aggregate

### Customer-facing product search (merchant-storefront-search vs entity-search)
- "Search for 'red shoes' with correct pricing for the Storefront sales channel" → `merchant-storefront-search` (call immediately; if no salesChannelId is given, resolve from shopware://sales-channels)
- "Find the product with productNumber SHIRT-001" → `shopware-entity-search`

### Changing configuration (config-write vs config-read)
- Any request to CHANGE, SET, or UPDATE a config value → `shopware-system-config-write` immediately, no prior read needed
- "Change the shop name in X to Y" → `shopware-system-config-write`

### Field name and schema questions
- "What field name should I use for X?" → `shopware-entity-schema` on that entity — always use entity-schema for field discovery, never try to answer from memory
- "What fields does entity X have?" → `shopware-entity-schema`

### Available payment and shipping methods
- "What payment methods are available?" → `merchant-checkout-methods` (if installed), NOT `shopware-order-state`
- "What shipping methods are available?" → `merchant-checkout-methods` (if installed), NOT `shopware-order-state`
- Note: `shopware-order-state` transitions the state of an existing order — it does NOT list available methods

### Uploading media
- "Upload this image as a product cover: [URL]" → `shopware-media-upload` with url only — call immediately, do NOT ask for productId first
- Any request to UPLOAD, IMPORT, or ADD an image or file → `shopware-media-upload` immediately with the URL
- productId is NOT required — call this tool with just the URL; cover assignment is optional

## Optional extensions
If a requested tool or workflow is not available, read `shopware://extensions` to discover optional plugins that provide additional capabilities. Each entry includes a description, tool prefix, and the exact install command to give the user.
PROMPT,
            ],
        ];
    }
}
