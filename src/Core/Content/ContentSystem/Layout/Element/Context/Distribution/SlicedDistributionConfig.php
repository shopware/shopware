<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type SlicedDistributionConfigData array{
 *   distribution: 'sliced',
 *   slice_size: int,
 *   consumer_alias: string|null
 * }
 *
 * @internal
 */
#[Package('discovery')]
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
            sliceSize: isset($data['slice_size']) && \is_int($data['slice_size']) ? $data['slice_size'] : 10,
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Sliced;
    }

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
            'slice_size' => $this->sliceSize,
            'consumer_alias' => $this->consumerAlias,
        ];
    }

    public static function buildConstraints(): array
    {
        return [
            'slice_size' => [new NotBlank(), new Type('int')],
            'consumer_alias' => [new Type('string')],
        ];
    }
}
