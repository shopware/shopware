<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException;
use Shopware\Core\System\StateMachine\Exception\StateMachineWithoutInitialStateException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class StateMachineRegistry
{
    private EntityRepositoryInterface $stateMachineRepository;

    private EntityRepositoryInterface $stateMachineStateRepository;

    private EntityRepositoryInterface $stateMachineHistoryRepository;

    /**
     * @var StateMachineEntity[]
     */
    private array $stateMachines;

    private EventDispatcherInterface $eventDispatcher;

    private DefinitionInstanceRegistry $definitionRegistry;

    /**
     * @var StateMachineStateEntity[]
     */
    private array $initialStates = [];

    public function __construct(
        EntityRepositoryInterface $stateMachineRepository,
        EntityRepositoryInterface $stateMachineStateRepository,
        EntityRepositoryInterface $stateMachineHistoryRepository,
        EventDispatcherInterface $eventDispatcher,
        DefinitionInstanceRegistry $definitionRegistry
    ) {
        $this->stateMachineRepository = $stateMachineRepository;
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->stateMachineHistoryRepository = $stateMachineHistoryRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->definitionRegistry = $definitionRegistry;
    }

    /**
     * @throws StateMachineNotFoundException
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

        $results = $this->stateMachineRepository->search($criteria, $context);

        if ($results->count() === 0) {
            throw new StateMachineNotFoundException($name);
        }

        return $this->stateMachines[$name] = $results->first();
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineWithoutInitialStateException
     */
    public function getInitialState(string $stateMachineName, Context $context): StateMachineStateEntity
    {
        if (isset($this->initialStates[$stateMachineName])) {
            return $this->initialStates[$stateMachineName];
        }

        /** @var StateMachineEntity|null $stateMachine */
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        if ($stateMachine === null) {
            throw new StateMachineNotFoundException($stateMachineName);
        }

        $initialState = $stateMachine->getInitialState();
        if ($initialState === null) {
            throw new StateMachineWithoutInitialStateException($stateMachineName);
        }

        return $this->initialStates[$stateMachineName] = $initialState;
    }

    /**
     * @throws DefinitionNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     * @throws StateMachineNotFoundException
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
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     * @throws StateMachineInvalidStateFieldException
     * @throws StateMachineInvalidEntityIdException
     * @throws DefinitionNotFoundException
     */
    public function transition(Transition $transition, Context $context): StateMachineStateCollection
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

        if (empty($transition->getTransitionName())) {
            $transitions = $this->getAvailableTransitionsById($stateMachine->getTechnicalName(), $fromPlace->getId(), $context);
            $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
                return $transition->getActionName();
            }, $transitions);

            throw new IllegalTransitionException($fromPlace->getId(), '', $transitionNames);
        }

        $toPlace = $this->getTransitionDestinationById(
            $stateMachine->getTechnicalName(),
            $fromPlace->getId(),
            $transition->getTransitionName(),
            $context
        );

        $this->stateMachineHistoryRepository->create([
            [
                'stateMachineId' => $toPlace->getStateMachineId(),
                'entityName' => $transition->getEntityName(),
                'entityId' => ['id' => $transition->getEntityId(), 'version_id' => $context->getVersionId()],
                'fromStateId' => $fromPlace->getId(),
                'toStateId' => $toPlace->getId(),
                'transitionActionName' => $transition->getTransitionName(),
                'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            ],
        ], $context);

        $data = [['id' => $transition->getEntityId(), $transition->getStateFieldName() => $toPlace->getId()]];
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($repository, $data): void {
            $repository->upsert($data, $context);
        });

        $this->eventDispatcher->dispatch(
            new StateMachineTransitionEvent(
                $transition->getEntityName(),
                $transition->getEntityId(),
                $fromPlace,
                $toPlace,
                $context
            )
        );

        $leaveEvent = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
            $transition,
            $stateMachine,
            $fromPlace,
            $toPlace
        );

        $this->eventDispatcher->dispatch(
            $leaveEvent,
            $leaveEvent->getName()
        );

        $enterEvent = new StateMachineStateChangeEvent(
            $context,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
            $transition,
            $stateMachine,
            $fromPlace,
            $toPlace
        );

        $this->eventDispatcher->dispatch(
            $enterEvent,
            $enterEvent->getName()
        );

        $stateMachineStateCollection = new StateMachineStateCollection();

        $stateMachineStateCollection->set('fromPlace', $fromPlace);
        $stateMachineStateCollection->set('toPlace', $toPlace);

        return $stateMachineStateCollection;
    }

    /**
     * @throws StateMachineNotFoundException
     * @throws InconsistentCriteriaIdsException
     */
    private function getAvailableTransitionsById(string $stateMachineName, string $fromStateId, Context $context): array
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $transitions = [];
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getFromStateMachineState()->getId() === $fromStateId) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /**
     * @throws IllegalTransitionException
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineNotFoundException
     */
    private function getTransitionDestinationById(string $stateMachineName, string $fromStateId, string $transitionName, Context $context): StateMachineStateEntity
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        foreach ($stateMachine->getTransitions() as $transition) {
            //always allow to cancel a payment
            if (
                (
                    $transition->getActionName() === 'cancel'
                    && $transitionName === 'cancel'
                )
                || (
                    $transition->getActionName() === $transitionName
                    && $transition->getFromStateMachineState()->getId() === $fromStateId
                )
            ) {
                return $transition->getToStateMachineState();
            }
        }

        $transitions = $this->getAvailableTransitionsById($stateMachineName, $fromStateId, $context);
        $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
            return $transition->getActionName();
        }, $transitions);

        throw new IllegalTransitionException(
            $fromStateId,
            $transitionName,
            $transitionNames
        );
    }

    /**
     * @throws StateMachineInvalidStateFieldException
     * @throws DefinitionNotFoundException
     */
    private function getStateField(string $stateFieldName, string $entityName): StateMachineStateField
    {
        $definition = $this->definitionRegistry->getByEntityName($entityName);
        $stateField = $definition->getFields()->get($stateFieldName);

        if (!$stateField || !$stateField instanceof StateMachineStateField) {
            throw new StateMachineInvalidStateFieldException($stateFieldName);
        }

        return $stateField;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws StateMachineInvalidEntityIdException
     * @throws StateMachineInvalidStateFieldException
     */
    private function getFromPlace(
        string $entityName,
        string $entityId,
        string $stateFieldName,
        Context $context,
        EntityRepositoryInterface $repository
    ): StateMachineStateEntity {
        $entity = $repository->search(new Criteria([$entityId]), $context)->get($entityId);

        if (!$entity) {
            throw new StateMachineInvalidEntityIdException($entityName, $entityId);
        }

        $fromPlaceId = $entity->get($stateFieldName);

        if (!$fromPlaceId || !Uuid::isValid($fromPlaceId)) {
            throw new StateMachineInvalidStateFieldException($stateFieldName);
        }

        $fromPlace = $this->stateMachineStateRepository->search(new Criteria([$fromPlaceId]), $context)->get($fromPlaceId);

        if (!$fromPlace) {
            throw new StateMachineInvalidStateFieldException($stateFieldName);
        }

        return $fromPlace;
    }
}
