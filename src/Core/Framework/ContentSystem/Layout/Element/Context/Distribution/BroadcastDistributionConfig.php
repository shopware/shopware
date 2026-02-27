<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type BroadcastDistributionConfigData array{
 *   distribution: 'broadcast',
 *   consumer_alias: string|null
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class BroadcastDistributionConfig implements DistributionConfig
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
        return DistributionStrategy::Broadcast;
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
        return array_fill(0, \count($consumers), $data);
    }

    /**
     * @return BroadcastDistributionConfigData
     */
    public function toArray(): array
    {
        return [
            'distribution' => 'broadcast',
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
