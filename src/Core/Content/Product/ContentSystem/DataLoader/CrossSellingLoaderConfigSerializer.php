<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\ContentSystem\DataLoader;

use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-import-type CrossSellingLoaderConfigData from CrossSellingLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class CrossSellingLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public static function getSource(): string
    {
        return 'cross_selling';
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

        $associationOverride = null;
        if (\array_key_exists('associationOverride', $data)) {
            if (!\is_string($data['associationOverride']) || $data['associationOverride'] === '') {
                throw ProductException::invalidFieldValueType('associationOverride', 'non-empty string', \gettype($data['associationOverride']));
            }
            $associationOverride = $data['associationOverride'];
        }

        return new CrossSellingLoaderConfig($property, $associations, $associationOverride);
    }

    /**
     * @return CrossSellingLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof CrossSellingLoaderConfig) {
            throw ProductException::invalidFieldValueType('config', CrossSellingLoaderConfig::class, $config::class);
        }

        return $config->jsonSerialize();
    }
}
