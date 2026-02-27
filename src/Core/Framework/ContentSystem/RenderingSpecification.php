<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
final readonly class RenderingSpecification
{
    /**
     * @param list<DataRequirement> $dataRequirements
     * @param list<string> $cacheTags
     */
    public function __construct(
        public string $layoutId,
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
        public Request $request,
        public ?string $targetElementId = null,
        public array $cacheTags = [],
    ) {
    }
}
