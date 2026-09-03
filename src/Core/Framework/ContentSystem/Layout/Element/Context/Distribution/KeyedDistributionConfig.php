<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type KeyedDistributionConfigData array{
 *   distribution: 'keyed',
 *   keyProperty: string,
 *   consumerAlias: string|null
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class KeyedDistributionConfig implements DistributionConfig
{
    private function __construct(
        public string $keyProperty = 'data_key',
        public ?string $consumerAlias = null
    ) {
    }

    public static function simple(): self
    {
        return new self('data_key', null);
    }

    /**
     * An absent field takes its default; a present one of the wrong type is rejected rather than replaced by
     * it, so a caller can tell a field it never set from a field it set wrongly. `keyProperty` is not
     * nullable, so a present `null` is one of those wrong types and is rejected like any other.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig
    {
        $keyProperty = \array_key_exists('keyProperty', $data) ? $data['keyProperty'] : 'data_key';

        if (!\is_string($keyProperty)) {
            throw ContentSystemException::invalidFieldValueType('keyProperty', 'string', get_debug_type($keyProperty));
        }

        $consumerAlias = $data['consumerAlias'] ?? null;

        if ($consumerAlias !== null && !\is_string($consumerAlias)) {
            throw ContentSystemException::invalidFieldValueType('consumerAlias', 'string', get_debug_type($consumerAlias));
        }

        return new self(keyProperty: $keyProperty, consumerAlias: $consumerAlias);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Keyed;
    }

    /**
     * @codeCoverageIgnore
     */
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

        return array_values($result);
    }

    /**
     * @return KeyedDistributionConfigData
     */
    public function toArray(): array
    {
        return [
            'distribution' => 'keyed',
            'keyProperty' => $this->keyProperty,
            'consumerAlias' => $this->consumerAlias,
        ];
    }

    public static function buildConstraints(): array
    {
        return [
            'keyProperty' => [new NotBlank(), new Type('string')],
            'consumerAlias' => [new Type('string')],
        ];
    }
}
