<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Search;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\Struct;

class AggregationResult extends Struct
{
    /**
     * @var array
     */
    protected $aggregations;

    /**
     * @var ShopContext
     */
    private $context;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(array $aggregations, ShopContext $context, Criteria $criteria)
    {
        $this->aggregations = $aggregations;
        $this->context = $context;
        $this->criteria = $criteria;
    }

    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
