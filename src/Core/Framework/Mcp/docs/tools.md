# MCP Tool Reference

All core tools are namespaced with `shopware-` prefix. Plugin tools must use their own namespace (e.g., `my-plugin-tool-name`).

## Read Tools

### shopware-entity-schema
Get the field and association schema of any Shopware entity. Use this first to discover field names before building search criteria.

**Parameters:**
- `entity` (string, required) -- Entity name (e.g., `product`, `order`)

**Example:**
```json
{"entity": "product"}
```

### shopware-entity-search
Primary data retrieval tool. Search entities using the Admin API criteria format. Supports top-level convenience parameters for simple queries, or full criteria JSON for advanced use.

**Parameters:**
- `entity` (string, required) -- Entity name
- `criteria` (string, optional) -- JSON criteria object for advanced queries
- `limit` (int, default: 25) -- Number of results to return
- `page` (int, default: 1) -- Page number for pagination
- `term` (string, optional) -- Full-text search term

**Criteria format:** Supports `filter`, `sort`, `limit`, `page`, `associations`, `aggregations`, `includes`, `fields`, `ids`, `term`, `query`, `post-filter`, `grouping`, `total-count-mode`. Top-level `limit`, `page`, and `term` parameters override values in criteria JSON.

**Examples:**
```json
{"entity": "product", "term": "shirt", "limit": 5}
```

```json
{"entity": "product", "criteria": "{\"filter\": [{\"type\": \"contains\", \"field\": \"name\", \"value\": \"shirt\"}], \"limit\": 10}"}
```

```json
{"entity": "order", "criteria": "{\"sort\": [{\"field\": \"createdAt\", \"order\": \"DESC\"}], \"limit\": 5, \"associations\": {\"lineItems\": {}}}"}
```

### shopware-entity-read
Read a single entity by its UUID. Use when you already have an entity ID; for searching by other fields, use `shopware-entity-search`.

**Parameters:**
- `entity` (string, required) -- Entity name
- `id` (string, required) -- Entity UUID
- `criteria` (string, optional) -- JSON criteria for associations

### shopware-system-config-read
Read system configuration values. Pass a domain prefix for all keys under it, or a full key for a single value.

**Parameters:**
- `key` (string, required) -- Config key or domain prefix (e.g., `core.listing`)
- `salesChannelId` (string, optional) -- Scope to a sales channel

### shopware-storefront-search
Search products with storefront context. Unlike `shopware-entity-search`, returns prices resolved for the sales channel including customer group pricing and tax rules.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID (see `shopware://sales-channels` resource)
- `criteria` (string, optional) -- JSON criteria object
- `customerId` (string, optional) -- Customer UUID for customer-specific pricing

### shopware-console-command
Execute allowlisted console commands. Only safe, administrative commands are permitted.

**Parameters:**
- `command` (string, required) -- Command name (must be in allowlist)
- `arguments` (string, optional) -- JSON object of command arguments/options

Only commands listed in `shopware.mcp.allowed_console_commands` can be executed. Default allowlist: `cache:clear`, `cache:warmup`, `plugin:list`, `plugin:refresh`, `scheduled-task:list`, `theme:compile`, `debug:router`, `debug:mcp`, `messenger:stats`, `assets:install`.

---

## Write Tools

All write tools default to `dryRun=true`. Always preview changes before committing.

### shopware-entity-upsert
Create or update entity data. Use `shopware-entity-schema` to understand required fields first.

**Parameters:**
- `entity` (string, required) -- Entity name
- `payload` (string, required) -- JSON data (single object or array)
- `dryRun` (bool, default: `true`) -- Preview without persisting

### shopware-entity-delete
Delete entities by their UUIDs. Returns cascade effects preview in dryRun mode.

**Parameters:**
- `entity` (string, required) -- Entity name
- `ids` (string, required) -- JSON array of UUIDs
- `dryRun` (bool, default: `true`) -- Preview cascade effects

### shopware-system-config-write
Update a system configuration value. Shows before/after diff in dryRun mode.

**Parameters:**
- `key` (string, required) -- Config key
- `value` (string, required) -- New value (JSON-encoded for complex types)
- `salesChannelId` (string, optional) -- Scope to a sales channel
- `dryRun` (bool, default: `true`) -- Preview the diff

### shopware-state-machine-transition
Transition an entity's state machine state. See `shopware://state-machines` resource for valid states and transitions.

**Parameters:**
- `entityName` (string, required) -- Entity name (e.g., `order`, `order_delivery`, `order_transaction`)
- `entityId` (string, required) -- Entity UUID
- `actionName` (string, required) -- Transition action (e.g., `process`, `complete`, `cancel`, `refund`)
- `stateFieldName` (string, default: `stateId`) -- State field name
- `dryRun` (bool, default: `true`) -- Validate without executing

---

## Resources

Resources are static reference data available via MCP resource URIs. They do not require tool calls.

| URI | Name | Description |
|---|---|---|
| `shopware://entities` | Entity list | All registered entity names |
| `shopware://sales-channels` | Sales channels | All sales channels with IDs, names, types, and domains |
| `shopware://currencies` | Currencies | All currencies with ISO codes, symbols, and factors |
| `shopware://languages` | Languages | All languages with locale codes |
| `shopware://state-machines` | State machines | All state machines with states and transitions |
| `shopware://business-events` | Business events | All events that can trigger flows |
| `shopware://flow-actions` | Flow actions | All flow actions available in Flow Builder |
