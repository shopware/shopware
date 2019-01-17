<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductDetailPageletLoadedEvent extends NestedEvent
{
    public const NAME = 'product-detail.pagelet.loaded.event';

    /**
     * @var ProductDetailPageletStruct
     */
    protected $pagelet;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var ProductDetailPageletRequest
     */
    protected $request;

    public function __construct(
        ProductDetailPageletStruct $pagelet,
        CheckoutContext $context,
        ProductDetailPageletRequest $request
    ) {
        $this->pagelet = $pagelet;
        $this->context = $context;
        $this->request = $request;
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

    public function getPagelet(): ProductDetailPageletStruct
    {
        return $this->pagelet;
    }

    public function getRequest(): ProductDetailPageletRequest
    {
        return $this->request;
    }
}
