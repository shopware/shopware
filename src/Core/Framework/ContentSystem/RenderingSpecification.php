<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
final readonly class RenderingSpecification
{
    /**
     * @param list<DataRequirement> $dataRequirements
     * @param list<string> $cacheTags
     * @param string|null $rootSource the id whose mapping catalogue applies to this layout, or null for a source
     *                                offering none; carried explicitly rather than inferred from a mapping path's
     *                                first segment, see `Adapter\AbstractSpecificationSource::rootSource()`
     */
    public function __construct(
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
        public Request $request,
        public ?string $targetElementId = null,
        public array $cacheTags = [],
        public ?string $rootSource = null,
    ) {
    }
}
