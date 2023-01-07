<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package customer-order
 */
class OrderPaymentMethodChangedCriteriaEvent extends Event
{
    private string $orderId;

    private Criteria $criteria;

    private SalesChannelContext $context;

    public function __construct(string $orderId, Criteria $criteria, SalesChannelContext $context)
    {
        $this->orderId = $orderId;
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }
}
