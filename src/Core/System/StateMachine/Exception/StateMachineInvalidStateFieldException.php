<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use StateMachineException::stateMachineInvalidStateField instead
 */
#[Package('checkout')]
class StateMachineInvalidStateFieldException extends StateMachineException
{
    public function __construct(string $fieldName)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use StateMachineException::stateMachineInvalidStateField instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_INVALID_STATE_FIELD,
            'Field "{{ fieldName }}" does not exists or isn\'t of type StateMachineStateField.',
            ['fieldName' => $fieldName]
        );
    }
}
