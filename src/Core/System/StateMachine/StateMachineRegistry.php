<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
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
     */
    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $stateMachineHistoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DefinitionInstanceRegistry $definitionRegistry
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

        $results = $this->stateMachineRepository->search($criteria, $context);

        if ($stateMachine = $results->first()) {
            /** @var StateMachineEntity $stateMachine */
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
                $transitionNames = array_map(fn (StateMachineTransitionEntity $transition) => $transition->getActionName(), $transitions);

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

                return $stateMachineStateCollection;
            }

            $stateMachineHistoryEntity = [
                'stateMachineId' => $toPlace->getStateMachineId(),
                'entityName' => $transition->getEntityName(),
                'fromStateId' => $fromPlace->getId(),
                'toStateId' => $toPlace->getId(),
                'transitionActionName' => $transition->getTransitionName(),
                'userId' => $context->getSource() instanceof AdminApiSource ? $context->getSource()->getUserId() : null,
            ];

            if (Feature::isActive('v6.6.0.0')) {
                $stateMachineHistoryEntity['referencedId'] = $transition->getEntityId();
                $stateMachineHistoryEntity['referencedVersionId'] = $context->getVersionId();
            } else {
                $stateMachineHistoryEntity['entityId'] = ['id' => $transition->getEntityId(), 'version_id' => $context->getVersionId()];
            }

            $this->stateMachineHistoryRepository->create([$stateMachineHistoryEntity], $context);

            $data = [['id' => $transition->getEntityId(), $transition->getStateFieldName() => $toPlace->getId()]];

            $repository->upsert($data, $context);

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
        });
    }

    public function reset(): void
    {
        $this->stateMachines = [];
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
            /** @var StateMachineStateEntity $fromState */
            $fromState = $transition->getFromStateMachineState();
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

        /** @var StateMachineTransitionCollection $stateMachineTransitions */
        $stateMachineTransitions = $stateMachine->getTransitions();

        foreach ($stateMachineTransitions as $transition) {
            /** @var StateMachineStateEntity $toState */
            $toState = $transition->getToStateMachineState();
            // Always allow to cancel a payment whether its a valid transition or not
            if ($transition->getActionName() === 'cancel' && $transitionName === 'cancel') {
                return $toState;
            }

            // Not the transition that was requested step over
            if ($transition->getActionName() !== $transitionName) {
                continue;
            }

            // Already transitioned, this exception is handled by StateMachineRegistry::transition
            if ($toState->getId() === $fromStateId) {
                throw StateMachineException::unnecessaryTransition($transitionName);
            }

            /** @var StateMachineStateEntity $fromState */
            $fromState = $transition->getFromStateMachineState();
            // Desired transition found
            if ($fromState->getId() === $fromStateId) {
                return $toState;
            }
        }

        if ($context->hasState(SetOrderStateAction::FORCE_TRANSITION)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('technicalName', $transitionName));
            $criteria->addFilter(new EqualsFilter('stateMachineId', $stateMachine->getId()));
            if ($toPlace = $this->stateMachineStateRepository->search($criteria, $context)->first()) {
                /** @var StateMachineStateEntity $toPlace */
                return $toPlace;
            }
        }

        $transitions = $this->getAvailableTransitionsById($stateMachineName, $fromStateId, $context);
        $transitionNames = array_map(fn (StateMachineTransitionEntity $transition) => $transition->getActionName(), $transitions);

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

        /** @var StateMachineStateEntity|null $fromPlace */
        $fromPlace = $this->stateMachineStateRepository->search(new Criteria([$fromPlaceId]), $context)->get($fromPlaceId);

        if (!$fromPlace) {
            throw StateMachineException::stateMachineInvalidStateField($stateFieldName);
        }

        return $fromPlace;
    }
}
