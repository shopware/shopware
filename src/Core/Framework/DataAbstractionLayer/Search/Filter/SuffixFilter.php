<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

/**
 * @final
 */
class SuffixFilter extends SingleFieldFilter
{
    private string $value;

    public function __construct(private string $field, string|bool|float|int|null $value)
    {
        $this->value = (string) $value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
