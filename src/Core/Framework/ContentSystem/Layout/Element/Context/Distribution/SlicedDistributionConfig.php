<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type SlicedDistributionConfigData array{
 *   distribution: 'sliced',
 *   sliceSize: int,
 *   consumerAlias: string|null
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class SlicedDistributionConfig implements DistributionConfig
{
    /**
     * @param positive-int $sliceSize
     */
    private function __construct(
        public int $sliceSize,
        public ?string $consumerAlias = null
    ) {
    }

    public static function withSliceSize(int $sliceSize, ?string $consumerAlias = null): self
    {
        if ($sliceSize < 1) {
            throw ContentSystemException::invalidFieldValueRange('sliceSize', 1, $sliceSize);
        }

        return new self($sliceSize, $consumerAlias);
    }

    /**
     * An absent field takes its default; a present one of the wrong type is rejected rather than replaced by
     * it, so a caller can tell a field it never set from a field it set wrongly. `sliceSize` is not nullable,
     * so a present `null` is one of those wrong types and is rejected like any other.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig
    {
        $sliceSize = \array_key_exists('sliceSize', $data) ? $data['sliceSize'] : 10;

        if (!\is_int($sliceSize)) {
            throw ContentSystemException::invalidFieldValueType('sliceSize', 'int', get_debug_type($sliceSize));
        }

        if ($sliceSize < 1) {
            throw ContentSystemException::invalidFieldValueRange('sliceSize', 1, $sliceSize);
        }

        $consumerAlias = $data['consumerAlias'] ?? null;

        if ($consumerAlias !== null && !\is_string($consumerAlias)) {
            throw ContentSystemException::invalidFieldValueType('consumerAlias', 'string', get_debug_type($consumerAlias));
        }

        return new self(sliceSize: $sliceSize, consumerAlias: $consumerAlias);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Sliced;
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
            return array_fill(0, \count($consumers), []);
        }

        $items = array_values($data);
        $slices = array_chunk($items, $this->sliceSize);

        $result = [];
        foreach ($consumers as $index => $consumer) {
            $result[$index] = $slices[$index] ?? [];
        }

        return array_values($result);
    }

    /**
     * @return SlicedDistributionConfigData
     */
    public function toArray(): array
    {
        return [
            'distribution' => 'sliced',
            'sliceSize' => $this->sliceSize,
            'consumerAlias' => $this->consumerAlias,
        ];
    }

    public static function buildConstraints(): array
    {
        return [
            'sliceSize' => [new NotBlank(), new Type('int'), new GreaterThanOrEqual(1)],
            'consumerAlias' => [new Type('string')],
        ];
    }
}
