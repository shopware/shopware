<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class EqualsAnyFilter extends SingleFieldFilter
{
    /**
     * @param string[]|float[]|int[] $value
     */
    public function __construct(
        protected readonly string $field,
        protected array $value = []
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return float[]|int[]|string[]
     */
    public function getValue(): array
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
