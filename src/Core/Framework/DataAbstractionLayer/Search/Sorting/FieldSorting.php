<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Shopware\Core\Framework\Struct\Struct;

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
