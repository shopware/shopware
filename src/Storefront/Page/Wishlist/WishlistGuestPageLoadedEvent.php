<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class WishlistGuestPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var WishlistGuestPage
     */
    protected $page;

    public function __construct(WishlistGuestPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): WishlistGuestPage
    {
        return $this->page;
    }
}
