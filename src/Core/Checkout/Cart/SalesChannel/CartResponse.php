<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<Cart>
 */
#[Package('checkout')]
class CartResponse extends StoreApiResponse
{
    public function getCart(): Cart
    {
        return $this->object;
    }
}
