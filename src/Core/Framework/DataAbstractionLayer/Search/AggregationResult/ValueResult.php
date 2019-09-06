<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class ValueResult extends AbstractAggregationResult
{
    /**
     * @var array
     */
    protected $values;

    public function __construct(?array $key, array $values)
    {
        parent::__construct($key);
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function add($value): void
    {
        $this->values[] = $value;
    }
}
