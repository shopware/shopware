<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\UnnecessaryTransitionException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('checkout')]
class StateMachineRegistry implements ResetInterface
{
    /**
     * @var StateMachineEntity[]
     */
    private array $stateMachines;

    /**
     * @internal
     *
     * @param EntityRepository<StateMachineCollection> $stateMachineRepository
     * @param EntityRepository<StateMachineStateCollection> $stateMachineStateRepository
     * @param EntityRepository<StateMachineHistoryCollection> $stateMachineHistoryRepository
     */
    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $stateMachineHistoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly StateMachineLocker $stateMachineLocker,
        private readonly Connection $connection
    ) {
    }

    /**
     * @throws StateMachineException
     * @throws InconsistentCriteriaIdsException
     */
    public function getStateMachine(string $name, Context $context): StateMachineEntity
    {
        if (isset($this->stateMachines[$name])) {
            return $this->stateMachines[$name];
        }

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('state_machine.technicalName', $name))
            ->setLimit(1);

        $criteria->getAssociation('transitions')
            ->addSorting(new FieldSorting('state_machine_transition.actionName'))
            ->addAssociation('fromStateMachineState')
            ->addAssociation('toStateMachineState');

        $criteria->getAssociation('states')
            ->addSorting(new FieldSorting('state_machine_state.technicalName'));

        $results = $this->stateMachineRepository->search($criteria, $context)->getEntities();

        if ($stateMachine = $results->first()) {
            return $this->stateMachines[$name] = $stateMachine;
        }

        throw StateMachineException::stateMachineNotFound($name);
    }

    /**
     * @throws DefinitionNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineException
     *
     * @return array<StateMachineTransitionEntity>
     */
    public function getAvailableTransitions(
        string $entityName,
        string $entityId,
        string $stateFieldName,
        Context $context
    ): array {
        $stateMachineName = $this->getStateField($stateFieldName, $entityName)->getStateMachineName();
        $repository = $this->definitionRegistry->getRepository($entityName);
        $fromPlace = $this->getFromPlace($entityName, $entityId, $stateFieldName, $context, $repository);

        return $this->getAvailableTransitionsById($stateMachineName, $fromPlace->getId(), $context);
    }

    /**
     * @throws StateMachineException
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws DefinitionNotFoundException
     */
    public function transition(Transition $transition, Context $context): StateMachineStateCollection
    {
        return $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($transition): StateMachineStateCollection {
            $result = $this->stateMachineLocker->locked(
                $transition,
                $context,
                fn (): StateMachineTransitionResult => $this->transitionState($transition, $context)
            );

            if ($result->hasTransitioned) {
                $this->dispatchTransitionEvents($transition, $context, $result);
            }

            return $result->stateMachineStates;
        });
    }

    public function reset(): void
    {
        $this->stateMachines = [];
    }

    private function transitionState(Transition $transition, Context $context): StateMachineTransitionResult
    {
        $stateField = $this->getStateField($transition->getStateFieldName(), $transition->getEntityName());

        $stateMachine = $this->getStateMachine($stateField->getStateMachineName(), $context);
        $definition = $this->definitionRegistry->getByEntityName($transition->getEntityName());
        $repository = $this->definitionRegistry->getRepository($transition->getEntityName());

        if ($transition->getTransitionName() === '') {
            $fromPlace = $this->getFromPlace(
                $transition->getEntityName(),
                $transition->getEntityId(),
                $transition->getStateFieldName(),
                $context,
                $repository
            );

            $transitions = $this->getAvailableTransitionsById($stateMachine->getTechnicalName(), $fromPlace->getId(), $context);
            $transitionNames = \array_map(static fn (StateMachineTransitionEntity $transition) => $transition->getActionName(), $transitions);

            throw StateMachineException::illegalStateTransition($fromPlace->getId(), '', $transitionNames);
        }

        // The whole transition runs inside one transaction that starts by locking the entity row. Everything
        // below - the state we come from, the state we go to, the history entry and the state update - is derived
        // from that locked read, so a concurrent transition can neither commit between our read and our write nor
        // have its own result overwritten by ours. Without the lock the destination was computed from a state that
        // another process could still change, which silently reverted state changes it had already committed.
        return RetryableTransaction::transactional($this->connection, function () use ($transition, $context, $stateMachine, $stateField, $definition, $repository): StateMachineTransitionResult {
            $fromPlace = $this->lockAndReadCurrentPlace($definition, $stateField, $transition, $context);

            if (\in_array($fromPlace->getTechnicalName(), $transition->getSkipIfInStates(), true)) {
                // The caller declared this state as one it must not transition away from. It was reached after the
                // caller read the entity, so the caller cannot have checked it itself without reintroducing the race.
                return $this->unchangedResult($stateMachine, $fromPlace);
            }

            try {
                $toPlace = $this->getTransitionDestinationById(
                    $stateMachine->getTechnicalName(),
                    $fromPlace->getId(),
                    $transition->getTransitionName(),
                    $context
                );
            } catch (UnnecessaryTransitionException) {
                // No transition needed, therefore don't create a history entry and return
                return $this->unchangedResult($stateMachine, $fromPlace);
            }

            $source = $this->resolveOriginalSource($context->getSource());

            $stateMachineHistoryEntity = [
                'stateMachineId' => $toPlace->getStateMachineId(),
                'entityName' => $transition->getEntityName(),
                'fromStateId' => $fromPlace->getId(),
                'toStateId' => $toPlace->getId(),
                'transitionActionName' => $transition->getTransitionName(),
                'userId' => $source instanceof AdminApiSource ? $source->getUserId() : null,
                'integrationId' => $source instanceof AdminApiSource ? $source->getIntegrationId() : null,
                'sourceType' => $this->resolveSourceType($source),
                'referencedId' => $transition->getEntityId(),
                'referencedVersionId' => $context->getVersionId(),
                'internalComment' => $transition->getInternalComment(),
            ];

            $data = [['id' => $transition->getEntityId(), $transition->getStateFieldName() => $toPlace->getId()]];

            // Record the history entry and apply the new state atomically, so a failure of either write
            // cannot leave the entity state and the state_machine_history out of sync. The history is written
            // first on purpose: if it fails, the state update (and its entity-written events for indexers,
            // cache invalidation and webhooks) is never performed.
            $this->stateMachineHistoryRepository->create([$stateMachineHistoryEntity], $context);
            $repository->upsert($data, $context);

            $stateMachineStateCollection = new StateMachineStateCollection();

            $stateMachineStateCollection->set('fromPlace', $fromPlace);
            $stateMachineStateCollection->set('toPlace', $toPlace);

            return new StateMachineTransitionResult(
                true,
                $stateMachineStateCollection,
                $stateMachine,
                $fromPlace,
                $toPlace,
            );
        });
    }

    private function unchangedResult(StateMachineEntity $stateMachine, StateMachineStateEntity $fromPlace): StateMachineTransitionResult
    {
        $stateMachineStateCollection = new StateMachineStateCollection();

        $stateMachineStateCollection->set('fromPlace', $fromPlace);
        $stateMachineStateCollection->set('toPlace', $fromPlace);

        return new StateMachineTransitionResult(
            false,
            $stateMachineStateCollection,
            $stateMachine,
            $fromPlace,
            $fromPlace,
        );
    }

    /**
     * Reads the current state directly from the entity row and keeps that row locked for the rest of the
     * transaction. Reading through the DAL would return the same state but would not lock anything, which is the
     * whole point of this read.
     *
     * @throws StateMachineException
     */
    private function lockAndReadCurrentPlace(
        EntityDefinition $definition,
        StateMachineStateField $stateField,
        Transition $transition,
        Context $context
    ): StateMachineStateEntity {
        if ($stateField->is(Inherited::class)) {
            // An inherited state lives on the parent row, so locking this row would guard the wrong state.
            // No such field exists today; fail loudly rather than serialize nothing.
            throw StateMachineException::stateMachineInvalidStateField($transition->getStateFieldName());
        }

        $idField = $definition->getPrimaryKeys()
            ->filter(static fn ($field) => $field instanceof StorageAware && !$field instanceof VersionField)
            ->first();

        if (!$idField instanceof StorageAware) {
            throw StateMachineException::stateMachineInvalidEntityId($transition->getEntityName(), $transition->getEntityId());
        }

        $query = \sprintf(
            'SELECT %s FROM %s WHERE %s = :id',
            EntityDefinitionQueryHelper::escape($stateField->getStorageName()),
            EntityDefinitionQueryHelper::escape($definition->getEntityName()),
            EntityDefinitionQueryHelper::escape($idField->getStorageName()),
        );

        $parameters = ['id' => Uuid::fromHexToBytes($transition->getEntityId())];

        $versionField = $definition->getFields()->filterInstance(VersionField::class)->first();
        if ($versionField instanceof VersionField) {
            // Scoped to the context version, matching what a transition could reach before: the DAL read this
            // through the entity's unique identifier, which for a versioned entity carries the version, so a row
            // that only exists in the live version was never reachable from another version either.
            $query .= \sprintf(' AND %s = :versionId', EntityDefinitionQueryHelper::escape($versionField->getStorageName()));
            $parameters['versionId'] = Uuid::fromHexToBytes($context->getVersionId());
        }

        $fromPlaceId = $this->connection->fetchOne($query . ' FOR UPDATE', $parameters);

        if ($fromPlaceId === false) {
            throw StateMachineException::stateMachineInvalidEntityId($transition->getEntityName(), $transition->getEntityId());
        }

        if (!\is_string($fromPlaceId) || $fromPlaceId === '') {
            throw StateMachineException::stateMachineInvalidStateField($transition->getStateFieldName());
        }

        $fromPlaceId = Uuid::fromBytesToHex($fromPlaceId);

        $fromPlace = $this->stateMachineStateRepository->search(new Criteria([$fromPlaceId]), $context)->getEntities()->get($fromPlaceId);

        if (!$fromPlace) {
            throw StateMachineException::stateMachineInvalidStateField($transition->getStateFieldName());
        }

        return $fromPlace;
    }

    private function resolveOriginalSource(ContextSource $source): ContextSource
    {
        if ($source instanceof AdminSalesChannelApiSource) {
            return $source->getOriginalContext()->getSource();
        }

        return $source;
    }

    private function resolveSourceType(ContextSource $source): ?string
    {
        // read from the source itself, so a custom ContextSource can contribute its own type
        $type = get_object_vars($source)['type'] ?? null;

        return \is_string($type) ? $type : null;
    }

    private function dispatchTransitionEvents(Transition $transition, Context $context, StateMachineTransitionResult $result): void
    {
        $this->eventDispatcher->dispatch(
            new StateMachineTransitionEvent(
                $transition->getEntityName(),
                $transition->getEntityId(),
                $result->fromPlace,
                $result->toPlace,
                $context,
                $transition->getInternalComment(),
            )
        );

        $leaveEvent = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
            $transition,
            $result->stateMachine,
            $result->fromPlace,
            $result->toPlace,
        );

        $this->eventDispatcher->dispatch(
            $leaveEvent,
            $leaveEvent->getName()
        );

        $enterEvent = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            $transition,
            $result->stateMachine,
            $result->fromPlace,
            $result->toPlace,
        );

        $this->eventDispatcher->dispatch(
            $enterEvent,
            $enterEvent->getName()
        );
    }

    /**
     * @throws StateMachineException
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<StateMachineTransitionEntity>
     */
    private function getAvailableTransitionsById(string $stateMachineName, string $fromStateId, Context $context): array
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $stateMachineTransitions = $stateMachine->getTransitions();
        if ($stateMachineTransitions === null) {
            return [];
        }

        $transitions = [];
        foreach ($stateMachineTransitions as $transition) {
            $fromState = $transition->getFromStateMachineState();
            if (!$fromState) {
                continue;
            }

            if ($fromState->getId() === $fromStateId) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /**
     * @throws StateMachineException
     * @throws IllegalTransitionException
     * @throws UnnecessaryTransitionException
     * @throws InconsistentCriteriaIdsException
     */
    private function getTransitionDestinationById(string $stateMachineName, string $fromStateId, string $transitionName, Context $context): StateMachineStateEntity
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $stateMachineTransitions = $stateMachine->getTransitions();
        \assert($stateMachineTransitions !== null);

        foreach ($stateMachineTransitions as $transition) {
            // Not the transition that was requested step over
            if ($transition->getActionName() !== $transitionName) {
                continue;
            }

            $toState = $transition->getToStateMachineState();
            if (!$toState) {
                continue;
            }

            // Already transitioned, this exception is handled by StateMachineRegistry::transition
            if ($toState->getId() === $fromStateId) {
                throw StateMachineException::unnecessaryTransition($transitionName);
            }

            $fromState = $transition->getFromStateMachineState();
            if (!$fromState) {
                continue;
            }

            // Desired transition found
            if ($fromState->getId() === $fromStateId) {
                return $toState;
            }
        }

        if ($context->hasState(SetOrderStateAction::FORCE_TRANSITION)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('technicalName', $transitionName));
            $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachine->getId()));
            $toPlace = $this->stateMachineStateRepository->search($criteria, $context)->getEntities()->first();
            if ($toPlace?->getId() === $fromStateId) {
                throw StateMachineException::unnecessaryTransition($transitionName);
            }

            if ($toPlace) {
                return $toPlace;
            }
        }

        $transitions = $this->getAvailableTransitionsById($stateMachineName, $fromStateId, $context);
        $transitionNames = \array_map(static fn (StateMachineTransitionEntity $transition) => $transition->getActionName(), $transitions);

        throw StateMachineException::illegalStateTransition(
            $fromStateId,
            $transitionName,
            $transitionNames
        );
    }

    /**
     * @throws StateMachineException
     * @throws DefinitionNotFoundException
     */
    private function getStateField(string $stateFieldName, string $entityName): StateMachineStateField
    {
        $definition = $this->definitionRegistry->getByEntityName($entityName);
        $stateField = $definition->getFields()->get($stateFieldName);

        if (!$stateField || !$stateField instanceof StateMachineStateField) {
            throw StateMachineException::stateMachineInvalidStateField($stateFieldName);
        }

        return $stateField;
    }

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     *
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineException
     */
    private function getFromPlace(
        string $entityName,
        string $entityId,
        string $stateFieldName,
        Context $context,
        EntityRepository $repository
    ): StateMachineStateEntity {
        $entity = $repository->search(new Criteria([$entityId]), $context)->getEntities()->get($entityId);

        if (!$entity) {
            throw StateMachineException::stateMachineInvalidEntityId($entityName, $entityId);
        }

        $fromPlaceId = $entity->get($stateFieldName);

        if (!$fromPlaceId || !Uuid::isValid($fromPlaceId)) {
            throw StateMachineException::stateMachineInvalidStateField($stateFieldName);
        }

        $fromPlace = $this->stateMachineStateRepository->search(new Criteria([$fromPlaceId]), $context)->getEntities()->get($fromPlaceId);

        if (!$fromPlace) {
            throw StateMachineException::stateMachineInvalidStateField($stateFieldName);
        }

        return $fromPlace;
    }
}
