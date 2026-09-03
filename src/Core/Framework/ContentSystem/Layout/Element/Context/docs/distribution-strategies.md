# Distribution Strategies

The five values the `distribution` field of a `providesContext` entry accepts, and the rules governing how far the distributed context reaches.

Strategy determines how provider data is distributed to direct children.

**Broadcast** - All children receive identical data (e.g., product detail page with shared product)

**Indexed** - Children receive data by position: child[N] gets data[N] (e.g., top 3 products in specific slots)

**Keyed** - Children receive data by matching their property value to data keys. The `keyProperty` field (default: `"data_key"`) specifies which element property is used for matching. Consumers need a property matching this name, e.g., `"properties": {"data_key": "featured"}` when using the default.

**Sliced** - Data split into chunks per child, with `sliceSize` items per chunk (default: `10`). E.g., 12 products with `sliceSize: 4` across 3 columns = 4 per column.

**Iterator** - Sequential distribution by position, consumer count ignored. E.g., 10 products distributed to 10 card elements, one each. Positions are counted over the children that consume this key, not over every direct child. The counts need not match: a consuming child past the last item receives nothing (unlike **Indexed**, which pads with null so each one gets a delivery), and items past the last consuming child are dropped.

## Context Flow Rules

Context flows from ancestors to descendants, never sideways or upward.

```
Valid:
  Provider (product detail)
    -- Consumer (title)          <-- Can receive

Invalid:
  Consumer (title)
    -- Provider (product detail) <-- Cannot receive from child

Invalid:
  Provider (product 1)
  Consumer (title)               <-- Siblings cannot share
```

Distribution strategy applies only to direct children. Deeper descendants do NOT receive context unless intermediate elements explicitly re-provide it.

Practical implication: Place consumers as direct children of provider for strategies to work as intended. For multi-level context, intermediate elements must both accept and re-provide context.
