<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\CartInfo;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;

class CartInfoPageletRequestEvent extends NestedEvent
{
    public const NAME = 'cartinfo.pagelet.request.event';

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CheckoutContext
     */
    private $context;

    /**
     * @var CartInfoPageletRequest
     */
    private $cartInfoPageletRequest;

    public function __construct(Request $request, CheckoutContext $context, CartInfoPageletRequest $cartInfoPageletRequest)
    {
        $this->request = $request;
        $this->context = $context;
        $this->cartInfoPageletRequest = $cartInfoPageletRequest;
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

    public function getCartinfoPageletRequest(): CartInfoPageletRequest
    {
        return $this->cartInfoPageletRequest;
    }
}
