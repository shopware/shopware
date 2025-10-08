# DataContext

Context resolution and distribution. Distributes context data from provider elements to consumer descendants down the tree.

## Why Context System

Elements deep in tree may need data from ancestors without explicitly passing it. Provider exposes data as context (e.g., product detail element provides product). Consumer descendants receive context in properties (e.g., buy button consumes product ID).

Avoids tight coupling because intermediate elements don't need to know about context passing through them.

## Key Classes

- `DataContextResolver` - Entry point, orchestrates distribution
- `DistributionStrategy` - Determines which consumers receive which context
- `ContextResolutionVisitor` - Traverses tree, resolves context
- `DataContextStack` - Stack of active contexts during traversal
- `ContextType` - Context type identifier

## Distribution Algorithm

Stack-based scoping with immediate distribution:

```
Walk tree with context stack:
  On element.enter():
    If element provides context:
      1. Push context to stack
      2. Immediately distribute to direct children via strategy

    If element accepts context and property not set:
      Get context value from stack

  On element.leave():
    If element provides context:
      Pop context from stack
```

Inner providers shadow outer providers for same context key. Consumers without property value get from stack. Direct children of provider receive via immediate distribution, deeper descendants get from stack.

## Distribution Strategies

Five strategies control how provider data splits across multiple consumers. Strategy selected by provider's distribution config.

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

- Distribution/: DistributionStrategy implementations and config
