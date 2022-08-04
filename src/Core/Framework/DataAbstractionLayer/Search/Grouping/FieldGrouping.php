<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping;

use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;

/**
 * @final tag:v6.5.0
 */
class FieldGrouping implements CriteriaPartInterface
{
    /**
     * @var string
     */
    protected $field;

    public function __construct(string $field)
    {
        $this->field = $field;
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
