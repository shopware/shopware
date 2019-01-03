<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Search\SearchPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class SearchPageStruct extends Struct
{
    use HeaderPageletStructTrait;

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
