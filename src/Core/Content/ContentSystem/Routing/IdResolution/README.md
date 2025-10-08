# IdResolution

URL parameters → entity IDs. Extracts placeholders from matched route, queries entities to resolve IDs.

## Why Not Direct ID Usage

URLs use SEO-friendly slugs (`/product/laptop-x1`), not UUIDs. ParameterExtractor parses URL parameters, EntityIdResolver queries entities based on parameter binding configuration to find IDs.

## Constraint

Only scalar values supported in URL placeholders. Can't pass complex objects or arrays through URLs. Parameter binding defines which entity field to query (e.g., `seoUrl` → product lookup).

## Key Classes

- `ParameterExtractor` - Parses placeholders from matched route parameters
- `EntityIdResolver` - Queries entities to resolve IDs from parameter values
- `EntityIdMap` - Container for resolved entity ID mappings
- `ParameterMap` - Container for extracted URL parameters

## Resolution Process

```
URL: /product/laptop-x1
Route pattern: /product/{seoUrl}
Parameter binding: {seoUrl: {entity: product, field: seoPathInfo}}

ParameterExtractor → {seoUrl: "laptop-x1"}
EntityIdResolver → Query products WHERE seoPathInfo = "laptop-x1"
EntityIdMap → {product: <UUID>}
```

ResolvedData (Struct/) contains both parameters and entity IDs. Used downstream for placeholder replacement and data loading.

## Parameter Binding Structure

Route's `parameterBinding` config is `array<string, array>` where key is URL parameter name. Two parameter types:

### Resolution Parameters

Need entity lookup. Config contains `resolution` field with entity query details.

```php
"seoUrl": {
  "placeholder": "seoUrl",    // optional, defaults to param name
  "resolution": {
    "entity": "product",       // entity type
    "match_field": "seoPathInfo"  // field to query
  }
}
```

ParameterExtractor extracts to "resolution" array. EntityIdResolver queries:
```php
EntityRepository::search(
  Criteria::fromArray([
    'filters' => [['type' => 'equals', 'field' => 'seoPathInfo', 'value' => 'laptop-x1']]
  ])
)
```

Result stored in EntityIdMap as `{product_id: "<uuid>"}` or `{product: "<uuid>"}`.

### Passthrough Parameters

Direct substitution, no entity lookup. Config has NO `resolution` field.

```php
"page": {
  "placeholder": "page"
}
```

ParameterExtractor extracts to "passthrough" array. Value used directly in placeholder replacement (`{{page}}` → `"2"`).

Use for: pagination, filters, sorting - any URL parameter that doesn't need entity resolution.

### Placeholder Field

`placeholder` field can differ from URL parameter name:

```php
URL: /product/{slug}
Binding: {"slug": {"placeholder": "productSlug", "resolution": {...}}}
Result: Placeholder is {{productSlug}}, not {{slug}}
```

Useful when URL param name differs from internal naming convention.

## Subdirectories

- Parameter/: Parameter extraction logic (ParameterExtractor)
- Struct/: Data structures (ResolvedData, EntityIdMap, ParameterMap)
