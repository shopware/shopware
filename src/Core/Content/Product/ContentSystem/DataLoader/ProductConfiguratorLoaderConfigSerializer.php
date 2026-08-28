<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductConfiguratorLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return ProductConfiguratorDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        if (!\array_key_exists('productProperty', $data)) {
            return new ProductConfiguratorLoaderConfig();
        }

        if (!\is_string($data['productProperty']) || $data['productProperty'] === '') {
            throw ProductException::invalidFieldValueType('productProperty', 'non-empty string', \gettype($data['productProperty']));
        }

        return new ProductConfiguratorLoaderConfig($data['productProperty']);
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductConfiguratorLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductConfiguratorLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
