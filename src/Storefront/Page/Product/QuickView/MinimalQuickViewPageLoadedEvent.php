<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class MinimalQuickViewPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected MinimalQuickViewPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): MinimalQuickViewPage
    {
        return $this->page;
    }
}
