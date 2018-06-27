<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Symfony\Component\HttpFoundation\Response;

abstract class ShopwareHttpException extends \Exception implements ShopwareException
{
    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getErrors(): \Generator
    {
        yield [
            'code' => (string) $this->getCode(),
            'status' => (string) $this->getStatusCode(),
            'title' => Response::$statusTexts[$this->getStatusCode()] ?? 'unknown status',
            'detail' => $this->getMessage(),
        ];
    }
}
