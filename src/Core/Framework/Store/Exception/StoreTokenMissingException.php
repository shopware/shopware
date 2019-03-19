<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreTokenMissingException extends ShopwareHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Store token missing. Error: {{ message }}',
            ['message' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_TOKEN_IS_MISSING';
    }
}
