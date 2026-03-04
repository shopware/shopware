# Distribution

Distribution strategy value objects. Each implements `DistributionConfig` with a `distribute()` method controlling how provider data splits across consumers.

## Key Classes

- `DistributionConfig` - Interface
- `DistributionStrategy` - Enum identifying strategy types
- `BroadcastDistributionConfig` - Same data to all consumers
- `IndexedDistributionConfig` - Position-based
- `KeyedDistributionConfig` - Key-based matching via `data_key`
- `SlicedDistributionConfig` - Data chunked across consumers
- `IteratorDistributionConfig` - Round-robin

See Hydration/DataContext/ for how strategies are invoked during context resolution.
