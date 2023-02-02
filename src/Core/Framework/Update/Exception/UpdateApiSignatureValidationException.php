<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UpdateApiSignatureValidationException extends ShopwareHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'Update-API signature validation failed. Error: {{ error }}',
            ['error' => $reason]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__UPDATEAPI_SIGNATURE_INVALID';
    }
}
