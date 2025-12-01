<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type KeyedDistributionConfigData array{
 *   distribution: 'keyed',
 *   key_property: string,
 *   consumer_alias: string|null
 * }
 *
 * @internal
 */
#[Package('discovery')]
final readonly class KeyedDistributionConfig implements DistributionConfig
{
    public function __construct(
        public string $keyProperty = 'data_key',
        public ?string $consumerAlias = null
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig
    {
        return new self(
            keyProperty: isset($data['key_property']) && \is_string($data['key_property']) ? $data['key_property'] : 'data_key',
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Keyed;
    }

    public function getConsumerAlias(): ?string
    {
        return $this->consumerAlias;
    }

    public function distribute(mixed $data, array $consumers): array
    {
        if (!\is_array($data)) {
            return array_fill(0, \count($consumers), null);
        }

        $result = [];
        foreach ($consumers as $index => $consumer) {
            if (!\is_array($consumer)) {
                $result[$index] = null;

                continue;
            }

            $properties = $consumer['properties'] ?? [];
            if (!\is_array($properties)) {
                $result[$index] = null;

                continue;
            }

            $dataKey = $properties[$this->keyProperty] ?? null;

            if (!\is_string($dataKey) && !\is_int($dataKey)) {
                $result[$index] = null;

                continue;
            }

            $result[$index] = $data[$dataKey] ?? null;
        }

        return $result;
    }

    /**
     * @return KeyedDistributionConfigData
     */
    public function toArray(): array
    {
        return [
            'distribution' => 'keyed',
            'key_property' => $this->keyProperty,
            'consumer_alias' => $this->consumerAlias,
        ];
    }
}
