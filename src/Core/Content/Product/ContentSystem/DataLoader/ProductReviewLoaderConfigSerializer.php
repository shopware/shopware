<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type ProductReviewLoaderConfigData from ProductReviewLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('after-sales')]
class ProductReviewLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'product_review';
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

        return new ProductReviewLoaderConfig($property, $associations);
    }

    /**
     * @return ProductReviewLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ProductReviewLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', ProductReviewLoaderConfig::class, $config::class);
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
