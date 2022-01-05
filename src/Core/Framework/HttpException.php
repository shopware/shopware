<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

class HttpException extends ShopwareHttpException
{
    private int $status;

    private string $errorCode;

    public function __construct(
        string $errorCode,
        int $status,
        string $message,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        $this->status = $status;
        $this->errorCode = $errorCode;
        parent::__construct($message, $parameters, $e);
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
