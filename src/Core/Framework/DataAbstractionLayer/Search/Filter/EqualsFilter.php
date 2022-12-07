<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class EqualsFilter extends SingleFieldFilter
{
    public function __construct(private string $field, private string|bool|float|int|null $value)
    {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): string|bool|float|int|null
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
