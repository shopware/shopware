<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreNotAvailableException extends ShopwareHttpException
{
    protected $code = 'STORE-NOT-AVAILABLE';

    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct('Store is not available', $code, $previous);
    }
}
