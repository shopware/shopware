<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreHostMissingException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store token missing');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_HOST_IS_MISSING';
    }
}
