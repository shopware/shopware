<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('merchant-services')]
class StoreSignatureValidationException extends ShopwareHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Store signature validation failed. Error: {{ error }}',
            ['error' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__STORE_SIGNATURE_INVALID';
    }
}
