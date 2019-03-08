<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StoreSignatureValidationException extends ShopwareHttpException
{
    protected $code = 'STORE-SIGNATURE-VALIDATION';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Store signature validation failed. Error: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
