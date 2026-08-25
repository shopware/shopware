<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ProductListingLoaderConfigData from ProductListingLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ProductListingLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'product_listing';
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

        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw ProductException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw ProductException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }

                $associations[] = $association;
            }
        }

        $aggregations = true;
        if (\array_key_exists('aggregations', $data) && $data['aggregations'] !== null) {
            if (!\is_bool($data['aggregations'])) {
                throw ProductException::invalidFieldValueType('aggregations', 'bool', \gettype($data['aggregations']));
            }
            $aggregations = $data['aggregations'];
        }

        return new ProductListingLoaderConfig($property, $associations, $aggregations);
    }

    /**
     * @return ProductListingLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductListingLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductListingLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
