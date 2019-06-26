<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class StorefrontRequestEvent extends Event
{
    /**
     * @var SalesChannelContext
     */
    protected $context;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(SalesChannelContext $context, Cart $cart, Request $request)
    {
        $this->context = $context;
        $this->cart = $cart;
        $this->request = $request;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
