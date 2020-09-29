<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ElasticsearchEntitySearcherBeforeSearchEvent extends Event implements ShopwareEvent
{
    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityDefinition
     */
    private $definition;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(
        EntityDefinition $definition,
        Criteria $criteria,
        Context $context
    ) {
        $this->context = $context;
        $this->definition = $definition;
        $this->criteria = $criteria;
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
