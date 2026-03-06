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

**Simple term search (using flattened params):**
```
Tool: shopware-entity-search
Input: {"entity": "product", "term": "shirt", "limit": 5}
```

**Search with criteria JSON:**
```
Tool: shopware-entity-search
Input: {
    "entity": "product",
    "criteria": "{\"filter\": [{\"type\": \"contains\", \"field\": \"name\", \"value\": \"shirt\"}], \"limit\": 10}"
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

**Paginate through results:**
```
Tool: shopware-entity-search
Input: {"entity": "product", "limit": 10, "page": 3}
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

**Quick order lookup by order number:**
```
Tool: shopware-order-summary
Input: {"orderNumber": "10001"}
```
Returns order with customer info, line items, payment status, and delivery status in a single call.

**Quick order lookup by UUID:**
```
Tool: shopware-order-summary
Input: {"orderId": "<uuid>"}
```

**Recent orders with line items (generic search):**
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

## Customer lookup

**Find customer by email with order history:**
```
Tool: shopware-customer-lookup
Input: {"email": "john@example.com"}
```

**Find customer by customer number:**
```
Tool: shopware-customer-lookup
Input: {"customerNumber": "SW10001"}
```

## Creating products (simplified)

**Preview a new product (dryRun):**
```
Tool: shopware-product-create
Input: {"name": "Blue T-Shirt", "productNumber": "SW-BLUE-001", "grossPrice": 29.99, "stock": 100}
```

**Create with custom tax rate and categories:**
```
Tool: shopware-product-create
Input: {"name": "Organic Tea", "productNumber": "SW-TEA-001", "grossPrice": 12.99, "taxRate": 7, "categories": "Food, Beverages", "dryRun": false}
```

## Revenue reporting

**Monthly revenue report:**
```
Tool: shopware-revenue-report
Input: {"from": "2025-03-01", "to": "2025-03-31"}
```

**Weekly revenue report for a specific sales channel:**
```
Tool: shopware-revenue-report
Input: {"from": "2025-01-01", "to": "2025-03-31", "groupBy": "week", "salesChannelId": "<uuid>"}
```

## Creating flow automations

**Simple flow: tag all new orders (preview):**
```
Tool: shopware-flow-create
Input: {
    "name": "Tag new orders",
    "eventName": "checkout.order.placed",
    "actionName": "action.add.order.tag",
    "actionConfig": "{\"tagIds\": {\"<tag-uuid>\": \"new-order\"}}"
}
```

**Conditional flow: add VIP tag when order total exceeds rule threshold:**
```
Tool: shopware-flow-create
Input: {
    "name": "VIP tag for high-value orders",
    "eventName": "checkout.order.placed",
    "actionName": "action.add.order.tag",
    "actionConfig": "{\"tagIds\": {\"<tag-uuid>\": \"vip\"}}",
    "ruleId": "<rule-uuid>"
}
```

**Discovery workflow -- find the right event, action, and config:**
1. Read `shopware://business-events` resource to find available trigger events
2. Read `shopware://flow-actions` resource to find available actions
3. For tag actions: `shopware-entity-search` on `tag` entity to find tag IDs
4. For state actions: read `shopware://state-machines` resource for valid state IDs
5. For conditional flows: `shopware-entity-search` on `rule` entity to find an existing rule
6. Call `shopware-flow-create` with dryRun=true to preview, then dryRun=false to persist

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

## Storefront checkout flow

**Step 1: Create a cart:**
```
Tool: shopware-cart-manage
Input: {"salesChannelId": "<uuid>", "action": "create"}
-> Returns {"token": "abc123", ...}
```

**Step 2: Add products to the cart:**
```
Tool: shopware-cart-manage
Input: {"salesChannelId": "<uuid>", "action": "add", "token": "abc123", "productId": "<product-uuid>", "quantity": 2}
-> Returns cart with line items and totals
```

**Step 3: Check available payment and shipping methods:**
```
Tool: shopware-checkout-methods
Input: {"salesChannelId": "<uuid>", "type": "all"}
-> Returns available payment and shipping methods with IDs
```

**Step 4: Preview the order (dryRun):**
```
Tool: shopware-cart-checkout
Input: {"salesChannelId": "<uuid>", "token": "abc123", "customerId": "<customer-uuid>", "paymentMethodId": "<payment-uuid>", "dryRun": true}
-> Returns order preview with totals
```

**Step 5: Place the order:**
```
Tool: shopware-cart-checkout
Input: {"salesChannelId": "<uuid>", "token": "abc123", "customerId": "<customer-uuid>", "paymentMethodId": "<payment-uuid>", "dryRun": false}
-> Returns {"orderId": "<uuid>"}
```

## Using resources

**List available events and flow actions:**
Read the `shopware://business-events` and `shopware://flow-actions` resources.

**Find sales channel IDs:**
Read the `shopware://sales-channels` resource.

**Check valid state transitions:**
Read the `shopware://state-machines` resource to see all states and transitions for order, delivery, and transaction state machines.

## Running console commands

**List installed plugins:**
```
Tool: shopware-console-command
Input: {"command": "plugin:list", "arguments": "{\"--format\": \"json\"}"}
```

**List API routes:**
```
Tool: shopware-console-command
Input: {"command": "debug:router", "arguments": "{\"--format\": \"json\"}"}
```

**Clear the cache:**
```
Tool: shopware-console-command
Input: {"command": "cache:clear"}
```

Only allowlisted commands can be executed. See `shopware.mcp.allowed_console_commands` configuration.
