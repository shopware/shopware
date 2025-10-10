# LayoutResolution

@README.md

## Source Code References

- `LayoutResolver` - Cascade query executor
- `DirectEntityStep` (Cascade/) - Exact entity match
- `AssociationStep` (Cascade/) - Fallback via relations
- `DefaultLayoutStep` (Cascade/) - Catch-all default
- `CascadeStepFactory` (Cascade/) - Step instantiation

## Constraints

### Cascade Evaluation Order

Steps evaluated in **array order**, first match wins:

```php
'layout_cascade' => [
    ['type' => 'direct', 'entity' => 'product'],           // Step 1: Try exact product match
    ['type' => 'association', 'entity' => 'category',      // Step 2: Try product's categories
     'association' => 'categories', 'source' => 'product'],
    ['type' => 'default']                                  // Step 3: Fall back to default
]

// Process: Step 1 → if no match → Step 2 → if no match → Step 3
```

Never rely on implicit ordering - cascade steps execute in explicit array order. **First match wins, remaining steps never execute.**

### Three Cascade Step Types

**1. DirectEntityStep** (exact entity match):
```php
['type' => 'direct', 'entity' => 'product']
// Looks up: WHERE entity_type = 'product' AND entity_id = {resolved_product_id}
```

Tries both `{entity}_id` and `{entity}` key formats in resolved data.

**2. AssociationStep** (fallback via relations):
```php
['type' => 'association', 'entity' => 'category', 'association' => 'categories', 'source' => 'product']
// Process:
// 1. Load product.categories association
// 2. Extract category IDs
// 3. Find layout assigned to ANY category (first match wins)
```

Association traversal happens at runtime - loads association, extracts IDs, queries layout assignments.

**3. DefaultLayoutStep** (catch-all):
```php
['type' => 'default']
// Looks up: WHERE entity_type IS NULL AND entity_id IS NULL AND sales_channel_id = {context}
```

Always place as last step for guaranteed fallback.

### Static vs Dynamic Assignment

**Static assignment** (skip cascade entirely):
```php
$route->setLayoutId('fixed-layout-uuid');
// No cascade query, uses fixed layout
```

**Dynamic assignment** (cascade query):
```php
$route->setLayoutCascade([
    ['type' => 'direct', 'entity' => 'product'],
    ['type' => 'default']
]);
// Query content_layout_assignment table with cascade logic
```

Routes have either `layout_id` (static) OR `layout_cascade` (dynamic), never both.

### Sales Channel Filtering

All cascade queries filter by sales channel:

```
WHERE (/* step filters */) AND (
    sales_channel_id = {context_sales_channel_id}
    OR sales_channel_id IS NULL  -- Global assignments
)
```

## Query Optimization

LayoutResolver builds one query combining all step filters with OR conditions. NOT multiple queries per step.

AssociationStep loads associations at runtime, adding query overhead. Use DirectEntityStep when possible.

## Quick Reference

- **Evaluation**: Array order, first match wins
- **Step types**: Direct (exact), Association (fallback), Default (catch-all)
- **Sales channel**: Always filtered (with NULL fallback)
- **Query**: Single OR query, not multiple queries
- **Assignment**: Static (`layout_id`) XOR dynamic (`layout_cascade`)
