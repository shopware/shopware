<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class ProductDetailPageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var ProductDetailPageletStruct
     */
    protected $productDetail;

    /**
     * DetailPageStruct constructor.
     */
    public function __construct()
    {
        $this->productDetail = new ProductDetailPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return ProductDetailPageletStruct
     */
    public function getProductDetail(): ProductDetailPageletStruct
    {
        return $this->productDetail;
    }

    /**
     * @param ProductDetailPageletStruct $productDetail
     */
    public function setProductDetail(ProductDetailPageletStruct $productDetail): void
    {
        $this->productDetail = $productDetail;
    }
}
