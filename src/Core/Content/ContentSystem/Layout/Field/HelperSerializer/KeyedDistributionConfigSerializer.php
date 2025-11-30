<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\KeyedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type KeyedConfigData array{
 *   distribution: 'keyed',
 *   key_property: string,
 *   consumer_alias: string|null
 * }
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class KeyedDistributionConfigSerializer
{
    /**
     * Accepts loose array from dispatcher - performs runtime validation.
     *
     * @param array<string, mixed> $data
     */
    public function decode(array $data): KeyedDistributionConfig
    {
        return new KeyedDistributionConfig(
            keyProperty: isset($data['key_property']) && \is_string($data['key_property']) ? $data['key_property'] : 'data_key',
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    /**
     * @return KeyedConfigData
     */
    public function encode(KeyedDistributionConfig $config): array
    {
        return [
            'distribution' => 'keyed',
            'key_property' => $config->keyProperty,
            'consumer_alias' => $config->consumerAlias,
        ];
    }
}
