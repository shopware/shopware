<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductConfiguratorLoaderConfigData array{productProperty?: non-empty-string}
 *
 * @internal
 */
#[Package('discovery')]
final readonly class ProductConfiguratorLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $productProperty Element property containing the product
     */
    public function __construct(public ?string $productProperty = null)
    {
    }

    /**
     * @return ProductConfiguratorLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        return $this->productProperty === null ? [] : ['productProperty' => $this->productProperty];
    }
}
