<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\Event;

class DocumentOrderCriteriaEvent extends Event
{
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var Context
     */
    private $context;

    public function __construct(Criteria $criteria, Context $context)
    {
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
