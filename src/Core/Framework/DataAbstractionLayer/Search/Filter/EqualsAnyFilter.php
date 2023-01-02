<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final tag:v6.5.0
 */
#[Package('core')]
class EqualsAnyFilter extends SingleFieldFilter
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var array<string>|float[]|int[]
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
