# DataContext

@README.md

## Coding Patterns

### Direct-Children-Only Distribution

Context distributed only to immediate children. Deeper descendants require explicit re-providing. See `ContextResolutionVisitor::enter()` and `distributeContextToChildren()` for implementation.

### Context Path Resolution

Consumers can use dot notation in context keys to access nested properties: `product.cover`, `product.manufacturer.name`, etc.

**Key implementation points**:
- `ContextPathResolver->parseContextKey()` splits keys into base + path segments
- `ContextPathResolver->resolvePath()` traverses Struct objects using `getVars()`
- Only works with Struct instances (all DAL entities)
- Path resolution happens in `ContextResolutionVisitor::setContextForConsumer()`
- Direct distribution lookups use base key, then resolve path on retrieved data
- Throws exception if `required: true` and path fails
- Property alias applied after path resolution: storage key differs from resolution key (alias in `setContextForConsumer()`)

**Usage**:
```php
// Consumer declares path in context key
"accepts_context": {
  "product.cover": {"type": "single", "required": true}
}

// System resolves automatically:
// 1. Finds "product" in direct distribution from parent
// 2. Calls getVars() on product entity
// 3. Returns $vars['cover']
```

## Quick Reference

- **Single mechanism**: Direct distribution to immediate children only
- **Re-providing required**: Multi-level context needs explicit `accepts_context` + `provides_context` at each level
- **Strategy scope**: Only applies to direct children
- **Strategies**: Broadcast (shared), Indexed (position), Keyed (named), Sliced (chunks), Iterator (round-robin)
- **Path resolution**: Use dot notation in context keys (`product.cover`) to access nested Struct properties
- **Path requirements**: Only Struct objects, `getVars()` used for traversal, arbitrary depth supported
- **Error handling**: `required: true` throws exception, `required: false` returns null
- **Implementation**: See ContextResolutionVisitor for visitor pattern, ContextPathResolver for path logic
- **Value objects**: Each DistributionConfig subclass implements `distribute()` directly (located in `Layout/Element/Context/Distribution/`)
- **Redistribution shorthand**: `redistribute: true` in consumer auto-generates broadcast provider (runtime, see `EventSubscriber/PreHydration/RedistributeExpansionSubscriber`)
