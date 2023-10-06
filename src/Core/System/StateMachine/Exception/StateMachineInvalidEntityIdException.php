<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use StateMachineException::stateMachineInvalidEntityId instead
 */
#[Package('checkout')]
class StateMachineInvalidEntityIdException extends StateMachineException
{
    public function __construct(
        string $entityName,
        string $entityId
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use StateMachineException::stateMachineInvalidEntityId instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_INVALID_ENTITY_ID,
            'Unable to read entity "{{ entityName }}" with id "{{ entityId }}".',
            [
                'entityName' => $entityName,
                'entityId' => $entityId,
            ]
        );
    }
}
