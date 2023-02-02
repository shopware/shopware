<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('merchant-services')]
class StoreInvalidCredentialsException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_INVALID_CREDENTIALS';
    }
}
