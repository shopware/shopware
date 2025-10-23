<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Specification for content rendering pipeline.
 *
 * Specifies everything needed for layout refinement and rendering:
 * which layout to use, placeholder values, and optional target element.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class RenderingSpecification
{
    public function __construct(
        public string $layoutId,
        public PlaceholderValues $placeholderValues,
        public ?string $targetElementId = null,
    ) {
    }
}
