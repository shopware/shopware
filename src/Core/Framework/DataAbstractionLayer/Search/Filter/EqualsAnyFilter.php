<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

class EqualsAnyFilter extends Filter
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string[]|float[]|int[]
     */
    protected $value = [];

    public function __construct(string $field, array $value = [])
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
