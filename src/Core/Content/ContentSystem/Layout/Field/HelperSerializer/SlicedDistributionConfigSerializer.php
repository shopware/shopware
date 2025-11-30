<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\SlicedDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type SlicedConfigData array{
 *   distribution: 'sliced',
 *   slice_size: int,
 *   consumer_alias: string|null
 * }
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class SlicedDistributionConfigSerializer
{
    /**
     * Accepts loose array from dispatcher - performs runtime validation.
     *
     * @param array<string, mixed> $data
     */
    public function decode(array $data): SlicedDistributionConfig
    {
        return new SlicedDistributionConfig(
            sliceSize: isset($data['slice_size']) && \is_int($data['slice_size']) ? $data['slice_size'] : 10,
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    /**
     * @return SlicedConfigData
     */
    public function encode(SlicedDistributionConfig $config): array
    {
        return [
            'distribution' => 'sliced',
            'slice_size' => $config->sliceSize,
            'consumer_alias' => $config->consumerAlias,
        ];
    }
}
