<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\DataStack;

use Shopware\Core\Framework\ShopwareException;

class ExceptionNoStackItemFound extends \InvalidArgumentException implements ShopwareException
{
}
