<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;

#[Package('checkout')]
class CustomerNotLoggedInRoutingException extends RoutingException
{
}
