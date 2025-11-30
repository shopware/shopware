<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Field\HelperSerializer;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\BroadcastDistributionConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type BroadcastConfigData array{
 *   distribution: 'broadcast',
 *   consumer_alias: string|null
 * }
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class BroadcastDistributionConfigSerializer
{
    /**
     * Accepts loose array from dispatcher - performs runtime validation.
     *
     * @param array<string, mixed> $data
     */
    public function decode(array $data): BroadcastDistributionConfig
    {
        return new BroadcastDistributionConfig(
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    /**
     * @return BroadcastConfigData
     */
    public function encode(BroadcastDistributionConfig $config): array
    {
        return [
            'distribution' => 'broadcast',
            'consumer_alias' => $config->consumerAlias,
        ];
    }
}
