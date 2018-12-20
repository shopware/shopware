<?php declare(strict_types=1);

namespace Shopware\Storefront\Search\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class SearchPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var SearchPageletStruct
     */
    protected $listing;

    public function __construct()
    {
        $this->listing = new SearchPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return SearchPageletStruct
     */
    public function getListing(): SearchPageletStruct
    {
        return $this->listing;
    }

    /**
     * @param SearchPageletStruct $listing
     */
    public function setListing(SearchPageletStruct $listing): void
    {
        $this->listing = $listing;
    }
}
