<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Search\Sorting;

use Shopware\Framework\ORM\Search\CriteriaPartInterface;
use Shopware\Framework\Struct\Struct;

class FieldSorting extends Struct implements CriteriaPartInterface
{
    public const ASCENDING = 'ASC';
    public const DESCENDING = 'DESC';

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
