<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
interface CartEvent
{
    public function getCart(): Cart;
}
