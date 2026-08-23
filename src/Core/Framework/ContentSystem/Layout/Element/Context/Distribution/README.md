# Distribution

Distribution strategy value objects. Each implements `DistributionConfig` with a `distribute()` method controlling how provider data splits across consumers.

## Key Classes

- `DistributionConfig` - Interface
- `DistributionStrategy` - Enum identifying strategy types
- `BroadcastDistributionConfig` - Same data to all consumers
- `IndexedDistributionConfig` - Position-based
- `KeyedDistributionConfig` - Key-based matching via `data_key`
- `SlicedDistributionConfig` - Data chunked across consumers
- `IteratorDistributionConfig` - Sequential by position, consumer count ignored: it returns the provider's values in order, so a consumer past the last value receives nothing at all, where `IndexedDistributionConfig` pads with null and delivers to every consumer

See Rendering/ for how strategies are invoked during context resolution.

For the `distribution` values these objects back, see [../docs/distribution-strategies.md](../docs/distribution-strategies.md).
