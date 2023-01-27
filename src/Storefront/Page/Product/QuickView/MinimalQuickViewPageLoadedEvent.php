<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product\QuickView;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class MinimalQuickViewPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var MinimalQuickViewPage
     */
    protected $page;

    public function __construct(
        MinimalQuickViewPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): MinimalQuickViewPage
    {
        return $this->page;
    }
}
