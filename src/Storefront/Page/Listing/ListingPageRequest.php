<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequest;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequest;

class ListingPageRequest extends Struct
{
    /**
     * @var ListingPageletRequest
     */
    protected $listingRequest;

    /**
     * @var NavigationSidebarPageletRequest
     */
    protected $navigationSidebarRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->listingRequest = new ListingPageletRequest();
        $this->navigationSidebarRequest = new NavigationSidebarPageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return ListingPageletRequest
     */
    public function getListingRequest(): ListingPageletRequest
    {
        return $this->listingRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
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
