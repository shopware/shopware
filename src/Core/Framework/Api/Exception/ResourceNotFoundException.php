<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ResourceNotFoundException extends ShopwareHttpException
{
    public function __construct(
        string $resourceType,
        array $primaryKey
    ) {
        $resourceIds = [];
        foreach ($primaryKey as $key => $value) {
            $resourceIds[] = $key . '(' . $value . ')';
        }

        parent::__construct(
            'The {{ type }} resource with the following primary key was not found: {{ primaryKeyString }}',
            ['type' => $resourceType, 'primaryKey' => $primaryKey, 'primaryKeyString' => implode(' ', $resourceIds)]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__RESOURCE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
