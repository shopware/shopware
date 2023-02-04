<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Listing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class AddressListingPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AddressListingPage
     */
    protected $page;

    public function __construct(
        AddressListingPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AddressListingPage
    {
        return $this->page;
    }
}
