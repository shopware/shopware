<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
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
        $repository = $this->definitionRegistry->getRepository($transition->getEntityName());

        $fromPlace = $this->getFromPlace(
            $transition->getEntityName(),
            $transition->getEntityId(),
            $transition->getStateFieldName(),
            $context,
            $repository
        );

        if ($transition->getTransitionName() === '') {
            $transitions = $this->getAvailableTransitionsById($stateMachine->getTechnicalName(), $fromPlace->getId(), $context);
            $transitionNames = \array_map(static fn (StateMachineTransitionEntity $transition) => $transition->getActionName(), $transitions);

            throw StateMachineException::illegalStateTransition($fromPlace->getId(), '', $transitionNames);
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

        $stateMachineHistoryEntity = [
            'stateMachineId' => $toPlace->getStateMachineId(),
            'entityName' => $transition->getEntityName(),
            'fromStateId' => $fromPlace->getId(),
            'toStateId' => $toPlace->getId(),
            'transitionActionName' => $transition->getTransitionName(),
            'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            'integrationId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getIntegrationId() : null,
            'referencedId' => $transition->getEntityId(),
            'referencedVersionId' => $context->getVersionId(),
            'internalComment' => $transition->getInternalComment(),
        ];

        $data = [['id' => $transition->getEntityId(), $transition->getStateFieldName() => $toPlace->getId()]];

        // Record the history entry and apply the new state atomically, so a failure of either write
        // cannot leave the entity state and the state_machine_history out of sync. The history is written
        // first on purpose: if it fails, the state update (and its entity-written events for indexers,
        // cache invalidation and webhooks) is never performed. Nested DAL transactions are handled via
        // DBAL savepoints.
        RetryableTransaction::transactional($this->connection, function () use ($repository, $data, $stateMachineHistoryEntity, $context): void {
            $this->stateMachineHistoryRepository->create([$stateMachineHistoryEntity], $context);
            $repository->upsert($data, $context);
        });

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
        $entity = $repository->search(new Criteria([$entityId]), $context)->get($entityId);

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
