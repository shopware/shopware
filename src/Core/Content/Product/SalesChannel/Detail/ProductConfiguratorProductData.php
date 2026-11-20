<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
final readonly class ProductConfiguratorProductData
{
    /**
     * @param array<string>|null $optionIds
     */
    public function __construct(
        public string $id,
        public ?string $parentId,
        public ?array $optionIds,
        public ?VariantListingConfig $variantListingConfig,
    ) {
    }
}
