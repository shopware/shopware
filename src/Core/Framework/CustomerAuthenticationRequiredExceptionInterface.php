<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that a customer must authenticate before an operation can continue.
 */
#[Package('framework')]
interface CustomerAuthenticationRequiredExceptionInterface extends \Throwable
{
}
