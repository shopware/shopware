<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Symfony\Component\HttpFoundation\Response;

class HttpException extends ShopwareHttpException
{
    private int $status;

    private string $errorCode;

    public function __construct(
        string $errorCode,
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
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
