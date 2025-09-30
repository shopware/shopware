<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Wishlist;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class GuestWishlistPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected GuestWishlistPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): GuestWishlistPagelet
    {
        return $this->pagelet;
    }
}
