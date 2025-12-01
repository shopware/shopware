@README.md

## Source Code References

- `ContextProvider` - Context exposed to descendants
- `ContextConsumer` - Context received from ancestors
- `ContextDefinitions` - Container for providers and consumers
- `ContextDependencyAnalyzer` - Analyzes dependencies for tree pruning
- `Distribution/` - Distribution config value objects (DistributionConfig interface and 5 implementations)

## Constraints

### Runtime Redistribution Expansion

`redistribute: true` generates virtual `ContextProvider` at runtime via `RedistributeExpansionSubscriber`:

```php
// JSON input (persisted to database)
"accepts_context": {
  "product": {"type": "single", "required": true, "redistribute": true}
}

// After RedistributeExpansionSubscriber runs (priority 4000)
// Virtual ContextProvider auto-generated for "product" key with broadcast strategy
// Element has BOTH consumer AND provider at runtime
```

**Critical**: Virtual providers never persisted to database. `RedistributeExpansionSubscriber` generates them on every request during PreHydration phase.

### Consumer Alias Validation

`consumerAlias` requires `redistribute: true`. Validation throws in `ContextConsumersFieldSerializer::deserializeContextConsumer()` if alias present without redistribution enabled.

### Property Alias Validation

Property alias constraints validated at runtime during PreHydration:
- No dots allowed: `ContextConsumersFieldSerializer::deserializeContextConsumer()` throws `propertyAliasWithDotNotation`
- Uniqueness per element: `RedistributeExpansionSubscriber::validatePropertyAliases()` throws `propertyAliasCollision`

Applied in `ContextResolutionVisitor::setContextForConsumer()` after path resolution.

Independent of `redistribute` (unlike `consumerAlias`).
