<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class EqualsAnyFilter extends SingleFieldFilter
{
    /**
     * @param string[]|float[]|int[] $value
     */
    public function __construct(private string $field, private array $value = [])
    {
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
