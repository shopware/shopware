<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('checkout')]
class AccountProfilePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountProfilePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountProfilePage
    {
        return $this->page;
    }
}
