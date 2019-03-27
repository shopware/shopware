<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreNotAvailableException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store is not available');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_NOT_AVAILABLE';
    }
}
