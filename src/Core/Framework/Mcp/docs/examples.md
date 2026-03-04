# MCP Usage Examples

## Exploring the data model

**Discover available entities:**
Use the `entity-list` resource (`shopware://entities`) or `shopware-entity-schema` tool.

**Understand an entity's fields:**
```
Tool: shopware-entity-schema
Input: {"entity": "product"}
```

## Searching for products

**Find products containing "shirt":**
```
Tool: shopware-entity-search
Input: {
    "entity": "product",
    "criteria": "{\"filter\": [{\"type\": \"contains\", \"field\": \"name\", \"value\": \"shirt\"}], \"limit\": 5}"
}
```

**Find active products with stock > 10, sorted by name:**
```
Tool: shopware-entity-search
Input: {
    "entity": "product",
    "criteria": "{\"filter\": [{\"type\": \"multi\", \"operator\": \"AND\", \"queries\": [{\"type\": \"equals\", \"field\": \"active\", \"value\": true}, {\"type\": \"range\", \"field\": \"stock\", \"parameters\": {\"gte\": 10}}]}], \"sort\": [{\"field\": \"name\", \"order\": \"ASC\"}]}"
}
```

## Storefront product search

**Search products with resolved prices in a sales channel context:**
```
Tool: shopware-storefront-search
Input: {
    "salesChannelId": "<sales-channel-uuid>",
    "criteria": "{\"filter\": [{\"type\": \"contains\", \"field\": \"name\", \"value\": \"shirt\"}], \"limit\": 5}"
}
```

## Working with orders

**Recent orders with line items:**
```
Tool: shopware-entity-search
Input: {
    "entity": "order",
    "criteria": "{\"sort\": [{\"field\": \"createdAt\", \"order\": \"DESC\"}], \"limit\": 5, \"associations\": {\"lineItems\": {}, \"transactions\": {}}}"
}
```

**Transition order state (preview first):**
```
Tool: shopware-state-machine-transition
Input: {"entityName": "order", "entityId": "<uuid>", "actionName": "process", "dryRun": true}
```

## System configuration

**Read all listing settings:**
```
Tool: shopware-system-config-read
Input: {"key": "core.listing"}
```

**Update a config value (preview):**
```
Tool: shopware-system-config-write
Input: {"key": "core.listing.defaultSorting", "value": "\"price-asc\"", "dryRun": true}
```

## Creating entities

**Create a product (preview):**
```
Tool: shopware-entity-upsert
Input: {
    "entity": "product",
    "payload": "{\"name\": \"New Product\", \"productNumber\": \"SW-NEW-001\", \"stock\": 100, \"taxId\": \"<tax-uuid>\", \"price\": [{\"currencyId\": \"<currency-uuid>\", \"gross\": 29.99, \"net\": 25.20, \"linked\": true}]}",
    "dryRun": true
}
```

## Investigating available events and flow actions

```
Tool: shopware-business-events
```
Returns all events that can trigger flows.

```
Tool: shopware-flow-actions
```
Returns all registered flow actions (core and app-provided).

## Running console commands

**List installed plugins:**
```
Tool: shopware-console-command
Input: {"command": "plugin:list", "arguments": "{\"--format\": \"json\"}"}
```

**Clear the cache:**
```
Tool: shopware-console-command
Input: {"command": "cache:clear"}
```

Only allowlisted commands can be executed. See `shopware.mcp.allowed_console_commands` configuration.
