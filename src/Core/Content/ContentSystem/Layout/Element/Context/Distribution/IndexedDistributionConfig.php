<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type IndexedDistributionConfigData array{
 *   distribution: 'indexed',
 *   consumer_alias: string|null
 * }
 *
 * @internal
 */
#[Package('discovery')]
final readonly class IndexedDistributionConfig implements DistributionConfig
{
    private function __construct(
        public ?string $consumerAlias = null
    ) {
    }

    public static function simple(): self
    {
        return new self(null);
    }

    public static function aliased(string $alias): self
    {
        return new self($alias);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig
    {
        return new self(
            consumerAlias: isset($data['consumer_alias']) && \is_string($data['consumer_alias']) ? $data['consumer_alias'] : null
        );
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Indexed;
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

        $items = array_values($data);

        $result = [];
        foreach ($consumers as $index => $consumer) {
            $result[$index] = $items[$index] ?? null;
        }

        return array_values($result);
    }

    /**
     * @return IndexedDistributionConfigData
     */
    public function toArray(): array
    {
        return [
            'distribution' => 'indexed',
            'consumer_alias' => $this->consumerAlias,
        ];
    }

    public static function buildConstraints(): array
    {
        return [
            'consumer_alias' => [new Type('string')],
        ];
    }
}
