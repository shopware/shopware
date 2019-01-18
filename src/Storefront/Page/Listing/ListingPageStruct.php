<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Listing\ListingPageletStruct;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletStruct;

class ListingPageStruct extends Struct
{
    /**
     * @var ListingPageletStruct
     */
    protected $listing;

    /**
     * @var NavigationSidebarPageletStruct
     */
    protected $navigationSidebar;

    /**
     * @var HeaderPagelet
     */
    protected $header;

    /**
     * @return ListingPageletStruct
     */
    public function getListing(): ListingPageletStruct
    {
        return $this->listing;
    }

    /**
     * @param ListingPageletStruct $listing
     */
    public function setListing(ListingPageletStruct $listing): void
    {
        $this->listing = $listing;
    }

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }

    /**
     * @return NavigationSidebarPageletStruct
     */
    public function getNavigationSidebar(): NavigationSidebarPageletStruct
    {
        return $this->navigationSidebar;
    }

    /**
     * @param NavigationSidebarPageletStruct $navigationSidebar
     */
    public function setNavigationSidebar(NavigationSidebarPageletStruct $navigationSidebar): void
    {
        $this->navigationSidebar = $navigationSidebar;
    }
}
