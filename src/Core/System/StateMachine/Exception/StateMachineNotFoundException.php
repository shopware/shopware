<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use StateMachineException::stateMachineNotFound instead
 */
#[Package('checkout')]
class StateMachineNotFoundException extends StateMachineException
{
    public function __construct(string $stateMachineName)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use StateMachineException::stateMachineNotFound instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_NOT_FOUND,
            'The StateMachine named "{{ name }}" was not found.',
            ['name' => $stateMachineName]
        );
    }
}
