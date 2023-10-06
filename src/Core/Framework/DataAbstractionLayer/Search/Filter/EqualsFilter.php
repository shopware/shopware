<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class EqualsFilter extends SingleFieldFilter
{
    public function __construct(
        protected readonly string $field,
        protected readonly string|bool|float|int|null $value
    ) {
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
