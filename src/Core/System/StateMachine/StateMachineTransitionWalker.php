<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException;
use Shopware\Core\System\StateMachine\Exception\UnwalkablePathException;

class StateMachineTransitionWalker
{
    private const STATE_NAME = 'state';

    private const ACTION_NAME = 'action';

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $stateMachineStateRepository;

    public function __construct(
        StateMachineRegistry $stateMachineRegistry,
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        EntityRepositoryInterface $stateMachineStateRepository
    ) {
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->stateMachineStateRepository = $stateMachineStateRepository;
    }

    /**
     * @throws StateMachineInvalidStateFieldException
     * @throws UnwalkablePathException
     */
    public function walkPath(
        string $entityName,
        string $entityId,
        string $stateFieldName,
        string $toStateName,
        Context $context
    ): void {
        $definition = $this->definitionInstanceRegistry->getByEntityName($entityName);
        $stateField = $definition->getFields()->get($stateFieldName);

        if (!$stateField || !$stateField instanceof StateMachineStateField) {
            throw new StateMachineInvalidStateFieldException($stateFieldName);
        }

        $machine = $this->stateMachineRegistry->getStateMachine($stateField->getStateMachineName(), $context);
        $entity = $this->definitionInstanceRegistry->getRepository($entityName)->search(new Criteria([$entityId]), $context)->first();

        if (!$entity instanceof Struct) {
            return;
        }

        $stateId = $entity->getVars()[$stateFieldName];
        $fromState = $this->stateMachineStateRepository->search(new Criteria([$stateId]), $context)->first();

        if (!$fromState instanceof StateMachineStateEntity) {
            throw new UnwalkablePathException($stateField->getStateMachineName(), $entityId, $fromState->getTechnicalName(), $toStateName);
        }

        if ($fromState->getTechnicalName() === $toStateName) {
            return;
        }

        $transitions = $machine->getTransitions() ?? new StateMachineTransitionCollection();
        $path = $this->calculateTransitionPath($entityName, $entityId, $transitions, $fromState->getTechnicalName(), $toStateName);

        if (count($path) === 0) {
            throw new UnwalkablePathException($stateField->getStateMachineName(), $entityId, $fromState->getTechnicalName(), $toStateName);
        }

        foreach ($path as $transition) {
            $this->stateMachineRegistry->transition($transition, $context);
        }
    }

    /**
     * @return Transition[]
     */
    private function calculateTransitionPath(
        string $entityName,
        string $entityId,
        StateMachineTransitionCollection $transitions,
        string $from,
        string $to
    ): array {
        $paths = [[[self::STATE_NAME => $to]]];

        do {
            $newPaths = [];

            foreach ($paths as $path) {
                foreach ($this->getActionsToReachTarget($transitions, current($path)[self::STATE_NAME]) as $action => $state) {
                    if ($this->isStateInPath($path, $state)) {
                        continue;
                    }

                    $newPath = $path;
                    array_unshift($newPath, [
                        self::STATE_NAME => $state,
                        self::ACTION_NAME => $action,
                    ]);
                    $newPaths[] = $newPath;
                }
            }

            $paths = $newPaths;
        } while (!empty($paths) && empty($matches = $this->getPathThatAreConnectingFromWithTo($paths, $from, $to)));

        if (empty($matches)) {
            return [];
        }

        $match = current($matches);
        $result = [];

        foreach ($match as $transition) {
            if (!array_key_exists(self::ACTION_NAME, $transition)) {
                continue;
            }

            $result[] = new Transition($entityName, $entityId, $transition[self::ACTION_NAME], 'stateId');
        }

        return $result;
    }

    private function getActionsToReachTarget(StateMachineTransitionCollection $transitions, string $target): array
    {
        $result = [];

        foreach ($transitions as $transition) {
            if ($transition->getToStateMachineState()->getTechnicalName() === $target) {
                $result[$transition->getActionName()] = $transition->getFromStateMachineState()->getTechnicalName();
            }
        }

        return $result;
    }

    private function isStateInPath(array $path, string $stateName): bool
    {
        foreach ($path as $state) {
            if ($state[self::STATE_NAME] === $stateName) {
                return true;
            }
        }

        return false;
    }

    private function isPathConnectingFromWithTo(array $path, string $from, string $to): bool
    {
        if (count($path) === 0) {
            return false;
        }

        return end($path)[self::STATE_NAME] === $to && reset($path)[self::STATE_NAME] === $from;
    }

    private function getPathThatAreConnectingFromWithTo(array $paths, string $from, string $to): array
    {
        return array_values(array_filter($paths, function (array $path) use ($from, $to): bool {
            return $this->isPathConnectingFromWithTo($path, $from, $to);
        }));
    }
}
