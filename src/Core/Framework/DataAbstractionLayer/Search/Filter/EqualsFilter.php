<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 *
 * @package core
 */
class EqualsFilter extends SingleFieldFilter
{
    public function __construct(private readonly string $field, private readonly string|bool|float|int|null $value)
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
