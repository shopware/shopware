<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletStruct;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletStruct;

class ProductDetailPageStruct extends Struct
{
    /**
     * @var ProductDetailPageletStruct
     */
    protected $productDetail;

    /**
     * @var ContentHeaderPageletStruct
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

    public function getHeader(): ContentHeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(ContentHeaderPageletStruct $header): void
    {
        $this->header = $header;
    }
}
