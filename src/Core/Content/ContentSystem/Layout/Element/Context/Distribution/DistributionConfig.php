<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @phpstan-import-type BroadcastDistributionConfigData from BroadcastDistributionConfig
 * @phpstan-import-type IndexedDistributionConfigData from IndexedDistributionConfig
 * @phpstan-import-type IteratorDistributionConfigData from IteratorDistributionConfig
 * @phpstan-import-type KeyedDistributionConfigData from KeyedDistributionConfig
 * @phpstan-import-type SlicedDistributionConfigData from SlicedDistributionConfig
 *
 * @phpstan-type DistributionConfigData BroadcastDistributionConfigData|IndexedDistributionConfigData|IteratorDistributionConfigData|KeyedDistributionConfigData|SlicedDistributionConfigData
 *
 * @internal
 */
#[Package('discovery')]
interface DistributionConfig
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig;

    public function getStrategy(): DistributionStrategy;

    public function getConsumerAlias(): ?string;

    /**
     * Distribute data to consumers according to this strategy's algorithm.
     *
     * @param array<int, array<string, mixed>> $consumers Consumer element data with 'component' and 'properties' keys
     *
     * @return array<int, mixed> Distributed data indexed by consumer position
     */
    public function distribute(mixed $data, array $consumers): array;

    /**
     * @return DistributionConfigData
     */
    public function toArray(): array;

    /**
     * Returns validation constraints for this distribution config's fields.
     *
     * @return array<string, list<Constraint>>
     */
    public static function buildConstraints(): array;
}
