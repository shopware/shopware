# Scaffolding

Pre/post-hydration layout transformation via symmetric wrap/unwrap operations. Enables temporary modifications to layout structure during rendering pipeline.

## Why Scaffolding

Layouts may need temporary structural modifications during rendering that must be reversed before response. Scaffolding provides controlled wrapping before refinement/hydration and guaranteed unwrapping after hydration. Used for distributing page-level context across multi-root layouts where standard context distribution is insufficient.

## Key Classes

- `LayoutScaffolderInterface` - Extension contract for scaffold/dismantle operations
- `ScaffoldingProcessor` - Orchestrates scaffolder execution in priority order
- `VirtualRootScaffolder` - Built-in scaffolder for page-level data distribution

## Scaffolder Contract

Scaffolders implement symmetric operations:

**scaffold()**: Wraps layouts before refinement. Executed highest priority first (1000 → 0).

**dismantle()**: Unwraps layouts after hydration. Executed lowest priority first (0 → 1000). Must reverse corresponding scaffold() modifications.

**getPriority()**: Returns 0-1000. Higher priority = outer wrapping layer.

Asymmetric scaffold/dismantle implementations corrupt layouts. Each scaffolder must perfectly reverse its own modifications.

## Priority System

Scaffolders execute in priority-determined nesting order. Higher priority scaffolders wrap outermost, lower priority wrap innermost.

```
scaffold():   Priority 1000 → 500 → 100 (outer to inner wrapping)
dismantle():  Priority 100 → 500 → 1000 (inner to outer unwrapping)
```

Built-in VirtualRootScaffolder uses priority 100. Custom scaffolders should use distinct priorities to control layer order.

## Extension Point

Implement LayoutScaffolderInterface, tag with `content_system.layout_scaffolder`:

```php
#[AutoconfigureTag('content_system.layout_scaffolder')]
class CustomScaffolder implements LayoutScaffolderInterface
{
    public function scaffold(array $roots, RenderingSpecification $spec, SalesChannelContext $context): array
    {
        // Wrap roots with additional structure
        return $wrappedRoots;
    }

    public function dismantle(array $roots, RenderingSpecification $spec, SalesChannelContext $context): array
    {
        // Unwrap to restore original structure
        return $originalRoots;
    }

    public function getPriority(): int
    {
        return 200; // Distinct priority
    }
}
```

Use cases: Global layout wrappers, preview mode scaffolding, A/B test variations, analytics injection.

## Pipeline Position

```
Load → **Scaffold** → Refine → Hydrate → **Dismantle** → Extract → Response
```

Scaffolding wraps before refinement, dismantles after hydration. Refiners and hydrators operate on scaffolded structure. Final output contains only original layout structure.
