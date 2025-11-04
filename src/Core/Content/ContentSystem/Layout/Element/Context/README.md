# Context

Context provider and consumer definitions for content elements.

## Key Classes

- `ContextProvider` - Defines what context an element exposes to descendants
- `ContextConsumer` - Defines what context an element receives from ancestors
- `ContextDefinitions` - Container holding providers and consumers for an element
- `ContextDependencyAnalyzer` - Analyzes context dependency chains for tree refinement

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

Virtual providers generated at parse-time by `ContentElementFieldSerializer`, not persisted to database.

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

## See Also

- `Layout/Field/` - Serialization implementation (parse-time expansion)
