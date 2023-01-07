<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;

/**
 * @package core
 */
class SalesChannelContextRestorerOrderCriteriaEvent extends NestedEvent
{
    protected Context $context;

    protected Criteria $criteria;

    public function __construct(Criteria $criteria, Context $context)
    {
        $this->context = $context;
        $this->criteria = $criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
