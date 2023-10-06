<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class EntityNotFoundException extends ShopwareHttpException
{
    public function __construct(
        string $entity,
        string $identifier
    ) {
        parent::__construct(
            '{{ entity }} for id {{ identifier }} not found.',
            ['entity' => $entity, 'identifier' => $identifier]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ENTITY_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
