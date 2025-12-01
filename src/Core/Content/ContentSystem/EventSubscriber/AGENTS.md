# EventSubscriber

@README.md

## Source Code References

- **PreHydration subscribers**: `PreHydration/VirtualRootPreparationSubscriber`, `PreHydration/RedistributeExpansionSubscriber`, `PreHydration/PlaceholderResolutionSubscriber`, `PreHydration/PartialRenderingPreparationSubscriber`
- **PostHydration subscribers**: `PostHydration/VirtualRootCleanupSubscriber`, `PostHydration/PartialRenderingExtractionSubscriber`
- **Business logic services**: `Layout/Scaffolding/VirtualRootWrapper`, `Output/PartialRenderer`

## Constraints

### Single-Pass Placeholder Resolution

Placeholders are resolved once during `PlaceholderResolutionSubscriber` execution. Subscribers adding new placeholders MUST resolve them in the same event dispatch cycle. The system will NOT re-run placeholder resolution.

## Quick Reference

- **Extension**: Add subscribers via `EventSubscriberInterface`, tag with DI
- **Execution**: Higher priority numbers execute first
- **Mutation**: Only `$event->elements` array is mutable
