<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreSignatureValidationException extends ShopwareHttpException
{
    public function __construct(string $reason, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Store signature validation failed. Error: {{ error }}',
            ['error' => $reason],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_SIGNATURE_INVALID';
    }
}
