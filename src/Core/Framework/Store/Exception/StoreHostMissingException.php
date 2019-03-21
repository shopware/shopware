<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreHostMissingException extends ShopwareHttpException
{
    protected $code = 'SHOP-HOST-MISSING';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Store host missing', 401, $previous);
    }
}
