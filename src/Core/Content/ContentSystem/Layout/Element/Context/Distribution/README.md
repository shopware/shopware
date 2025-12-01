# Distribution

Distribution configuration value objects. Each config contains the distribution algorithm (`distribute()` method) and optional consumer alias.

## Key Classes

- `DistributionConfig` - Interface for distribution configurations
- `DistributionStrategy` - Enum identifying strategy types
- `BroadcastDistributionConfig` - Same data to all consumers
- `IndexedDistributionConfig` - Position-based distribution
- `KeyedDistributionConfig` - Key-based matching via `data_key` property
- `SlicedDistributionConfig` - Data chunked across consumers
- `IteratorDistributionConfig` - Round-robin distribution

## Value Object Pattern

Each config is a readonly value object containing:
- Configuration parameters (e.g., `keyProperty`, `sliceSize`, `consumerAlias`)
- `getStrategy()` - Returns the DistributionStrategy enum
- `getConsumerAlias()` - Optional consumer property name override
- `distribute(mixed $data, array $consumers)` - Core distribution algorithm

## Consumer Data Format

The `distribute()` method receives consumer elements as arrays with `component` and `properties` keys:

```php
$consumers = [
    ['component' => 'Sw:Product:Card', 'properties' => ['data_key' => 'featured']],
    ['component' => 'Sw:Product:Grid', 'properties' => ['data_key' => 'related']],
];
```

This allows strategies like `KeyedDistributionConfig` to read properties from consumers.
