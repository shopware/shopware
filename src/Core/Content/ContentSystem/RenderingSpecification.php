<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * Specification for content rendering pipeline.
 *
 * Specifies everything needed for layout refinement and rendering:
 * which layout to use, placeholder values, optional target element, and page-level data requirements.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class RenderingSpecification
{
    /**
     * @param array<Layout\Element\DataRequirement\DataRequirement> $dataRequirements
     */
    public function __construct(
        public string $layoutId,
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
        public ?string $targetElementId = null,
    ) {
    }
}
