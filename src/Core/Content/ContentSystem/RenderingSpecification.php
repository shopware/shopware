<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Specification for content rendering pipeline.
 *
 * Specifies everything needed for layout refinement and rendering:
 * which layout to use, placeholder values, optional target element, page-level data requirements, and layout type.
 */
#[Package('discovery')]
final readonly class RenderingSpecification
{
    /**
     * @param array<DataRequirement> $dataRequirements
     * @param list<string> $cacheTags
     */
    public function __construct(
        public string $layoutId,
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
        public Request $request,
        public LayoutType $layoutType,
        public ?string $targetElementId = null,
        public array $cacheTags = [],
    ) {
    }
}
