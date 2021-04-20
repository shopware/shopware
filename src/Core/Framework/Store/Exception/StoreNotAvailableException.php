<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreNotAvailableException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Store is not available', [], $previous);
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_NOT_AVAILABLE';
    }
}
