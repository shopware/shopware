# Refinery

@README.md

## Constraints

### Single-Pass Constraint (ABSOLUTE RULE)

**NO recursive placeholder resolution. If your refiner adds placeholders, it MUST resolve them in the same pass.**

### Refiner Chain Order

Priority determines execution order (lower number = earlier execution):

```
Priority   0: PlaceholderResolutionRefiner (built-in)
Priority 200: PartialRenderingRefiner (built-in)
Priority 100+: Extension refiners
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
    public function refine(
        ContentElement $layout,
        ResolvedData $resolvedData,
        RenderingContext $renderingContext,
        SalesChannelContext $context
    ): ContentElement { /* ... */ }
}
```

### Built-in Refiners

PlaceholderResolutionRefiner (priority 0) and PartialRenderingRefiner (priority 200).

Extension refiners typically use priority 100+.

## Quick Reference

- **CRITICAL**: Single-pass only, no recursive placeholder resolution
- **Chain**: Sequential, output → input between refiners
- **DI tag**: `content_system.layout_refiner` with priority
- **Built-in**: PlaceholderResolutionRefiner at priority 0
- **Performance**: Each refiner adds latency, optimize with early returns and caching
