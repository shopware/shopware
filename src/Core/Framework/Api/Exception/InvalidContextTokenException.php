<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidContextTokenException extends ShopwareHttpException
{
    public function __construct(string $token)
    {
        parent::__construct(
            'The provided context token "{{ token }}" is invalid.',
            ['token' => $token]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__CONTEXT_INVALID_TOKEN';
    }
}
