# Entity

@README.md

## Source Code References

- `ContentRouteEntity` - Content route entity (used in both Admin and Store APIs)
- `ContentRouteDefinition` - Entity definition with field-level API visibility

## Constraints

### Sales Channel Assignment

Routes are filtered by sales channel through their **layout assignments**, not through direct route-to-sales-channel mappings.

**How it works:**

Routes are included if:
- Route has at least one layout assignment for the current sales channel, OR
- Route has at least one layout assignment with null salesChannelId (global)

Routes without any layout assignments won't be returned by RouteCollectionBuilder.

**Implementation:**
```php
$criteria->addFilter(new OrFilter([
    new EqualsFilter('layoutAssignments.salesChannelId', $context->getSalesChannel()->getId()),
    new EqualsFilter('layoutAssignments.salesChannelId', null),
]));
```

See `RouteCollectionBuilder::build()` for full implementation.

### Entity Structure

Routes must have:
- `url_pattern`: Pattern with `{placeholders}`
- `parameter_bindings`: Maps placeholders to resolution rules (`array<string, ParameterBinding>`)
- `priority`: Tie-breaker for pattern matching (optional, default: 0)
- `name`: Human-readable route identifier (required)
- `active`: Boolean flag, inactive routes excluded from matching (default: true)
- `overrides`: Optional configuration overrides

Layout assignments stored in `content_layout_assignment` table with `route_id` foreign key.

### Parameter Binding Structure

See Routing/IdResolution/AGENTS.md for detailed structure.

The field stores `array<string, ParameterBinding>` objects. JSON configuration format:
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
// Deserialized to ParameterBinding objects with ResolutionConfig value objects
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
