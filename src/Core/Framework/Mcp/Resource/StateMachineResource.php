<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\StateMachineCollection;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://state-machines', name: 'shopware-state-machines', description: 'All state machines with their states and transitions. Use this to understand valid actions for shopware-order-state.')]
#[Package('framework')]
class StateMachineResource
{
    /**
     * @internal
     *
     * @param EntityRepository<StateMachineCollection> $stateMachineRepository
     */
    public function __construct(
        private readonly EntityRepository $stateMachineRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('states');
        $criteria->addAssociation('transitions.fromStateMachineState');
        $criteria->addAssociation('transitions.toStateMachineState');

        $result = $this->stateMachineRepository->search($criteria, Context::createDefaultContext());

        $machines = [];
        foreach ($result->getEntities() as $machine) {
            $states = [];
            foreach ($machine->getStates() ?? [] as $state) {
                $states[] = [
                    'technicalName' => $state->getTechnicalName(),
                    'name' => $state->getName(),
                ];
            }

            $transitions = [];
            foreach ($machine->getTransitions() ?? [] as $transition) {
                $transitions[] = [
                    'actionName' => $transition->getActionName(),
                    'fromState' => $transition->getFromStateMachineState()?->getTechnicalName(),
                    'toState' => $transition->getToStateMachineState()?->getTechnicalName(),
                ];
            }

            $machines[] = [
                'technicalName' => $machine->getTechnicalName(),
                'name' => $machine->getName(),
                'states' => $states,
                'transitions' => $transitions,
            ];
        }

        return [
            'uri' => 'shopware://state-machines',
            'mimeType' => 'application/json',
            'text' => json_encode($machines, \JSON_THROW_ON_ERROR),
        ];
    }
}
