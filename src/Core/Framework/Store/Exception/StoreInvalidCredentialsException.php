<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreInvalidCredentialsException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Invalid credentials', [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_INVALID_CREDENTIALS';
    }
}
