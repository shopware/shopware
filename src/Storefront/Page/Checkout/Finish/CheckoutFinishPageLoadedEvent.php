<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class CheckoutFinishPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var CheckoutFinishPage
     */
    protected $page;

    public function __construct(
        CheckoutFinishPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): CheckoutFinishPage
    {
        return $this->page;
    }
}
