<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ProductSuggestLoaderConfigData from ProductSuggestLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ProductSuggestLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'product_suggest';
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

        return new ProductSuggestLoaderConfig($searchTermProperty, $associations);
    }

    /**
     * @return ProductSuggestLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductSuggestLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductSuggestLoaderConfig::class, $config::class);
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
