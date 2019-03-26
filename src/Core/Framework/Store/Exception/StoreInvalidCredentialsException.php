<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreInvalidCredentialsException extends ShopwareHttpException
{
    protected $code = 'INVALID-CREDENTIALS';

    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('Invalid credentials', $code, $previous);
    }
}
