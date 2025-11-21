<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ProductListingLoader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigInterface;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderConfigSerializerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ProductListingLoaderConfigData from ProductListingLoaderConfig
 *
 * @internal
 */
#[Package('discovery')]
class ProductListingLoaderConfigSerializer implements ContentDataLoaderConfigSerializerInterface
{
    public static function getSource(): string
    {
        return 'product_listing';
    }

    public function decode(array $data): ContentDataLoaderConfigInterface
    {
        $property = null;
        if (\array_key_exists('property', $data)) {
            if (!\is_string($data['property']) || $data['property'] === '') {
                throw ContentSystemException::invalidFieldValueType('property', 'non-empty string', \gettype($data['property']));
            }
            $property = $data['property'];
        }

        $associations = [];
        if (\array_key_exists('associations', $data) && $data['associations'] !== null) {
            if (!\is_array($data['associations'])) {
                throw ContentSystemException::invalidFieldValueType('associations', 'array', \gettype($data['associations']));
            }
            foreach ($data['associations'] as $i => $association) {
                if (!\is_string($association) || $association === '') {
                    throw ContentSystemException::invalidFieldValueType('associations.' . $i, 'non-empty string', \gettype($association));
                }

                $associations[] = $association;
            }
        }

        return new ProductListingLoaderConfig($property, $associations);
    }

    /**
     * @return ProductListingLoaderConfigData
     */
    public function encode(ContentDataLoaderConfigInterface $config): array
    {
        if (!$config instanceof ProductListingLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', ProductListingLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->property !== null) {
            $data['property'] = $config->property;
        }

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        return $data;
    }
}
