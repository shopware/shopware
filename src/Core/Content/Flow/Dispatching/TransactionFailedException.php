<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('services-settings')]
class TransactionFailedException extends FlowException
{
    final public const TRANSACTION_FAILED = 'TRANSACTION_FAILED';

    public static function because(\Throwable $e): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::TRANSACTION_FAILED,
            'Transaction failed because an exception occurred. Exception: ' . $e->getMessage(),
            [],
            $e,
        );
    }
}
