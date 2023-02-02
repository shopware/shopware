<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ApiTypeNotFoundException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct(
            'A api type "{{ type }}" was not found.',
            ['type' => $type]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__API_DEFINITION_TYPE_NOT_SUPPORTED';
    }
}
