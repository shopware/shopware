@README.md

## Source Code References

- `ContextProvider` - Context exposed to descendants
- `ContextConsumer` - Context received from ancestors
- `ContextDefinitions` - Container for providers and consumers
- `ContextDependencyAnalyzer` - Analyzes dependencies for tree refinement

## Constraints

### Parse-Time Redistribution

`redistribute: true` generates virtual `ContextProvider` at deserialization, filtered during serialization:

```php
// JSON input (persisted to database)
"accepts_context": {
  "product": {"type": "single", "required": true, "redistribute": true}
}

// After deserialization (runtime)
// Virtual ContextProvider auto-generated for "product" key with broadcast strategy
// Element has BOTH consumer AND provider in runtime

// On serialization back to JSON (database write)
// Serializer uses getGeneratedProviderKey() to detect virtual provider
// Virtual provider filtered out, only "redistribute: true" persists
```

**Critical**: Virtual providers never persisted to database. Serializer in `ContentElementFieldSerializer` recreates them on every deserialization.

### Consumer Alias Validation

`consumerAlias` requires `redistribute: true`. Validation throws in `ContextConsumersFieldSerializer::deserializeContextConsumer()` if alias present without redistribution enabled.

### Property Alias Validation

Property alias constraints validated at serialization:
- No dots allowed: `ContextConsumersFieldSerializer::deserializeContextConsumer()` throws `propertyAliasWithDotNotation`
- Uniqueness per element: `ContentElementFieldSerializer::expandRedistributeFlags()` throws `propertyAliasCollision`

Applied in `ContextResolutionVisitor::setContextForConsumer()` after path resolution.

Independent of `redistribute` (unlike `consumerAlias`).
