<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class EntitySearchedEvent extends Event implements ShopwareEvent
{
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var EntityDefinition
     */
    private $definition;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Criteria $criteria, EntityDefinition $definition, Context $context)
    {
        $this->criteria = $criteria;
        $this->definition = $definition;
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
