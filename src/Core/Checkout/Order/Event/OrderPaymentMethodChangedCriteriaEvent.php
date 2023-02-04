<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('customer-order')]
class OrderPaymentMethodChangedCriteriaEvent extends Event
{
    public function __construct(
        private readonly string $orderId,
        private readonly Criteria $criteria,
        private readonly SalesChannelContext $context
    ) {
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
