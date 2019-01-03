<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequest;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequest;

class ListingPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var ListingPageletRequest
     */
    protected $listingRequest;

    /**
     * @var NavigationSidebarPageletRequest
     */
    protected $navigationSidebarRequest;

    /**
     * @return ListingPageletRequest
     */
    public function getListingRequest(): ListingPageletRequest
    {
        return $this->listingRequest;
    }

    /**
     * @param ListingPageletRequest $listingRequest
     */
    public function setListingRequest(ListingPageletRequest $listingRequest): void
    {
        $this->listingRequest = $listingRequest;
    }

    /**
     * @return NavigationSidebarPageletRequest
     */
    public function getNavigationSidebarRequest(): NavigationSidebarPageletRequest
    {
        return $this->navigationSidebarRequest;
    }

    /**
     * @param NavigationSidebarPageletRequest $navigationSidebarRequest
     */
    public function setNavigationSidebarRequest(NavigationSidebarPageletRequest $navigationSidebarRequest): void
    {
        $this->navigationSidebarRequest = $navigationSidebarRequest;
    }
}
