<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Listing\ListingPageletStruct;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletStruct;

class ListingPageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var ListingPageletStruct
     */
    protected $listing;

    /**
     * @var NavigationSidebarPageletStruct
     */
    protected $navigationSidebar;

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
