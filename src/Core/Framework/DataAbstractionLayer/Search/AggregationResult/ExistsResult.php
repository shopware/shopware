<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class ExistsResult extends AbstractAggregationResult
{
    /**
     * @var bool
     */
    protected $exists;

    public function __construct(?array $key, bool $exists)
    {
        parent::__construct($key);
        $this->exists = $exists;
    }

    public function getExists(): bool
    {
        return $this->exists;
    }
}
