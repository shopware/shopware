<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Product;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletStruct;

class ProductPageStruct extends Struct
{
    /**
     * @var ProductDetailPageletStruct
     */
    protected $productDetail;

    /**
     * @var HeaderPagelet
     */
    protected $header;

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

    public function getHeader(): HeaderPagelet
    {
        return $this->header;
    }

    public function setHeader(HeaderPagelet $header): void
    {
        $this->header = $header;
    }
}
