<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;

/**
 * @final
 */
class FieldGrouping implements CriteriaPartInterface
{
    public function __construct(private string $field)
    {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getFields(): array
    {
        return [$this->field];
    }
}
