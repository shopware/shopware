<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

abstract class HttpException extends ShopwareHttpException
{
    protected string $errorCode;

    protected int $statusCode;

    protected function __construct(int $statusCode, string $errorCode, string $message, array $parameters = [], ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;

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
