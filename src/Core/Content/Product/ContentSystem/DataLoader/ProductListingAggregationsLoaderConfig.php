<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductListingAggregationsLoaderConfigData array{
 *   property?: non-empty-string
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class ProductListingAggregationsLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $property Element property name to read navigation ID from
     */
    public function __construct(public ?string $property = null)
    {
    }

    /**
     * @return ProductListingAggregationsLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        return $this->property === null ? [] : ['property' => $this->property];
    }
}
