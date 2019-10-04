<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter\Exceptions;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class QueryDeprecatedFieldException extends ShopwareHttpException
{
    public function __construct(string $field, string $entityName, int $apiVersion)
    {
        parent::__construct('The field "{{ field }}" on entity "{{ entityName }}" is deprecated in v{{ apiVersion }} of the API and cannot be used as criteria',
            [
                'field' => $field,
                'entityName' => $entityName,
                'apiVersion' => $apiVersion
            ]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__QUERY_DEPRECATED_FIELD';
    }
}
