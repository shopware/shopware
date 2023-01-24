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
    public function __construct(protected Criteria $criteria, protected Context $context)
    {
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
