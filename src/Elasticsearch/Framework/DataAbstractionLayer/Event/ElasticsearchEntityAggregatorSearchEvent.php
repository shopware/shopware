<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event;

use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ElasticsearchEntityAggregatorSearchEvent extends Event implements ShopwareEvent
{
    private Search $search;

    private Context $context;

    private EntityDefinition $definition;

    private Criteria $criteria;

    public function __construct(
        Search $search,
        EntityDefinition $definition,
        Criteria $criteria,
        Context $context
    ) {
        $this->search = $search;
        $this->context = $context;
        $this->definition = $definition;
        $this->criteria = $criteria;
    }

    public function getSearch(): Search
    {
        return $this->search;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
