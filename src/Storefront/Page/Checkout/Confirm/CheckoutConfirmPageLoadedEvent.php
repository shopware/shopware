<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class CheckoutConfirmPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var CheckoutConfirmPage
     */
    protected $page;

    public function __construct(CheckoutConfirmPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): CheckoutConfirmPage
    {
        return $this->page;
    }
}
