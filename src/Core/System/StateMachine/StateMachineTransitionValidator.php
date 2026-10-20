<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed. The unique key on state_machine_transition enforces a single destination per action and source state.
 */
#[Package('checkout')]
class StateMachineTransitionValidator implements EventSubscriberInterface
{
    final public const VIOLATION_DUPLICATE_TRANSITION = 'duplicate_state_machine_transition_violation';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @phpstan-ignore shopware.deprecatedClass (framework-invoked, must not trigger a deprecation)
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $transitions = [];
        foreach ($event->getCommandsForEntity(StateMachineTransitionDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if ($command instanceof UpdateCommand
                && !isset($payload['action_name'])
                && !isset($payload['state_machine_id'])
                && !isset($payload['from_state_id'])
            ) {
                continue;
            }

            $id = $command->getPrimaryKey()['id'];
            $transition = $this->resolveTransition($id, $payload, $command instanceof UpdateCommand);
            if ($transition === null) {
                continue;
            }

            $transitions[] = ['id' => $id, 'path' => $command->getPath(), ...$transition];
        }

        if ($transitions === []) {
            return;
        }

        $violations = new ConstraintViolationList();

        $seen = [];
        foreach ($transitions as $transition) {
            $key = \sprintf('%s|%s|%s', Uuid::fromBytesToHex($transition['state_machine_id']), Uuid::fromBytesToHex($transition['from_state_id']), $transition['action_name']);

            if (isset($seen[$key]) || $this->conflictsWithExistingTransition($transition)) {
                $violations->add($this->buildViolation($transition));
            }

            $seen[$key] = true;
        }

        if ($violations->count() === 0) {
            return;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Writing a state machine transition with the same action name and source state as an existing transition, but a different destination state, is ambiguous and will be rejected in v6.8.0.0. A state machine action must have exactly one destination state per source state.'
            );

            return;
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{action_name: string, state_machine_id: string, from_state_id: string}|null
     */
    private function resolveTransition(string $id, array $payload, bool $isUpdate): ?array
    {
        $actionName = $payload['action_name'] ?? null;
        $stateMachineId = $payload['state_machine_id'] ?? null;
        $fromStateId = $payload['from_state_id'] ?? null;

        if ($isUpdate && ($actionName === null || $stateMachineId === null || $fromStateId === null)) {
            $existing = $this->connection->fetchAssociative(
                'SELECT `action_name`, `state_machine_id`, `from_state_id` FROM `state_machine_transition` WHERE `id` = :id',
                ['id' => $id]
            );

            if ($existing === false) {
                return null;
            }

            $actionName ??= $existing['action_name'];
            $stateMachineId ??= $existing['state_machine_id'];
            $fromStateId ??= $existing['from_state_id'];
        }

        if (!\is_string($actionName) || !\is_string($stateMachineId) || !\is_string($fromStateId)) {
            return null;
        }

        return [
            'action_name' => $actionName,
            'state_machine_id' => $stateMachineId,
            'from_state_id' => $fromStateId,
        ];
    }

    /**
     * @param array{id: string, path: string, action_name: string, state_machine_id: string, from_state_id: string} $transition
     */
    private function conflictsWithExistingTransition(array $transition): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM `state_machine_transition`
             WHERE `state_machine_id` = :stateMachineId
                 AND `from_state_id` = :fromStateId
                 AND `action_name` = :actionName
                 AND `id` != :id',
            [
                'stateMachineId' => $transition['state_machine_id'],
                'fromStateId' => $transition['from_state_id'],
                'actionName' => $transition['action_name'],
                'id' => $transition['id'],
            ]
        );
    }

    /**
     * @param array{id: string, path: string, action_name: string, state_machine_id: string, from_state_id: string} $transition
     */
    private function buildViolation(array $transition): ConstraintViolation
    {
        $template = 'The action "{{ actionName }}" of state machine "{{ stateMachineId }}" already has a transition from state "{{ fromStateId }}" to a different destination state. A state machine action must have exactly one destination state per source state.';
        $parameters = [
            '{{ actionName }}' => $transition['action_name'],
            '{{ stateMachineId }}' => Uuid::fromBytesToHex($transition['state_machine_id']),
            '{{ fromStateId }}' => Uuid::fromBytesToHex($transition['from_state_id']),
        ];

        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $template),
            $template,
            $parameters,
            null,
            $transition['path'],
            null,
            null,
            self::VIOLATION_DUPLICATE_TRANSITION
        );
    }
}
