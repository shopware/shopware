# Entity

@README.md

## Source Code References

- `ContentRouteEntity` - Content route entity (used in both Admin and Store APIs)
- `ContentRouteDefinition` - Entity definition with field-level API visibility
- `ContentRouteSalesChannelEntity` - Mapping entity (many-to-many junction table)
- `ContentRouteSalesChannelDefinition` - Mapping definition (route ↔ sales channel)

## Constraints

### Sales Channel Assignment

**ContentRouteSalesChannelEntity** is a **mapping entity** (junction table), not a route entity:

- Maps many-to-many relationship: ContentRoute ↔ SalesChannel
- Created automatically by DAL when loading `salesChannels` association
- Never instantiated directly in application code
- Used by RouteCollectionBuilder to filter routes per sales channel

**Global vs. Channel-Specific Routes**:

```php
// Global route (visible in all channels)
$route->setSalesChannels(null);  // or empty collection

// Channel-specific route (visible only in assigned channels)
$route->setSalesChannels($salesChannelCollection);
```

RouteCollectionBuilder filters routes:
```php
if ($salesChannels === null || $salesChannels->count() === 0 || $salesChannels->has($salesChannelId))
```

Routes without assignments are global. Routes with assignments are visible only in those channels.

### Entity Structure

Routes must have:
- `url_pattern`: Pattern with `{placeholders}`
- `parameter_binding`: Maps placeholders to resolution rules
- `priority`: Tie-breaker for pattern matching
- Either `layout_id` (static) OR `layout_cascade` (dynamic)

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

Either static OR dynamic:

**Static**:
```php
$route->setLayoutId($layoutId);
$route->setLayoutCascade(null);
```

**Dynamic**:
```php
$route->setLayoutId(null);
$route->setLayoutCascade([
    ['type' => 'direct', 'entity' => 'product'],
    ['type' => 'default']
]);
```

## Quick Reference

- **URL pattern**: Symfony route syntax with {parameters}
- **Priority**: Integer for tie-breaking
- **ID generation**: Always Uuid::randomHex()
