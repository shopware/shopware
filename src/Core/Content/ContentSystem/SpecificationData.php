<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem;

use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * Bundles data requirements and placeholder values since both are
 * produced from the same layout assignment resolution.
 *
 * @internal
 */
#[Package('discovery')]
final readonly class SpecificationData
{
    /**
     * @codeCoverageIgnore
     *
     * @param list<DataRequirement> $dataRequirements
     */
    public function __construct(
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
    ) {
    }
}
