<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class MaxAggregationResult extends AggregationResult
{
    /**
     * @var float|int|\DateTime|null
     */
    protected $max;

    public function __construct(Aggregation $aggregation, $max)
    {
        parent::__construct($aggregation);

        if (is_float($max)) {
            $this->max = (float) $max;
        } elseif (is_numeric($max)) {
            $this->max = (int) $max;
        } elseif (is_string($max)) {
            $this->max = new \DateTime($max);
        } else {
            $this->max = $max;
        }
    }

    /**
     * @return float|int|\DateTime|null
     */
    public function getMax()
    {
        return $this->max;
    }

    public function getResult(): array
    {
        return ['max' => $this->max];
    }
}
