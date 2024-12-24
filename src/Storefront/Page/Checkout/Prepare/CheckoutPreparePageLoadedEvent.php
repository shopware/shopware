<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Prepare;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class CheckoutPreparePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected CheckoutPreparePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): CheckoutPreparePage
    {
        return $this->page;
    }
}
