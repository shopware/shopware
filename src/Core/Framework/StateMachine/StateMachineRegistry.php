<?php declare(strict_types=1);

namespace Shopware\Core\Framework\StateMachine;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateStruct;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionStruct;
use Shopware\Core\System\StateMachine\StateMachineStruct;

class StateMachineRegistry
{
    /**
     * @var RepositoryInterface
     */
    private $stateMachineRepository;

    /**
     * @var StateMachineStruct[]
     */
    private $stateMachines;

    public function __construct(RepositoryInterface $stateMachineRepository)
    {
        $this->stateMachineRepository = $stateMachineRepository;
    }

    public function getStateMachine(string $name, Context $context): StateMachineStruct
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
        $criteria->addAssociation('state_machine.initialState');
        $criteria->addFilter(new EqualsFilter('state_machine.technicalName', $name));
        $criteria->setLimit(1);

        $results = $this->stateMachineRepository->search($criteria, $context);

        if ($results->count() === 0) {
            throw new StateMachineNotFoundException($name);
        }

        return $this->stateMachines[$name] = $results->first();
    }

    public function getInitialState(string $stateMachineName, Context $context): StateMachineStateStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('state_machine.technicalName', $stateMachineName));
        $criteria->setLimit(1);

        $result = $this->stateMachineRepository->search($criteria, $context);

        /** @var StateMachineStruct|null $stateMachine */
        $stateMachine = $result->first();

        if ($stateMachine === null) {
            throw new StateMachineNotFoundException($stateMachineName);
        }

        return $stateMachine->getInitialState();
    }

    public function getStateByTechnicalName(string $stateMachineName, string $technicalName, Context $context): StateMachineStateStruct
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        /** @var StateMachineStateStruct $state */
        foreach ($stateMachine->getStates() as $state) {
            if ($state->getTechnicalName() === $technicalName) {
                return $state;
            }
        }

        throw new StateMachineStateNotFoundException($stateMachineName, $technicalName);
    }

    /**
     * @param string  $stateMachineName
     * @param string  $fromStateName
     * @param Context $context
     *
     * @throws StateMachineNotFoundException
     *
     * @return StateMachineTransitionStruct[]
     */
    public function getAvailableTransitions(string $stateMachineName, string $fromStateName, Context $context): array
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        $transitions = [];
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getFromState()->getTechnicalName() === $fromStateName) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    public function transition(string $stateMachineName, string $fromStateName, string $transitionName, Context $context): StateMachineStateStruct
    {
        $stateMachine = $this->getStateMachine($stateMachineName, $context);

        /** @var StateMachineTransitionStruct $transition */
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getActionName() === $transitionName && $transition->getFromState()->getTechnicalName() === $fromStateName) {
                return $transition->getToState();
            }
        }

        $transitions = $this->getAvailableTransitions($stateMachineName, $fromStateName, $context);
        $transitionNames = array_map(function (StateMachineTransitionStruct $transition) {
            return $transition->getActionName();
        }, $transitions);

        throw new IllegalTransitionException(
            $fromStateName,
            $transitionName,
            $transitionNames
        );
    }
}
