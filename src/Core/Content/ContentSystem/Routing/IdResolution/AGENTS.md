# IdResolution

@README.md

## Source Code References

- `ParameterExtractor` - Parses placeholders from matched route parameters, returns `ExtractedParameters`
- `EntityIdResolver` - Queries entities to resolve IDs from parameter values
- `EntityIdMap` - Container for resolved entity ID mappings
- `ParameterMap` - Container for extracted URL parameters
- `ResolvedData` - Combined structure with parameters and entity IDs
- `ParameterBinding` - Value object encapsulating placeholder and resolution config
- `ResolutionConfig` - Value object encapsulating entity, matchField, and constraints
- `ExtractedParameters` - Return type from ParameterExtractor with resolution and passthrough parameters
- `ResolutionParameter` - Individual resolution parameter with value and config
- `ResolutionParameterMap` - Collection of resolution parameters

## Constraints

### Two Parameter Types

Detection: Check for `resolution` field existence, NOT parameter name patterns.

**Note**: The array structures shown below represent JSON configuration format. At runtime, these are deserialized into `ParameterBinding` objects containing `ResolutionConfig` value objects.

**Resolution Parameters** (require entity lookup) - HAS `resolution` field:
```php
'seoUrl' => [
    'placeholder' => 'productId',  // optional, defaults to param name
    'resolution' => [               // PRESENCE of this field = resolution parameter
        'entity' => 'product',
        'match_field' => 'seoPathInfo'
    ]
]
// Deserialized to: ParameterBinding with ResolutionConfig
```

**Passthrough Parameters** (direct substitution) - NO `resolution` field:
```php
'page' => [
    'placeholder' => 'page'  // NO 'resolution' field = passthrough parameter
]
// Deserialized to: ParameterBinding with null resolution
```

### EntityIdMap Keys

EntityIdResolver stores results keyed by placeholder name:

```php
$entityIdMap = ['productId' => '<uuid>'];  // Key is placeholder, not entity type
```

Placeholder defaults to URL parameter name if not explicitly set in ParameterBinding.

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

### Constraints

Optional constraint filters in ResolutionConfig. Uses QueryStringParser format:

```php
'constraints' => [
    ['type' => 'equals', 'field' => 'active', 'value' => true],
    ['type' => 'range', 'field' => 'stock', 'parameters' => ['gte' => 10]],
]
```

Multiple constraints combine with AND. Supported filter types: equals, range, contains, prefix, suffix, equalsAny, multi, and, or, not, nand, nor.

## Entity Query Mechanics

### Criteria Construction

EntityIdResolver builds Criteria with equals filter. See `EntityIdResolver::resolve()` for implementation.

For parameter binding with `match_field: 'seoPathInfo'` and URL parameter value `'laptop-x1'`:

```
Criteria with EqualsFilter('seoPathInfo', 'laptop-x1')
```

### Query Batching

EntityIdResolver batches same-entity-type resolutions via `ResolutionParameterMap::groupByEntityType()`:

```php
/compare/{productA}/{productB}  → 1 query (OR filter)
/page/{product}/{category}      → 2 queries (different types)
```

N parameters of same entity type = 1 query, not N queries.

## Quick Reference

- **Resolution detection**: Check for `resolution` field, not parameter name
- **Placeholder default**: Defaults to parameter name if not specified
- **Scalar constraint**: Only strings/integers in URL parameters
- **Query batching**: Same entity type = 1 query (OR filter), different types = separate queries
- **Constraints**: Optional filtering via scalar (EqualsFilter) or range operators (RangeFilter)
- **EntityIdMap keys**: Placeholder names, not entity type names
- **Exception on not found**: EntityIdResolver throws `ContentSystemException::parameterResolutionFailed()` if entity not found
