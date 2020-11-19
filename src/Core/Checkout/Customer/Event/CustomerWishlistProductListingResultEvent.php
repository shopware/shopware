<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CustomerWishlistProductListingResultEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    public const EVENT_NAME = 'checkout.customer.wishlist_listing_product_result';

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EntitySearchResult
     */
    protected $result;

    /**
     * @var SalesChannelContext
     */
    private $context;

    public function __construct(Request $request, EntitySearchResult $wishlistProductListingResult, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->result = $wishlistProductListingResult;
        $this->context = $salesChannelContext;
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
