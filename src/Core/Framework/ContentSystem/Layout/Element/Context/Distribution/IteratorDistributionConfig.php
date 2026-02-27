<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type IteratorDistributionConfigData array{
 *   distribution: 'iterator',
 *   consumer_alias: string|null
 * }
 *
 * @internal
 */
#[Package('discovery')]
final readonly class IteratorDistributionConfig implements DistributionConfig
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

    /**
     * @codeCoverageIgnore
     */
    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Iterator;
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
            return [];
        }

        return array_values($data);
    }

    /**
     * @return IteratorDistributionConfigData
     */
    public function toArray(): array
    {
        return [
            'distribution' => 'iterator',
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
