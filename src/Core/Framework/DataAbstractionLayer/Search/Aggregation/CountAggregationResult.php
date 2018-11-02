<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

class CountAggregationResult extends AggregationResult
{
    /**
     * @var int
     */
    protected $count;

    public function __construct(Aggregation $aggregation, int $count)
    {
        parent::__construct($aggregation);

        $this->count = $count;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getResult(): array
    {
        return ['count' => $this->count];
    }
}
