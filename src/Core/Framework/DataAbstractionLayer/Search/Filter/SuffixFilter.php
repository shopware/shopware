<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Filter;

use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class SuffixFilter extends SingleFieldFilter
{
    protected readonly string $value;

    public function __construct(
        protected readonly string $field,
        string|bool|float|int|null $value
    ) {
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
