# IdResolution

@README.md

## Source Code References

- `ParameterExtractor` - Parses placeholders from matched route parameters
- `EntityIdResolver` - Queries entities to resolve IDs from parameter values
- `EntityIdMap` - Container for resolved entity ID mappings
- `ParameterMap` - Container for extracted URL parameters
- `ResolvedData` (Struct/) - Combined structure with parameters and entity IDs

## Constraints

### Two Parameter Types

Detection: Check for `resolution` field existence, NOT parameter name patterns.

**Resolution Parameters** (require entity lookup) - HAS `resolution` field:
```php
'seoUrl' => [
    'placeholder' => 'productId',  // optional, defaults to param name
    'resolution' => [               // PRESENCE of this field = resolution parameter
        'entity' => 'product',
        'match_field' => 'seoPathInfo'
    ]
]
```

**Passthrough Parameters** (direct substitution) - NO `resolution` field:
```php
'page' => [
    'placeholder' => 'page'  // NO 'resolution' field = passthrough parameter
]
```

### EntityIdMap Key Formats

EntityIdResolver stores results in TWO formats:

```php
$entityIdMap = [
    'product_id' => '<uuid>',  // Format 1: {entity}_id
    'product' => '<uuid>'      // Format 2: {entity}
];
```

Always check both formats when reading from EntityIdMap.

### Placeholder Field Behavior

Placeholder name can differ from URL parameter name:

```php
// URL pattern: /product/{slug}
// Parameter binding:
'slug' => [
    'placeholder' => 'productSlug',  // Different from 'slug'
    'resolution' => [/* ... */]
]
// Result: {{productSlug}} in layouts, not {{slug}}
```

If `placeholder` field omitted, defaults to parameter name.

## Entity Query Mechanics

### Criteria Construction

EntityIdResolver builds Criteria with equals filter. See `EntityIdResolver::resolve()` for implementation.

For parameter binding with `match_field: 'seoPathInfo'` and URL parameter value `'laptop-x1'`:

```
Criteria with EqualsFilter('seoPathInfo', 'laptop-x1')
```

### Multiple Entity Resolutions

Routes can resolve multiple entities. Each resolution triggers separate entity query.

## Quick Reference

- **Resolution detection**: Check for `resolution` field, not parameter name
- **Placeholder default**: Defaults to parameter name if not specified
- **Scalar constraint**: Only strings/integers in URL parameters
- **Query type**: Single EqualsFilter per resolution parameter
