<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class ProductDetailPageletRequestEvent extends NestedEvent
{
    public const NAME = 'product-detail.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var ProductDetailPageletRequest
     */
    protected $detailPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, ProductDetailPageletRequest $detailPageletRequest)
    {
        $this->context = $context;
        $this->httpRequest = $request;
        $this->detailPageletRequest = $detailPageletRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    public function getDetailPageletRequest(): ProductDetailPageletRequest
    {
        return $this->detailPageletRequest;
    }
}
