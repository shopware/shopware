<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerWishlistLoaderCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    public const EVENT_NAME = 'checkout.customer.customer_wishlist_loader_criteria';

    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var SalesChannelContext
     */
    private $context;

    public function __construct(Criteria $criteria, SalesChannelContext $context)
    {
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
