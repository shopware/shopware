<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineStateNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineWithoutInitialStateException;
use Shopware\Core\System\StateMachine\Exception\UnnecessaryTransitionException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class StateMachineException extends HttpException
{
    public const ILLEGAL_STATE_TRANSITION = 'SYSTEM__ILLEGAL_STATE_TRANSITION';
    public const STATE_MACHINE_INVALID_ENTITY_ID = 'SYSTEM__STATE_MACHINE_INVALID_ENTITY_ID';
    public const STATE_MACHINE_INVALID_STATE_FIELD = 'SYSTEM__STATE_MACHINE_INVALID_STATE_FIELD';
    public const STATE_MACHINE_NOT_FOUND = 'SYSTEM__STATE_MACHINE_NOT_FOUND';
    public const STATE_MACHINE_STATE_NOT_FOUND = 'SYSTEM__STATE_MACHINE_STATE_NOT_FOUND';
    public const STATE_MACHINE_WITHOUT_INITIAL_STATE = 'SYSTEM__STATE_MACHINE_WITHOUT_INITIAL_STATE';
    public const UNNECESSARY_TRANSITION = 'SYSTEM__UNNECESSARY_TRANSITION';

    /**
     * @param array<mixed> $possibleTransitions
     */
    public static function illegalStateTransition(string $currentState, string $transition, array $possibleTransitions): IllegalTransitionException
    {
        return new IllegalTransitionException($currentState, $transition, $possibleTransitions);
    }

    public static function stateMachineInvalidEntityId(string $entityName, string $entityId): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new StateMachineInvalidEntityIdException($entityName, $entityId);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_INVALID_ENTITY_ID,
            'Unable to read entity "{{ entityName }}" with id "{{ entityId }}".',
            [
                'entityName' => $entityName,
                'entityId' => $entityId,
            ]
        );
    }

    public static function stateMachineInvalidStateField(string $fieldName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new StateMachineInvalidStateFieldException($fieldName);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_INVALID_STATE_FIELD,
            'Field "{{ fieldName }}" does not exists or isn\'t of type StateMachineStateField.',
            [
                'fieldName' => $fieldName,
            ]
        );
    }

    public static function stateMachineNotFound(string $stateMachineName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new StateMachineNotFoundException($stateMachineName);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_NOT_FOUND,
            'The StateMachine named "{{ name }}" was not found.',
            ['name' => $stateMachineName]
        );
    }

    public static function stateMachineStateNotFound(string $stateMachineName, string $technicalPlaceName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new StateMachineStateNotFoundException($stateMachineName, $technicalPlaceName);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_STATE_NOT_FOUND,
            'The place "{{ place }}" for state machine named "{{ stateMachine }}" was not found.',
            [
                'place' => $technicalPlaceName,
                'stateMachine' => $stateMachineName,
            ]
        );
    }

    public static function stateMachineWithoutInitialState(string $stateMachineName): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new StateMachineWithoutInitialStateException($stateMachineName);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STATE_MACHINE_WITHOUT_INITIAL_STATE,
            'The StateMachine named "{{ name }}" has no initial state.',
            ['name' => $stateMachineName]
        );
    }

    public static function unnecessaryTransition(string $transition): UnnecessaryTransitionException
    {
        return new UnnecessaryTransitionException($transition);
    }
}
