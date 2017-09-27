<?php

namespace Shopware\Search\Sorting;

use Shopware\Search\CriteriaPartInterface;

class FieldSorting implements CriteriaPartInterface
{
    const ASCENDING = 'ASC';
    const DESCENDING = 'DESC';

    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $direction;

    public function __construct(string $field, string $direction = self::ASCENDING)
    {
        $this->field = $field;
        $this->direction = $direction;
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
}
