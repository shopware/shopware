<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;
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
    private function __construct(
        public int $sliceSize,
        public ?string $consumerAlias = null
    ) {
    }

    public static function withSliceSize(int $sliceSize, ?string $consumerAlias = null): self
    {
        return new self($sliceSize, $consumerAlias);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig
    {
        return new self(
            sliceSize: isset($data['sliceSize']) && \is_int($data['sliceSize']) ? $data['sliceSize'] : 10,
            consumerAlias: isset($data['consumerAlias']) && \is_string($data['consumerAlias']) ? $data['consumerAlias'] : null
        );
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

        $sliceSize = $this->sliceSize;
        if ($sliceSize < 1) {
            $sliceSize = 1;
        }

        $items = array_values($data);
        $slices = array_chunk($items, $sliceSize);

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
            'sliceSize' => [new NotBlank(), new Type('int')],
            'consumerAlias' => [new Type('string')],
        ];
    }
}
