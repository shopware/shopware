<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type ProductConfiguratorLoaderConfigData array{productId?: non-empty-string}
 *
 * @internal
 */
#[Package('discovery')]
final readonly class ProductConfiguratorLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param non-empty-string|null $productId Element property containing the product ID
     */
    public function __construct(public ?string $productId = null)
    {
    }

    /**
     * @return ProductConfiguratorLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        return $this->productId === null ? [] : ['productId' => $this->productId];
    }
}
