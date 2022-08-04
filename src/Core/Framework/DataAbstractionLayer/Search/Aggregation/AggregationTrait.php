<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

/**
 * @deprecated tag:v6.5.0 will be removed, as it is not needed anymore
 */
trait AggregationTrait
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $name;

    public function getField(): string
    {
        return $this->field;
    }

    public function getFields(): array
    {
        return [$this->field];
    }

    public function getName(): string
    {
        return $this->name;
    }
}
