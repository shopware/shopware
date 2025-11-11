@README.md

## Source Code References

- `LayoutScaffolderInterface` - Extension contract for scaffold/dismantle operations
- `ScaffoldingProcessor` - Orchestrates scaffolder execution in priority order
- `VirtualRootScaffolder` - Built-in scaffolder for page-level data distribution

## Constraints

### Symmetric Operations Requirement

`scaffold()` and `dismantle()` must be perfect inverses. Each scaffolder must reverse its own modifications exactly:

```php
public function scaffold(array $roots, RenderingSpecification $spec, SalesChannelContext $context): array;
public function dismantle(array $roots, RenderingSpecification $spec, SalesChannelContext $context): array;
```

**Critical:** `dismantle(scaffold($roots))` must return original `$roots` array. Asymmetric implementations corrupt layouts.

### Execution Order

Priority determines nesting order:
- `scaffold()`: Executes highest → lowest (1000, 500, 100)
- `dismantle()`: Executes lowest → highest (100, 500, 1000)

ScaffoldingProcessor pre-computes both forward and reverse arrays in constructor for performance.

### Array Mutation

Scaffolders receive `array<ContentElement>` and return modified `array<ContentElement>`. Root array structure can change (wrap, unwrap, filter). ContentElement objects within may be cloned or modified.

## Quick Reference

- **DI tag**: `content_system.layout_scaffolder` with `getPriority()` method
- **Priority range**: 0-1000 (higher = outer layer, executes first in scaffold, last in dismantle)
- **Built-in**: VirtualRootScaffolder at priority 100
- **Pipeline position**: Between Load and Refine (scaffold), Between Hydrate and Extract (dismantle)
- **Common mistake**: Asymmetric scaffold/dismantle operations
