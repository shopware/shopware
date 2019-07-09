<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class CountResult extends AbstractAggregationResult
{
    /**
     * @var int
     */
    protected $count;

    public function __construct(?array $key, int $count)
    {
        parent::__construct($key);
        $this->count = $count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
