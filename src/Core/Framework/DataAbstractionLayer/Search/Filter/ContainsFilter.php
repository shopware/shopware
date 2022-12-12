<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class ContainsFilter extends SingleFieldFilter
{
    public function __construct(private string $field, private mixed $value)
    {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
