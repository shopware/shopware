# Listener

Event-driven pipeline transformations for the content hydration lifecycle. Listeners modify `$event->elements` (the only mutable property) before and after data loading.

## Execution Order

**PreHydration** (before data loading):
1. `VirtualRootPreparationSubscriber` (5000) — Wraps roots with temporary container for layout-level context
2. `RedistributeExpansionSubscriber` (4000) — Expands `redistribute: true` into broadcast providers
3. `PlaceholderResolutionSubscriber` (3000) — Resolves `{{variable}}` placeholders from specification
4. `PartialRenderingPreparationSubscriber` (1000) — Prunes tree when `targetElementId` specified

**PostHydration** (after data loading):
1. `VirtualRootCleanupSubscriber` (5000) — Removes virtual root wrapper
2. `PartialRenderingExtractionSubscriber` (1000) — Extracts target subtree for response

Higher priority numbers execute first.

## Priority Ranges

**Extensions:** `>= 6000` (before core), `< 1000` and `>= 0` (after core), `< 0` (absolute last)

**Core (RESERVED):** `>= 5000` (structure), `>= 3000` (transform), `>= 1000` (pruning)

## Subdirectories

- **PreHydration/** - Listeners that prepare layout structure before data loading
- **PostHydration/** - Listeners that finalize layout structure after data loading
