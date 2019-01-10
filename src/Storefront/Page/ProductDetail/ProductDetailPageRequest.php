<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\ContentHeader\ContentHeaderPageletRequest;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletRequest;

class ProductDetailPageRequest extends Struct
{
    /**
     * @var bool
     */
    protected $xmlHttpRequest;
    /**
     * @var ProductDetailPageletRequest
     */
    protected $productDetailRequest;

    /**
     * @var ContentHeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->productDetailRequest = new ProductDetailPageletRequest();
        $this->headerRequest = new ContentHeaderPageletRequest();
    }

    /**
     * @return ProductDetailPageletRequest
     */
    public function getProductDetailRequest(): ProductDetailPageletRequest
    {
        return $this->productDetailRequest;
    }

    public function getHeaderRequest(): ContentHeaderPageletRequest
    {
        return $this->headerRequest;
    }

    /**
     * @return bool
     */
    public function isXmlHttpRequest(): bool
    {
        return $this->xmlHttpRequest;
    }

    /**
     * @param bool $xmlHttpRequest
     */
    public function setXmlHttpRequest(bool $xmlHttpRequest): void
    {
        $this->xmlHttpRequest = $xmlHttpRequest;
    }
}
