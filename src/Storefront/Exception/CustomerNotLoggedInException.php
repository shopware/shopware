<?php declare(strict_types=1);

namespace Shopware\Storefront\Exception;

use Shopware\Core\Framework\ShopwareException;

class CustomerNotLoggedInException extends \Exception implements ShopwareException
{
    protected $message = 'Customer is not logged in.';
}
