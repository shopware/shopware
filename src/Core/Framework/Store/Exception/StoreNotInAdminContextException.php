<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreNotInAdminContextException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Store actions not available outside of AdminApiContext.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_NOT_IN_ADMIN_CONTEXT';
    }
}
