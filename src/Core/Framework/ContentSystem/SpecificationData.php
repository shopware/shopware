<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * Bundles data requirements and placeholder values since both are
 * produced from the same layout assignment resolution.
 *
 * @internal
 */
#[Package('framework')]
final readonly class SpecificationData
{
    /**
     * @param list<DataRequirement> $dataRequirements
     */
    public function __construct(
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
    ) {
    }
}
