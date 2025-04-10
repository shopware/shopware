<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class FieldSorting extends Struct implements CriteriaPartInterface
{
    public const ASCENDING = 'ASC';
    public const DESCENDING = 'DESC';

    public function __construct(
        protected string $field,
        protected string $direction = self::ASCENDING,
        protected bool $naturalSorting = false
    ) {
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getFields(): array
    {
        return [$this->field];
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function getNaturalSorting(): bool
    {
        return $this->naturalSorting;
    }

    public function getApiAlias(): string
    {
        return 'dal_field_sorting';
    }
}
