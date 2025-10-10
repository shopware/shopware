# LayoutResolution

Priority-based layout assignment. Determines which content layout to render based on route-scoped assignments and sales channel context.

## Why Priority-Based Assignment

Current Shopware CMS stores `cms_page_id` directly on entities but lacks sales channel dimensionality. Same product must use different layouts in different sales channels without duplicating entities. Priority-based assignment queries `content_layout_assignment` table with route + entity + sales channel context.

## Key Class

- `LayoutResolver` - Priority-based assignment matching, returns layout ID or null

## Assignment Structure

All layout assignments stored in `content_layout_assignment` table with:
- `route_id` - Scopes assignment to specific route
- `entity_type` (nullable) - Entity type filter (e.g., "product", "category")
- `entity_id` (nullable) - Specific entity ID or NULL for wildcard
- `association_path` (nullable) - Multi-level association path (e.g., "product.categories.parent")
- `sales_channel_id` (nullable) - Sales channel scope or NULL for global
- `layout_id` - Layout to render
- `priority` (integer) - Evaluation order (DESC)

## Resolution Logic

```
LayoutResolver loads assignments for route WHERE:
  - route_id = {matched_route_id}
  - sales_channel_id = {context} OR sales_channel_id IS NULL

Sort by:
  - priority DESC
  - sales_channel_id DESC (channel-specific over global)

For each assignment (first match wins):
  If association_path IS NULL:
    → matchesDirect() - entity type/ID matching
  Else:
    → matchesAssociation() - traverse association path, collect IDs

  If match found:
    return assignment.layout_id
```

First match wins. Remaining assignments never evaluated.

## Assignment Matching Types

### Direct Entity Matching

Matches by `entity_type` and `entity_id`. Three patterns:

**1. Specific entity:**
```
entity_type: "product"
entity_id: "uuid-123"
→ Matches exact product with ID uuid-123
```

**2. Wildcard (any entity of type):**
```
entity_type: "product"
entity_id: NULL
→ Matches ANY product
```

**3. Route-level default:**
```
entity_type: NULL
entity_id: NULL
→ Matches ANY entity on this route
```

Matching logic checks resolved entity ID from `ResolvedData` (populated by `EntityIdResolver`).

### Association Path Matching

Matches entities via relationship traversal. Supports multi-level paths.

**Format:** `source_entity.association.path`

**Examples:**
- `product.categories` - Single level: product → categories
- `product.categories.parent` - Multi-level: product → categories → parent
- `product.manufacturer.country` - Multi-level: product → manufacturer → country

**Matching process:**
1. Parse path: split into source entity and association path
2. Get source entity ID from resolved data
3. Load nested association using DAL (e.g., `$criteria->addAssociation('categories.parent')`)
4. Traverse loaded structure, collect all IDs at end of path
5. Check if assignment's `entity_id` matches any collected ID (or NULL for wildcard)

**Use cases:**
- Product detail pages inherit category layouts: `product.categories` → category assignment
- Multi-level inheritance: `product.categories.parent` → parent category layout
- Manufacturer-specific layouts: `product.manufacturer` → manufacturer assignment

Association traversal happens at runtime. DAL natively supports nested paths. LayoutResolver recursively traverses loaded entities and collections.

## Priority-Based Evaluation

Assignments evaluated in strict priority order (DESC). First match wins.

**Common priority patterns:**
- 100: Specific entity overrides (highest priority)
- 80: Type-level wildcards (e.g., "any product")
- 60: Direct association fallbacks (e.g., product.categories)
- 40: Multi-level association fallbacks (e.g., product.categories.parent)
- 0: Route-level defaults (catch-all)

Merchants control specificity via priority. Higher priority = evaluated first.

## Sales Channel Filtering

All queries filter by sales channel:
```
WHERE (
    sales_channel_id = {context_sales_channel_id}
    OR sales_channel_id IS NULL  -- Global assignments
)
ORDER BY sales_channel_id DESC  -- Channel-specific before global
```

Global assignments (NULL sales_channel_id) work across all channels but rank lower than channel-specific.

## Query Details

Single query per resolution:
1. Filter by route_id and sales channel
2. Sort by priority DESC, sales_channel_id DESC
3. Iterate results, evaluate each assignment
4. Return first match

Association matching may trigger additional DAL queries to load nested associations. Wildcards should be used when possible to avoid association overhead.
