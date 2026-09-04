<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @phpstan-type IndexedDistributionConfigData array{
 *   distribution: 'indexed',
 *   consumerAlias: string|null
 * }
 *
 * @internal
 */
#[Package('framework')]
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
     * An absent (or null) `consumerAlias` takes the default; a present one of the wrong type is rejected
     * rather than replaced by it, so a caller can tell a field it never set from a field it set wrongly.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): DistributionConfig
    {
        $consumerAlias = $data['consumerAlias'] ?? null;

        if ($consumerAlias !== null && !\is_string($consumerAlias)) {
            throw ContentSystemException::invalidFieldValueType('consumerAlias', 'string', get_debug_type($consumerAlias));
        }

        return new self(consumerAlias: $consumerAlias);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Indexed;
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
            'consumerAlias' => $this->consumerAlias,
        ];
    }

    public static function buildConstraints(): array
    {
        return [
            'consumerAlias' => [new Type('string')],
        ];
    }
}
