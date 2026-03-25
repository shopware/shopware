<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ShippingMethodLoader;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @phpstan-import-type ShippingMethodLoaderConfigData from ShippingMethodLoaderConfig
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ShippingMethodLoaderConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function getDecorated(): AbstractContentDataLoaderConfigSerializer
    {
        throw new DecorationPatternException(self::class);
    }

    public static function getSource(): string
    {
        return 'shipping_method';
    }

    public function decode(array $data): AbstractContentDataLoaderConfig
    {
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

        $onlyAvailable = true;
        if (\array_key_exists('onlyAvailable', $data)) {
            if (!\is_bool($data['onlyAvailable'])) {
                throw ContentSystemException::invalidFieldValueType('onlyAvailable', 'bool', \gettype($data['onlyAvailable']));
            }
            $onlyAvailable = $data['onlyAvailable'];
        }

        return new ShippingMethodLoaderConfig($associations, $onlyAvailable);
    }

    /**
     * @return ShippingMethodLoaderConfigData
     */
    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        if (!$config instanceof ShippingMethodLoaderConfig) {
            throw ContentSystemException::invalidFieldValueType('config', ShippingMethodLoaderConfig::class, $config::class);
        }

        $data = [];

        if ($config->associations !== []) {
            $data['associations'] = $config->associations;
        }

        if ($config->onlyAvailable !== true) {
            $data['onlyAvailable'] = $config->onlyAvailable;
        }

        return $data;
    }
}
