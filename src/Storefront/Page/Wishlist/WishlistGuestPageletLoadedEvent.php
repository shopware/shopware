<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class WishlistGuestPageletLoadedEvent extends PageletLoadedEvent
{
    /**
     * @var WishlistGuestPagelet
     */
    protected $pagelet;

    public function __construct(WishlistGuestPagelet $pagelet, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->pagelet = $pagelet;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): WishlistGuestPagelet
    {
        return $this->pagelet;
    }
}
