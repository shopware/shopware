# MCP Tool Reference

All core tools are namespaced with `shopware-` prefix. Plugin tools must use their own namespace (e.g., `my-plugin-tool-name`).

## Read Tools

### shopware-entity-schema
Get the field and association schema of any Shopware entity.

**Parameters:**
- `entity` (string, required) -- Entity name (e.g., `product`, `order`)

**Example:**
```json
{"entity": "product"}
```

### shopware-entity-search
Search entities using the Admin API criteria format.

**Parameters:**
- `entity` (string, required) -- Entity name
- `criteria` (string, optional) -- JSON criteria object

**Criteria format:** Supports `filter`, `sort`, `limit`, `page`, `associations`, `aggregations`, `includes`, `fields`, `ids`, `term`, `query`, `post-filter`, `grouping`, `total-count-mode`.

**Examples:**
```json
{"entity": "product", "criteria": "{\"filter\": [{\"type\": \"contains\", \"field\": \"name\", \"value\": \"shirt\"}], \"limit\": 10}"}
```

```json
{"entity": "order", "criteria": "{\"sort\": [{\"field\": \"createdAt\", \"order\": \"DESC\"}], \"limit\": 5, \"associations\": {\"lineItems\": {}}}"}
```

### shopware-entity-read
Read a single entity by ID.

**Parameters:**
- `entity` (string, required) -- Entity name
- `id` (string, required) -- Entity UUID
- `criteria` (string, optional) -- JSON criteria for associations

### shopware-system-config-read
Read system configuration values.

**Parameters:**
- `key` (string, required) -- Config key or domain prefix
- `salesChannelId` (string, optional) -- Scope to a sales channel

### shopware-api-routes
List available API routes.

**Parameters:**
- `prefix` (string, optional, default: `/api`) -- Path prefix filter

### shopware-business-events
List all registered business events.

No parameters required.

### shopware-flow-actions
List all registered flow actions (core and app-provided) available in Flow Builder.

No parameters required.

### shopware-storefront-search
Search products with storefront context including resolved prices, customer group pricing, and visibility.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID
- `criteria` (string, optional) -- JSON criteria object
- `customerId` (string, optional) -- Customer UUID for customer-specific pricing

### shopware-console-command
Execute allowlisted console commands.

**Parameters:**
- `command` (string, required) -- Command name (must be in allowlist)
- `arguments` (string, optional) -- JSON object of command arguments/options

Only commands listed in `shopware.mcp.allowed_console_commands` can be executed. Default allowlist: `cache:clear`, `cache:warmup`, `plugin:list`, `plugin:refresh`, `scheduled-task:list`, `theme:compile`, `debug:router`, `debug:mcp`, `messenger:stats`, `assets:install`.

---

## Write Tools

All write tools default to `dryRun=true`. Always preview changes before committing.

### shopware-entity-upsert
Create or update entity data.

**Parameters:**
- `entity` (string, required) -- Entity name
- `payload` (string, required) -- JSON data (single object or array)
- `dryRun` (bool, default: `true`) -- Preview without persisting

### shopware-entity-delete
Delete entities by ID.

**Parameters:**
- `entity` (string, required) -- Entity name
- `ids` (string, required) -- JSON array of UUIDs
- `dryRun` (bool, default: `true`) -- Preview cascade effects

### shopware-system-config-write
Update a system configuration value.

**Parameters:**
- `key` (string, required) -- Config key
- `value` (string, required) -- New value (JSON-encoded for complex types)
- `salesChannelId` (string, optional) -- Scope to a sales channel
- `dryRun` (bool, default: `true`) -- Preview the diff

### shopware-state-machine-transition
Transition an entity's state machine state.

**Parameters:**
- `entityName` (string, required) -- Entity name (e.g., `order`)
- `entityId` (string, required) -- Entity UUID
- `actionName` (string, required) -- Transition action (e.g., `process`, `complete`)
- `stateFieldName` (string, default: `stateId`) -- State field name
- `dryRun` (bool, default: `true`) -- Validate without executing
