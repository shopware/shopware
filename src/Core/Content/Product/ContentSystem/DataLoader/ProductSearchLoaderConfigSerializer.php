<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ProductSearchLoaderConfigData from ProductSearchLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class ProductSearchLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'product_search';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        $searchTermProperty = null;
        if (\array_key_exists('searchTermProperty', $data)) {
            if (!\is_string($data['searchTermProperty']) || $data['searchTermProperty'] === '') {
                throw ProductException::invalidFieldValueType('searchTermProperty', 'non-empty string', \gettype($data['searchTermProperty']));
            }
            $searchTermProperty = $data['searchTermProperty'];
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

        return new ProductSearchLoaderConfig($searchTermProperty, $associations);
    }

    /**
     * @return ProductSearchLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductSearchLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductSearchLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->searchTermProperty !== null) {
            $data['searchTermProperty'] = $config->searchTermProperty;
        }

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        return $data;
    }
}
