<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use StateMachineException::stateMachineWithoutInitialState instead
 */
#[Package('checkout')]
class StateMachineWithoutInitialStateException extends StateMachineException
{
    public function __construct(string $stateMachineName)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use StateMachineException::stateMachineWithoutInitialState instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_WITHOUT_INITIAL_STATE,
            'The StateMachine named "{{ name }}" has no initial state.',
            ['name' => $stateMachineName]
        );
    }
}
