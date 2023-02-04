<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class CustomerWishlistProductListingResultEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    final public const EVENT_NAME = 'checkout.customer.wishlist_listing_product_result';

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EntitySearchResult
     */
    protected $result;

    public function __construct(
        Request $request,
        EntitySearchResult $wishlistProductListingResult,
        private SalesChannelContext $context
    ) {
        $this->request = $request;
        $this->result = $wishlistProductListingResult;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }

    public function setResult(EntitySearchResult $result): void
    {
        $this->result = $result;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function setSalesChannelContext(SalesChannelContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}
