<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter\Exceptions;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class QueryFutureFieldException extends ShopwareHttpException
{
    public function __construct(string $field, string $entityName, int $apiVersion)
    {
        parent::__construct(
            'The field "{{ field }}" on entity "{{ entityName }}" is not available in v{{ apiVersion }} of the API and cannot be used as criteria or in the path',
            [
                'field' => $field,
                'entityName' => $entityName,
                'apiVersion' => $apiVersion,
            ]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__QUERY_FUTURE_FIELD';
    }
}
