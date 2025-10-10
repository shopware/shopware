# Entity

@README.md

## Source Code References

- `ContentRouteEntity` - Content route entity (used in both Admin and Store APIs)
- `ContentRouteDefinition` - Entity definition with field-level API visibility

## Constraints

### Sales Channel Assignment

Routes are filtered by sales channel through their **layout assignments**, not through direct route-to-sales-channel mappings.

**How it works:**

RouteCollectionBuilder filters routes by querying:
```php
$criteria->addFilter(new OrFilter([
    new EqualsFilter('layoutAssignments.salesChannelId', $context->getSalesChannel()->getId()),
    new EqualsFilter('layoutAssignments.salesChannelId', null),
]));
```

RouteCollectionBuilder filters routes by querying with sales channel context. Routes are included if:
- Route has layout assignment for current sales channel, OR
- Route has layout assignment with null salesChannelId (global)

Routes without any layout assignments won't be returned. See `RouteCollectionBuilder::build()` for Criteria implementation.

### Entity Structure

Routes must have:
- `url_pattern`: Pattern with `{placeholders}`
- `parameter_binding`: Maps placeholders to resolution rules
- `priority`: Tie-breaker for pattern matching

Layout assignments stored in `content_layout_assignment` table with `route_id` foreign key.

### Parameter Binding Structure

See Routing/IdResolution/AGENTS.md for detailed structure.

Basic format:
```php
[
    'paramName' => [
        'placeholder' => 'outputName',  // Optional, defaults to paramName
        'resolution' => [               // Optional, omit for passthrough
            'entity' => 'entity_name',
            'match_field' => 'field_name'
        ]
    ]
]
```

### Layout Assignment

Layout assignments stored in `content_layout_assignment` table, not on route entity.

Create assignments via `content_layout_assignment.repository`:
```php
$this->assignmentRepository->create([[
    'routeId' => $routeId,
    'entityType' => 'product',       // or null for route-level default
    'entityId' => $productId,        // or null for wildcard
    'associationPath' => null,       // or 'product.categories' for association matching
    'salesChannelId' => $scId,       // or null for global
    'layoutId' => $layoutId,
    'priority' => 100,               // evaluation order (DESC)
]], $context);
```

## Quick Reference

- **URL pattern**: Symfony route syntax with {parameters}
- **Priority**: Integer for tie-breaking
- **ID generation**: Always Uuid::randomHex()
