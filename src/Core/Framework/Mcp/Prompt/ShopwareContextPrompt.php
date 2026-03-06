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
- Console commands can be executed via `shopware-console-command` with a safe allowlist of commands.

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

## Best practices
1. When you need field or association names to build criteria (e.g. which field links sales channel to categories), call `shopware-entity-schema` for the relevant entity first, then build your `shopware-entity-search` criteria from the returned fields. No predefined task list needed.
2. Always use "includes" in search criteria to select only the fields you need -- this keeps responses small and fast
3. Always use dryRun=true for write operations before committing
4. Check `shopware-business-events` to understand available flow triggers
5. Use `shopware-system-config-read` to check shop configuration before making changes
6. Use `shopware-flow-actions` to discover available flow actions for automation
7. Use `shopware-console-command` to run safe administrative commands
PROMPT,
            ],
        ];
    }
}
