<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use StateMachineException::stateMachineStateNotFound instead
 */
#[Package('checkout')]
class StateMachineStateNotFoundException extends StateMachineException
{
    public function __construct(
        string $stateMachineName,
        string $technicalPlaceName
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use StateMachineException::stateMachineStateNotFound instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_STATE_NOT_FOUND,
            'The place "{{ place }}" for state machine named "{{ stateMachine }}" was not found.',
            [
                'place' => $technicalPlaceName,
                'stateMachine' => $stateMachineName,
            ]
        );
    }
}
