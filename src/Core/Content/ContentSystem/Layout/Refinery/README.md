# Refinery

Layout refinement via sequential refiners. Transforms ContentElement tree with resolved data before hydration.

## Critical Constraint

**Single-pass only.** Recursive placeholder resolution not supported. Extension refiners adding placeholders must resolve to final values immediately. If refiner adds `{{newPlaceholder}}`, it must also resolve it in same pass. System won't re-process.

## Why Single-Pass

Performance. Multiple passes would require repeated tree traversal. Layout trees can be deep. Single-pass constraint forces extension authors to resolve dependencies upfront.

## Key Classes

- `LayoutRefinery` - Orchestrates refinement, calls refiners sequentially
- `LayoutRefinerInterface` - Refiner contract
- `RefinedLayout` - Output containing refined element tree + metadata
- `RefinedLayoutBuilder` - Builds RefinedLayout from ContentLayoutEntity

## Refiner Chain

LayoutRefinery iterates refiners (DI tagged services), calls `refine()` on each. Refiners receive:
- `ContentElement $layout` - Element tree (may be modified)
- `RenderingSpecification $specification` - Rendering specification (layout ID, placeholders, target element)
- `SalesChannelContext $salesChannelContext` - Sales channel context

Each refiner returns modified ContentElement. Output becomes input for next refiner. Order matters - refiners run in DI priority order.

## Built-in Refiners

Two built-in refiners:

**PlaceholderResolutionRefiner** (priority 0): Calls `ContentElement::replacePlaceholders(RenderingSpecification)` recursively.

**PartialRenderingRefiner** (priority 200): Pre-hydration tree pruning when `?elementId` parameter present. Keeps only path to target element and its descendants.

All other refiners are extension-provided.

## Priority System

DI tag priority determines execution order. Lower priority number runs earlier:

```
Priority 0:   PlaceholderResolutionRefiner (built-in)
Priority 100: CustomRefiner1 (extension)
Priority 200: CustomRefiner2 (extension)
```

Typical extension priority: 100+. Priority 0 reserved for core. Negative priorities run before placeholder resolution (use with caution - placeholders won't be resolved yet).

## Extension Pattern

Implement LayoutRefinerInterface, tag with `content_system.layout_refiner`:

```php
namespace App\Content\Refiner;

use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\LayoutRefinerInterface;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('content_system.layout_refiner', ['priority' => 100])]
class CustomRefiner implements LayoutRefinerInterface
{
    public function refine(
        ContentElement $layout,
        RenderingSpecification $specification,
        SalesChannelContext $salesChannelContext
    ): ContentElement {
        // Access placeholder values
        $placeholders = $specification->placeholderValues->all();

        // Example: Modify layout based on placeholders
        if (isset($placeholders['productId'])) {
            $this->applyProductCustomizations($layout, $placeholders['productId']);
        }

        return $layout;
    }

    private function applyProductCustomizations(ContentElement $element, string $productId): void
    {
        // Custom logic here
        foreach ($element->allSlotElements() as $child) {
            $this->applyProductCustomizations($child, $productId);
        }
    }
}
```

Common use cases:
- Route-specific overrides (shown above)
- Calculated properties (compute values from resolved data)
- Conditional transformations (add/remove elements based on context)
- A/B testing (modify layout based on customer segment)

Don't add placeholders unless you resolve them. System won't re-run placeholder resolution.

## Subdirectory

- Refiner/: Concrete refiner implementations
