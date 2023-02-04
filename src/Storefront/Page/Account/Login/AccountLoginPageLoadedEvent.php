<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Login;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class AccountLoginPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AccountLoginPage
     */
    protected $page;

    public function __construct(
        AccountLoginPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountLoginPage
    {
        return $this->page;
    }
}
