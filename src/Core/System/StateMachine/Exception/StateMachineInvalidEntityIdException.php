<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StateMachineInvalidEntityIdException extends ShopwareHttpException
{
    public function __construct(
        string $entityName,
        string $entityId
    ) {
        parent::__construct(
            'Unable to read entity "{{ entityName }}" with id "{{ entityId }}".',
            [
                'entityName' => $entityName,
                'entityId' => $entityId,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__STATE_MACHINE_INVALID_ENTITY_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
