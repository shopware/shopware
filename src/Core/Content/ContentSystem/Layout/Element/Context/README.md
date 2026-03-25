# Context

Context provider and consumer definitions for content elements.

## Key Classes

- `ContextProvider` - Defines what context an element exposes to descendants
- `ContextConsumer` - Defines what context an element receives from ancestors
- `ContextDefinitions` - Container holding providers and consumers for an element
- `ContextDependencyAnalyzer` - Analyzes context dependencies for tree pruning

## Redistribution Shorthand

`ContextConsumer::$redistribute` auto-generates a broadcast provider when `true`. Simplifies common pattern of accepting context and immediately re-providing to children.

**Example:**
```json
{
  "accepts_context": {
    "product": {
      "type": "single",
      "required": true,
      "redistribute": true
    }
  }
}
```

Equivalent to explicit provider:
```json
{
  "accepts_context": {
    "product": {"type": "single", "required": true}
  },
  "provides_context": {
    "product": {"type": "single", "distribution": "broadcast"}
  }
}
```

Virtual providers not persisted to database.

## Consumer Alias

`ContextConsumer::$consumerAlias` transforms context key when redistributing. Element accepts under one key, provides to children under different key.

**Example:**
```json
{
  "accepts_context": {
    "featuredProduct": {
      "type": "single",
      "required": true,
      "redistribute": true,
      "consumer_alias": "product"
    }
  }
}
```

Element accepts context as `"featuredProduct"`, children receive as `"product"`. Useful for semantic clarity.

## Property Alias

`ContextConsumer::$propertyAlias` renames storage key for resolved context data within consuming element.

**Example:**
```json
{
  "accepts_context": {
    "product.cover": {
      "type": "single",
      "required": true,
      "property_alias": "cover"
    }
  }
}
```

Element receives `product.cover` from parent but stores as `cover` property. Useful for simplifying nested paths.

**Constraints:**
- No dots allowed in alias value
- Each alias must be unique within element (validated at serialization)

**Implementation:** `ContextConsumer::$propertyAlias` applied in `ContextResolutionVisitor::setContextForConsumer()`.

## Distribution Strategies

Distribution configs (value objects with `distribute()` method) control how provider data distributes to consumers:

- `BroadcastDistributionConfig` - All children receive identical data
- `IndexedDistributionConfig` - Position-based: child[N] gets data[N]
- `KeyedDistributionConfig` - Children receive by matching `data_key` property
- `SlicedDistributionConfig` - Data split into chunks across children
- `IteratorDistributionConfig` - Round-robin distribution

See `Distribution/` subdirectory for implementation.

## Subdirectory

- `Distribution/` - Distribution config value objects (DistributionConfig interface and implementations)
