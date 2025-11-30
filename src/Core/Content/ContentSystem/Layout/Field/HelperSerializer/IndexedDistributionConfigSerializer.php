<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\IndexedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type IndexedConfigData array{
 *   distribution: 'indexed',
 *   consumer_alias: string|null
 * }
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class IndexedDistributionConfigSerializer
{
    /**
     * Accepts loose array from dispatcher - performs runtime validation.
     *
     * @param array<string, mixed> $data
     */
    public function decode(array $data): IndexedDistributionConfig
    {
        return new IndexedDistributionConfig(
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    /**
     * @return IndexedConfigData
     */
    public function encode(IndexedDistributionConfig $config): array
    {
        return [
            'distribution' => 'indexed',
            'consumer_alias' => $config->consumerAlias,
        ];
    }
}
