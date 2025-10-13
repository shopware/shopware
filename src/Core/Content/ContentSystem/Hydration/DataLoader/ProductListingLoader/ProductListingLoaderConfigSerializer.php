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
        $limit = null;
        if (isset($data['limit'])) {
            if (!\is_int($data['limit']) || $data['limit'] <= 0) {
                throw ContentSystemException::invalidFieldValueType('limit', 'positive integer', \gettype($data['limit']));
            }
            $limit = $data['limit'];
        }

        $associations = [];
        if (isset($data['associations'])) {
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

        return new ProductListingLoaderConfig($limit, $associations);
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

        if ($config->limit !== null) {
            $data['limit'] = $config->limit;
        }

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        return $data;
    }
}
