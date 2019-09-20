<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Offcanvas;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class OffcanvasCartPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var OffcanvasCartPage
     */
    protected $page;

    public function __construct(OffcanvasCartPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): OffcanvasCartPage
    {
        return $this->page;
    }
}
