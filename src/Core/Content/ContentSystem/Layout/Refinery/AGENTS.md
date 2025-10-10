# Refinery

@README.md

## Constraints

### Single-Pass Constraint (ABSOLUTE RULE)

**NO recursive placeholder resolution. If your refiner adds placeholders, it MUST resolve them in the same pass.**

### Refiner Chain Order

Priority determines execution order (lower number = earlier execution):

```
Priority   0: PlaceholderResolutionRefiner (core, runs first)
Priority 100: CustomRefiner1 (extension)
Priority 200: CustomRefiner2 (extension)
```

Chain characteristics:
- Sequential, not parallel
- Output → input between refiners
- Order matters for dependencies
- No backtracking or re-running

### DI Tag Registration

```php
#[AutoconfigureTag('content_system.layout_refiner', ['priority' => 100])]
class RouteOverrideRefiner implements LayoutRefinerInterface
{
    // See LayoutRefinerInterface for contract
}
```

### Built-in Refiner: PlaceholderResolutionRefiner

Runs at priority 0 (first). Calls `$layout->replacePlaceholders($resolvedData)`.

All extension refiners run AFTER placeholders resolved (unless negative priority).

## Quick Reference

- **CRITICAL**: Single-pass only, no recursive placeholder resolution
- **Chain**: Sequential, output → input between refiners
- **DI tag**: `content_system.layout_refiner` with priority
- **Built-in**: PlaceholderResolutionRefiner at priority 0
- **Performance**: Each refiner adds latency, optimize with early returns and caching
