<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Profile;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class AccountProfilePageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AccountProfilePage
     */
    protected $page;

    public function __construct(
        AccountProfilePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountProfilePage
    {
        return $this->page;
    }
}
