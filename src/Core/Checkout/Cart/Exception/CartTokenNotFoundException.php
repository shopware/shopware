<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class CartTokenNotFoundException extends CartException
{
}
