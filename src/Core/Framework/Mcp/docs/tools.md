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
Retrieve matching entity records. Returns entities with pagination metadata. Does **not** return aggregation results — use `shopware-entity-aggregate` for counts, averages, and other metrics.

**Response optimization:** When no `includes` are specified, responses are automatically optimized to return only scalar fields and explicitly requested associations. This strips auto-loaded noise (thumbnails, extensions, translated duplicates) and keeps responses compact. Pass your own `includes` in the criteria to override this behavior.

**Parameters:**
- `entity` (string, required) -- Entity name
- `criteria` (string, optional) -- JSON criteria object for advanced queries
- `limit` (int, default: 25) -- Number of results to return
- `page` (int, default: 1) -- Page number for pagination
- `term` (string, optional) -- Full-text search term

**Criteria format:** Supports `filter`, `sort`, `limit`, `page`, `associations`, `includes`, `fields`, `ids`, `term`, `query`, `post-filter`, `grouping`, `total-count-mode`. Top-level `limit`, `page`, and `term` parameters override values in criteria JSON.

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

### shopware-entity-aggregate
Run aggregations over any entity without fetching records. Always returns zero entity rows (internally uses `limit: 0`), so the response is bounded in size regardless of dataset cardinality.

**Why a separate tool instead of aggregations inside `shopware-entity-search`:**
Aggregation results — especially bucket aggregations like `terms` or `date-histogram` — can produce thousands of entries and easily exceed the 100 KB response limit. Mixing entity rows and aggregations in one response compounds this problem: the tool would have to truncate either the records or the aggregation buckets to stay within budget. A dedicated tool with a clear contract (`data.aggregations`, no entity rows) removes the ambiguity and guarantees a compact, predictable response.

**Parameters:**
- `entity` (string, required) -- Entity name
- `aggregations` (string, required) -- JSON array of aggregation definitions (same format as Admin API criteria `aggregations` field)
- `filters` (string, optional, default `[]`) -- JSON array of filter definitions to narrow the data set

**Supported aggregation types:** `avg`, `sum`, `min`, `max`, `count`, `terms`, `date-histogram`, `range`, `filter`, `entity`

**Response:** `{"success": true, "data": {"aggregations": { "<name>": { ... } }}}`

**Examples:**
```json
{"entity": "order", "aggregations": "[{\"type\": \"avg\", \"name\": \"avgOrderValue\", \"field\": \"amountTotal\"}]"}
```

```json
{
  "entity": "newsletter_recipient",
  "aggregations": "[{\"type\": \"count\", \"name\": \"total\", \"field\": \"id\"}]",
  "filters": "[{\"type\": \"equals\", \"field\": \"status\", \"value\": \"optIn\"}]"
}
```

```json
{
  "entity": "order",
  "aggregations": "[{\"type\": \"date-histogram\", \"name\": \"ordersByMonth\", \"field\": \"orderDateTime\", \"interval\": \"month\"}]",
  "filters": "[{\"type\": \"range\", \"field\": \"orderDateTime\", \"parameters\": {\"gte\": \"2025-01-01\"}}]"
}
```

### shopware-entity-read
Read a single entity by its UUID. Use when you already have an entity ID; for searching by other fields, use `shopware-entity-search`.

**Response optimization:** Same as `shopware-entity-search` -- responses are automatically optimized when no `includes` are specified.

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
Search products with storefront context. Unlike `shopware-entity-search`, returns prices resolved for the sales channel including customer group pricing and tax rules. Supports human-readable property filters.

**Response optimization:** Same as `shopware-entity-search` -- responses are automatically optimized when no `includes` are specified. Uses `JsonEntityEncoder` (not the Store API serializer), so `includes`/`excludes` filtering works correctly.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID (see `shopware://sales-channels` resource)
- `criteria` (string, optional) -- JSON criteria object
- `customerId` (string, optional) -- Customer UUID for customer-specific pricing
- `properties` (string, optional) -- JSON object mapping property group names to option names, e.g. `{"Color": "Red", "Size": "42"}`. Names are resolved to UUIDs automatically.
- `term` (string, optional) -- Full-text search term, e.g. "shoes"

---

## Write Tools

All write tools default to `dryRun=true`. Always preview changes before committing.

### shopware-entity-upsert
Create or update entity data. Use `shopware-entity-schema` to understand required fields first.

**ACL:** Permissions are checked per item, not upfront as a union. Items without an `id` require `<entity>:create`; items with an `id` require `<entity>:update`. Mixed payloads (some with ID, some without) require both. This means an integration with only `:create` or only `:update` can use the tool without being blocked by the other permission.

**Parameters:**
- `entity` (string, required) -- Entity name
- `payload` (string, required) -- JSON data (single object or array). Include `id` to update an existing record; omit `id` to create.
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

## Outcome Tools

Outcome tools encapsulate common multi-step workflows into a single call with flattened, human-readable parameters.

### shopware-order-summary
Look up an order by order number or UUID and get a pre-formatted summary with customer info, line items, payment status, and delivery status.

**Parameters:**
- `orderNumber` (string, optional) -- Order number (e.g., "10001"). Mutually exclusive with `orderId`
- `orderId` (string, optional) -- Order UUID. Mutually exclusive with `orderNumber`

At least one parameter must be provided.

**Example:**
```json
{"orderNumber": "10001"}
```

### shopware-customer-lookup
Look up a customer by email, customer number, or UUID and get profile with order history.

**Parameters:**
- `email` (string, optional) -- Customer email address
- `customerNumber` (string, optional) -- Customer number
- `customerId` (string, optional) -- Customer UUID

At least one parameter must be provided.

**Example:**
```json
{"email": "john@example.com"}
```

### shopware-product-create
Create a product with human-readable inputs. Automatically resolves tax rate to tax ID, currency ISO code to currency ID, and builds the nested price structure. Defaults to `dryRun=true`.

**Parameters:**
- `name` (string, required) -- Product name
- `productNumber` (string, required) -- Unique product number (SKU)
- `grossPrice` (float, required) -- Gross price
- `taxRate` (float, default: 19) -- Tax percentage (e.g., `19` for 19%)
- `currencyCode` (string, default: "EUR") -- ISO 4217 currency code
- `stock` (int, default: 0) -- Initial stock
- `description` (string, optional) -- Product description (HTML)
- `categories` (string, optional) -- Comma-separated exact category names to assign
- `active` (bool, default: true) -- Whether product is active
- `dryRun` (bool, default: true) -- Preview without persisting

**Example:**
```json
{"name": "Blue T-Shirt", "productNumber": "SW-BLUE-001", "grossPrice": 29.99, "stock": 100}
```

### shopware-revenue-report
Generate a revenue report for a date range. Excludes cancelled orders.

**Parameters:**
- `from` (string, required) -- Start date (ISO 8601, e.g., "2025-01-01")
- `to` (string, required) -- End date (ISO 8601, e.g., "2025-01-31")
- `groupBy` (string, default: "day") -- Grouping: `day`, `week`, `month`
- `salesChannelId` (string, optional) -- Filter by sales channel

**Example:**
```json
{"from": "2025-03-01", "to": "2025-03-31", "groupBy": "week"}
```

### shopware-order-cancel
Cancel an order including its transactions and deliveries in one call. Looks up the order, cancels the order state, refunds or cancels each transaction, and cancels each delivery. Defaults to `dryRun=true`.

**Parameters:**
- `orderNumber` (string, optional) -- Order number (e.g., "10001"). Mutually exclusive with `orderId`
- `orderId` (string, optional) -- Order UUID. Mutually exclusive with `orderNumber`
- `refundTransactions` (bool, default: `false`) -- If true, refund paid transactions instead of cancelling them
- `dryRun` (bool, default: `true`) -- Preview transitions without executing

At least one of `orderNumber` or `orderId` must be provided.

**Example:**
```json
{"orderNumber": "10001", "refundTransactions": true, "dryRun": true}
```

### shopware-bestseller-report
Get the top-selling products by quantity in a date range. Excludes cancelled orders.

**Parameters:**
- `from` (string, required) -- Start date (ISO 8601, e.g., "2025-01-01")
- `to` (string, required) -- End date (ISO 8601, e.g., "2025-01-31")
- `limit` (int, default: 10) -- Number of top products to return (1-100)
- `salesChannelId` (string, optional) -- Filter by sales channel

**Example:**
```json
{"from": "2025-03-01", "to": "2025-03-31", "limit": 5}
```

---

## Storefront Tools

Storefront tools use the Store API / SalesChannelContext layer for customer-facing operations. They require a `salesChannelId` (see `shopware://sales-channels` resource).

### shopware-cart-manage
Manage a storefront shopping cart. Supports creating carts, adding/removing/updating line items, and viewing cart state.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID
- `action` (string, required) -- One of: `create`, `add`, `remove`, `update`, `get`
- `token` (string, required for non-create) -- Cart token returned by `create`
- `productId` (string, required for `add`) -- Product UUID
- `quantity` (int, default: 1) -- Quantity for `add` and `update`
- `lineItemId` (string, required for `remove`/`update`) -- Line item identifier
- `customerId` (string, optional) -- Customer UUID for customer-specific pricing

**Example (create):**
```json
{"salesChannelId": "<uuid>", "action": "create"}
```

**Example (add):**
```json
{"salesChannelId": "<uuid>", "action": "add", "token": "<token>", "productId": "<uuid>", "quantity": 2}
```

### shopware-cart-checkout
Place an order from an existing cart. Requires a registered customer. Defaults to `dryRun=true`.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID
- `token` (string, required) -- Cart token from `shopware-cart-manage`
- `customerId` (string, required) -- Customer UUID
- `paymentMethodId` (string, optional) -- Payment method UUID (defaults to sales channel default)
- `shippingMethodId` (string, optional) -- Shipping method UUID (defaults to sales channel default)
- `dryRun` (bool, default: true) -- Preview order without placing it

**Example:**
```json
{"salesChannelId": "<uuid>", "token": "<token>", "customerId": "<uuid>", "dryRun": true}
```

### shopware-checkout-methods
List available payment and shipping methods for a sales channel.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID
- `type` (string, default: "all") -- One of: `payment`, `shipping`, `all`

**Example:**
```json
{"salesChannelId": "<uuid>", "type": "all"}
```

---

### shopware-media-upload
Upload a media file from a public URL. Optionally assign it as the cover image of a product.

**Parameters:**
- `url` (string, required) -- Public URL of the file to download
- `fileName` (string, optional) -- Desired file name (defaults to basename of URL)
- `mediaFolderId` (string, optional) -- UUID of the media folder to place the file in
- `productId` (string, optional) -- If provided, assigns the uploaded media as product cover image

**ACL:** `media:create`, optionally `product:update` if assigning to product

---

### shopware-theme-config
Read or update theme configuration for a sales channel. Manage brand colors, logos, and fonts.

**Parameters:**
- `salesChannelId` (string, required) -- Sales channel UUID to find the assigned theme
- `action` (string, required) -- `get` or `update`
- `config` (string, optional) -- For `update`: JSON key-value pairs, e.g. `{"sw-color-brand-primary": {"value": "#0000ff"}}`
- `dryRun` (bool, default true) -- For `update`: preview without persisting

**ACL:** `theme:read` for get, `theme:update` for update

Note: This tool lives in `src/Storefront/Mcp/Tool/` because it depends on Storefront's `ThemeService`.

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
