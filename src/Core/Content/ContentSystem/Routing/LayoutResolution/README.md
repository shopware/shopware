# LayoutResolution

Cascade-based layout lookup. Determines which content layout to render based on route configuration and sales channel context.

## Two Assignment Methods

Routes support static or dynamic layout assignment:

1. **Static**: Fixed `layout_id` in route (all entities use same layout)
2. **Dynamic**: Cascade query against `content_layout_assignment` table

## Why Dynamic Resolution

Current Shopware CMS stores `cms_page_id` directly on entities but lacks sales channel dimensionality. Same product must use different layouts in different sales channels without duplicating entities. Dynamic resolution queries assignment table with entity ID + sales channel context.

## Key Class

- `LayoutResolver` - Cascade query executor, returns layout ID or null

## Resolution Logic

```
If route.layout_id exists:
  return route.layout_id (static assignment)

If route.layout_cascade exists:
  query content_layout_assignment WHERE:
    - entity_id matches resolved entity
    - sales_channel_id matches context (with fallback to null)
    - cascade priority determines selection
  return matched layout_id
```

Cascade configuration defines priority order (e.g., specific product → category → default). LayoutResolver walks cascade chain until match found.

## Cascade Step Types

Three step types define lookup strategy. Steps evaluated in array order, first match wins:

### DirectEntityStep

Exact entity match. Looks up layout assigned to specific entity.

```
Config: {type: "direct", entity: "product"}
Resolution: Finds layout assigned to resolved product_id
```

Tries both `{entity}_id` and `{entity}` keys in resolved data.

### AssociationStep

Fallback via associations. Loads association from source entity, looks up layouts for associated entities.

```
Config: {type: "association", entity: "category", association: "categories", source: "product"}
Resolution:
  1. Load product.categories association
  2. Get category IDs
  3. Find layout assigned to any category (first match wins)
```

Use case: Product detail page can use category layouts as fallback. Merchant assigns layout to category, all products in category inherit unless overridden.

Association traversal happens at runtime. Query fetches association, extracts IDs, looks up layout assignments for those IDs.

### DefaultLayoutStep

Catch-all default. Looks up layout with `entityType=null` and `entityId=null`.

```
Config: {type: "default"}
Resolution: Finds default layout for sales channel
```

Last fallback when no entity-specific or association layouts found.

## Query Details

All cascade queries filter by sales channel ID. LayoutResolver builds OR filter combining all step filters, executes single query against `content_layout_assignment` table. Each step evaluates result set to find its match.

## Subdirectory

- Cascade/: Cascade step implementations (DirectEntityStep, AssociationStep, DefaultLayoutStep, CascadeStepFactory)
