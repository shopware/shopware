<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidRequestParameterException extends ShopwareHttpException
{
    public function __construct(string $name)
    {
        parent::__construct(
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_REQUEST_PARAMETER';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
