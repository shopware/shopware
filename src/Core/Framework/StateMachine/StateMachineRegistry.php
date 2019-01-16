<?php declare(strict_types=1);

namespace Shopware\Core\Framework\StateMachine;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;

class StateMachineRegistry
{
    /**
     * @var EntityRepository
     */
    private $stateMachineRepository;

    /**
     * @var StateMachineEntity[]
     */
    private $stateMachines;

    public function __construct(EntityRepository $stateMachineRepository)
    {
        $this->stateMachineRepository = $stateMachineRepository;
    }

    public function getStateMachine(string $name, Context $context): StateMachineEntity
    {
        if (isset($this->stateMachines[$name])) {
            return $this->stateMachines[$name];
        }

        $transitionCriteria = new Criteria();
        $transitionCriteria->addSorting(new FieldSorting('state_machine_transition.actionName'));
        $statesCriteria = new Criteria();
        $statesCriteria->addSorting(new FieldSorting('state_machine_state.technicalName'));

        $criteria = new Criteria();
        $criteria->addAssociation('state_machine.transitions', $transitionCriteria);
        $criteria->addAssociation('state_machine.states', $statesCriteria);
        $criteria->addFilter(new EqualsFilter('state_machine.technicalName', $name));
        $criteria->setLimit(1);

        $results = $this->stateMachineRepository->search($criteria, $context);

        if ($results->count() === 0) {
            throw new StateMachineNotFoundException($name);
        }

        return $this->stateMachines[$name] = $results->first();
    }

    public function getInitialState(string $stateMachineName, Context $context): StateMachineStateEntity
    {
        /** @var StateMachineEntity|null $stateMachine */
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        if ($stateMachine === null) {
            throw new StateMachineNotFoundException($stateMachineName);
        }

        $initialState = $stateMachine->getInitialState();
        if ($initialState === null) {
            throw new StateMachineWithoutInitialStateException($stateMachineName);
        }

        return $initialState;
    }

    public function getStateByTechnicalName(string $stateMachineName, string $technicalName, Context $context): StateMachineStateEntity
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        /** @var StateMachineStateEntity $state */
        foreach ($stateMachine->getStates() as $state) {
            if ($state->getTechnicalName() === $technicalName) {
                return $state;
            }
        }

        throw new StateMachineStateNotFoundException($stateMachineName, $technicalName);
    }

    /**
     * @throws StateMachineNotFoundException
     */
    public function getAvailableTransitions(string $stateMachineName, string $fromStateName, Context $context): array
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $transitions = [];
        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getFromStateMachineState()->getTechnicalName() === $fromStateName) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    public function transition(string $stateMachineName, string $fromStateName, string $transitionName, Context $context): StateMachineStateEntity
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        /** @var StateMachineTransitionEntity $transition */
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getActionName() === $transitionName && $transition->getFromStateMachineState()->getTechnicalName() === $fromStateName) {
                return $transition->getToStateMachineState();
            }
        }

        $transitions = $this->getAvailableTransitions($stateMachineName, $fromStateName, $context);
        $transitionNames = array_map(function (StateMachineTransitionEntity $transition) {
            return $transition->getActionName();
        }, $transitions);

        throw new IllegalTransitionException(
            $fromStateName,
            $transitionName,
            $transitionNames
        );
    }
}
