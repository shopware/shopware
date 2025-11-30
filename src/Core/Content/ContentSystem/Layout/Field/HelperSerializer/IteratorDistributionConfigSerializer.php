<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\IteratorDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type IteratorConfigData array{
 *   distribution: 'iterator',
 *   consumer_alias: string|null
 * }
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class IteratorDistributionConfigSerializer
{
    /**
     * Accepts loose array from dispatcher - performs runtime validation.
     *
     * @param array<string, mixed> $data
     */
    public function decode(array $data): IteratorDistributionConfig
    {
        return new IteratorDistributionConfig(
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    /**
     * @return IteratorConfigData
     */
    public function encode(IteratorDistributionConfig $config): array
    {
        return [
            'distribution' => 'iterator',
            'consumer_alias' => $config->consumerAlias,
        ];
    }
}
