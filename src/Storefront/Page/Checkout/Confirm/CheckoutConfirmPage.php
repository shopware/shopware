<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Storefront\Framework\Page\GenericPage;

class CheckoutConfirmPage extends GenericPage
{
    /**
     * @var Cart
     */
    protected $cart;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }
}
