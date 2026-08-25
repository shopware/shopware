<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ProductListingAggregationsLoaderConfigData from ProductListingAggregationsLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ProductListingAggregationsLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return ProductListingAggregationsDataLoader::SOURCE;
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $property = null;

        if (\array_key_exists('property', $data)) {
            if (!\is_string($data['property']) || $data['property'] === '') {
                throw ProductException::invalidFieldValueType('property', 'non-empty string', \gettype($data['property']));
            }

            $property = $data['property'];
        }

        return new ProductListingAggregationsLoaderConfig($property);
    }

    /**
     * @return ProductListingAggregationsLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductListingAggregationsLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductListingAggregationsLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
