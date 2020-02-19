<?php declare(strict_types=1);

namespace Shopware\Administration\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AdminOrderCartService
{
    /**
     * @var CartService
     */
    protected $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function updateShippingCosts(CalculatedPrice $calculatedPrice, SalesChannelContext $context): Cart
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $delivery = $cart->getDeliveries()->first();

        if ($delivery) {
            $delivery->setShippingCosts($calculatedPrice);
        }

        return $this->cartService->recalculate($cart, $context);
    }
}
