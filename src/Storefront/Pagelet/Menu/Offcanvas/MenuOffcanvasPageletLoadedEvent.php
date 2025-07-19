<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Menu\Offcanvas;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class MenuOffcanvasPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected MenuOffcanvasPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): MenuOffcanvasPagelet
    {
        return $this->pagelet;
    }
}
