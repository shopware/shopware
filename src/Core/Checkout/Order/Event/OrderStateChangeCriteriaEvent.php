<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('customer-order')]
class OrderStateChangeCriteriaEvent extends Event
{
    /**
     * @var string
     */
    private $orderId;

    /**
     * @var Criteria
     */
    private $criteria;

    public function __construct(string $orderId, Criteria $criteria)
    {
        $this->orderId = $orderId;
        $this->criteria = $criteria;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
