<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class ProductDetailPageLoadedEvent extends Event
{
    public const NAME = 'detail.page.loaded.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ProductDetailPageRequest
     */
    protected $productDetailPageRequest;

    public function __construct(Request $request, CheckoutContext $context, ProductDetailPageRequest $detailPageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->productDetailPageRequest = $detailPageRequest;
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

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getDetailPageRequest(): ProductDetailPageRequest
    {
        return $this->productDetailPageRequest;
    }
}
