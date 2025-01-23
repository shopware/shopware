<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.7.0 - Will be removed
 */
#[Package('checkout')]
class CustomerNotLoggedInException extends CartException
{
}
