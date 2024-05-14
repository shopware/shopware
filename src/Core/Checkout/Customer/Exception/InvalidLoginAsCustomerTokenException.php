<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class InvalidLoginAsCustomerTokenException extends CustomerException
{
}
