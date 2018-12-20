<?php declare(strict_types=1);

namespace Shopware\Storefront\Product\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class DetailPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var DetailPageletStruct
     */
    protected $productDetail;

    /**
     * DetailPageStruct constructor.
     */
    public function __construct()
    {
        $this->productDetail = new DetailPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return DetailPageletStruct
     */
    public function getProductDetail(): DetailPageletStruct
    {
        return $this->productDetail;
    }

    /**
     * @param DetailPageletStruct $productDetail
     */
    public function setProductDetail(DetailPageletStruct $productDetail): void
    {
        $this->productDetail = $productDetail;
    }
}
