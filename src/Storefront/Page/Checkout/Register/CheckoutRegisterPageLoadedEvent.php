<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class CheckoutRegisterPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var CheckoutRegisterPage
     */
    protected $page;

    public function __construct(CheckoutRegisterPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): CheckoutRegisterPage
    {
        return $this->page;
    }
}
