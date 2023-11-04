<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class HttpException extends ShopwareHttpException
{
    public function __construct(
        protected int $statusCode,
        protected string $errorCode,
        string $message,
        array $parameters = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $parameters, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
