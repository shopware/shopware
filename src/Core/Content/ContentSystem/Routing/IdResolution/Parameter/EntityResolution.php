<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\IdResolution\Parameter;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class EntityResolution
{
    /**
     * @param array<string, mixed> $constraints
     */
    public function __construct(
        public string $entityType,
        public string $matchField = 'id',
        public array $constraints = []
    ) {
    }

    public function buildMatchFilter(mixed $value): EqualsFilter
    {
        return new EqualsFilter($this->matchField, $value);
    }

    /**
     * @return array<EqualsFilter|RangeFilter|MultiFilter>
     */
    public function buildConstraintFilters(): array
    {
        $filters = [];

        foreach ($this->constraints as $field => $constraint) {
            if (\is_array($constraint)) {
                $rangeFilters = [];
                foreach ($constraint as $operator => $value) {
                    $rangeFilters[] = new RangeFilter($field, [
                        $operator => $value,
                    ]);
                }
                $filters[] = new MultiFilter(MultiFilter::CONNECTION_AND, $rangeFilters);
            } else {
                $filters[] = new EqualsFilter($field, $constraint);
            }
        }

        return $filters;
    }

    public function hasConstraints(): bool
    {
        return !empty($this->constraints);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entityType: $data['entity'],
            matchField: $data['match_field'] ?? 'id',
            constraints: $data['constraints'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'entity' => $this->entityType,
            'match_field' => $this->matchField,
        ];

        if (!empty($this->constraints)) {
            $data['constraints'] = $this->constraints;
        }

        return $data;
    }
}
