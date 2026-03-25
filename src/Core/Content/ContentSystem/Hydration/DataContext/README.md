# DataContext

Context resolution and distribution. Distributes context data from provider elements to consumer descendants down the tree.

## Why Context System

Elements deep in tree may need data from ancestors without explicitly passing it. Provider exposes data as context (e.g., product detail element provides product). Consumer descendants receive context in properties (e.g., buy button consumes product ID).

Avoids tight coupling because intermediate elements don't need to know about context passing through them.

## Key Classes

- `DataContextResolver` - Entry point, orchestrates distribution
- `ContextResolutionVisitor` - Traverses tree, resolves context
- `ContextType` - Context type identifier

## Distribution Algorithm

Direct-children-only distribution:

```
Walk tree:
  On element.enter():
    If element provides context:
      Immediately distribute to direct children via strategy

  On element.leave():
    (No-op)
```

Context distributed ONLY to direct children. Deeper descendants do NOT receive context automatically. Multi-level context requires explicit re-providing: intermediate elements must both accept (`accepts_context`) and re-provide (`provides_context`) context for their children.

## Context Path Resolution

Consumers can request nested properties from context using dot notation (e.g., `product.cover`, `product.manufacturer.name`). The system automatically resolves these paths on Shopware Struct objects.

**How it works**:
1. Consumer declares context key with path: `"product.cover"` or `"product.manufacturer.name"`
2. System extracts base key (`product`) and path segments (`["cover"]` or `["manufacturer", "name"]`)
3. Looks up base key in direct distribution from parent
4. Uses `getVars()` on Struct objects to traverse path
5. Validates each segment exists and is Struct (for intermediate values)
6. Returns final value or throws exception if required

**Requirements**:
- Only works with Struct objects (all DAL entities extend Struct)
- Each intermediate value must be a Struct instance
- Missing properties or null intermediates handled based on `required` flag

**Error handling**:
- `required: true` - Throws `ContentSystemException::contextPathNotResolvable()` with details
- `required: false` - Returns null silently

**Implementation**: See `ContextPathResolver` class for path parsing and resolution logic.

### Property Alias

Path resolution uses full context key (e.g., `product.cover`), but resolved value stored using optional property alias.

Order: resolve path → apply alias → store in element properties.

## Virtual Providers from Redistribution

Redistribution shorthand (`redistribute: true`) auto-generates broadcast providers at parse-time.

## Distribution Strategies

Five strategies control how provider data splits across multiple consumers. Strategy selected by provider's distribution config.

All distribution configs support optional `consumer_alias` field. Null (default) uses provider's context key as consumer property name. Non-null value overrides this with alias for all direct children. Useful when provider key ('featuredProducts') differs from desired consumer property ('product').

### Broadcast

Same data to all consumers. Simple case where every consumer gets identical copy.

```php
// Provider has data: $product
// 3 consumers all receive: $product
```

Use for: Shared data that every consumer needs in full.

### Indexed

Position-based distribution. Consumer at index N gets data[N].

```php
// Provider has data: [$product1, $product2, $product3]
// Consumer[0] receives: $product1
// Consumer[1] receives: $product2
// Consumer[2] receives: $product3
// Consumer[3] receives: null (no data at index 3)
```

Fewer items than consumers → remaining consumers get null. More items than consumers → extra items ignored.

Use for: Fixed-position layouts ("first product here, second product there").

### Keyed

Consumer's `data_key` property matches keys in provider data array.

```php
// Provider has data: ["featured" => $prod1, "sale" => $prod2]
// Consumer with data_key="featured" receives: $prod1
// Consumer with data_key="sale" receives: $prod2
// Consumer with data_key="new" receives: null (key not in data)
```

Missing keys → consumer gets null.

Use for: Named data slots ("featured product", "sale product", "new arrivals").

### Sliced

Splits collection into chunks, each consumer gets a chunk. Useful for column layouts.

### Iterator

Cycles through consumers distributing items round-robin. Useful for even distribution across variable-count consumers.

## Subdirectory

None.
