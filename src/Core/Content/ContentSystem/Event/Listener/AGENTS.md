# Listener

@README.md

## Source Code References

- **PreHydration listeners**: `PreHydration/VirtualRootPreparationSubscriber`, `PreHydration/RedistributeExpansionSubscriber`, `PreHydration/PlaceholderResolutionSubscriber`, `PreHydration/PartialRenderingPreparationSubscriber`
- **PostHydration listeners**: `PostHydration/VirtualRootCleanupSubscriber`, `PostHydration/PartialRenderingExtractionSubscriber`
- **Business logic services**: `Layout/Scaffolding/VirtualRootWrapper`, `Output/PartialRenderer`

## Constraints

### Single-Pass Placeholder Resolution

Placeholders are resolved once during `PlaceholderResolutionSubscriber` execution. Listeners adding new placeholders MUST resolve them in the same event dispatch cycle. The system will NOT re-run placeholder resolution.

## Quick Reference

- **Extension**: Add listeners via `#[AsEventListener]` attribute with event and priority
- **Execution**: Higher priority numbers execute first
- **Mutation**: Only `$event->elements` array is mutable
