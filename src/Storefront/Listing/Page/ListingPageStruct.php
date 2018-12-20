<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;

class ListingPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var ListingPageletStruct
     */
    protected $listing;

    public function __construct()
    {
        $this->listing = new ListingPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

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
}
