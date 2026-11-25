<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductListingLoaderConfigData array{
 *   property?: non-empty-string,
 *   associations?: list<non-empty-string>,
 *   associationOverride?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class ProductListingLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read navigation ID from
     * @param list<non-empty-string> $associations
     * @param non-empty-string|null $associationOverride Element property name to read additional associations from
     */
    public function __construct(
        public ?string $property = null,
        public array $associations = [],
        public ?string $associationOverride = null
    ) {
    }

    /**
     * @return ProductListingLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->property !== null) {
            $data['property'] = $this->property;
        }

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        if ($this->associationOverride !== null) {
            $data['associationOverride'] = $this->associationOverride;
        }

        return $data;
    }
}
