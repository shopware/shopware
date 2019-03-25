<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreTokenMissingException extends ShopwareHttpException
{
    protected $code = 'STORE-TOKEN-MISSING';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Store token missing. Error: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
