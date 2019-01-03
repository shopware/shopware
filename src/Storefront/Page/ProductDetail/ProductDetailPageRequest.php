<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletRequestTrait;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletRequest;

class ProductDetailPageRequest extends Struct
{
    use HeaderPageletRequestTrait;

    /**
     * @var bool
     */
    protected $xmlHttpRequest = false;

    /**
     * @var ProductDetailPageletRequest
     */
    protected $detailRequest;

    /**
     * @return ProductDetailPageletRequest
     */
    public function getDetailRequest(): ProductDetailPageletRequest
    {
        return $this->detailRequest;
    }

    /**
     * @param ProductDetailPageletRequest $detailRequest
     */
    public function setDetailRequest(ProductDetailPageletRequest $detailRequest): void
    {
        $this->detailRequest = $detailRequest;
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
    public function setxmlHttpRequest(bool $xmlHttpRequest): void
    {
        $this->xmlHttpRequest = $xmlHttpRequest;
    }
}
