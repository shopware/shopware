<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class MinAggregationResult extends AggregationResult
{
    /**
     * @var float|int|\DateTime|null
     */
    protected $min;

    public function __construct(Aggregation $aggregation, $min)
    {
        parent::__construct($aggregation);

        if (is_float($min)) {
            $this->min = (float) $min;
        } elseif (is_numeric($min)) {
            $this->min = (int) $min;
        } elseif (is_string($min)) {
            $this->min = new \DateTime($min);
        } else {
            $this->min = $min;
        }
    }

    /**
     * @return \DateTime|float|int|null
     */
    public function getMin()
    {
        return $this->min;
    }

    public function getResult(): array
    {
        return ['min' => $this->min];
    }
}
