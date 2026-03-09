<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-state-machine-transition', description: 'Transition a Shopware entity state machine. Common entities: order, order_delivery, order_transaction. Common actions: process, complete, cancel, refund, reopen. Always use dryRun=true (default) to validate the transition is allowed, then dryRun=false to execute. See shopware://state-machines resource for available states and transitions.')]
#[Package('framework')]
class StateMachineTransitionTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $entityName,
        string $entityId,
        string $actionName,
        string $stateFieldName = 'stateId',
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, $entityName . ':read')) {
            return $error;
        }

        if (!$dryRun) {
            if ($error = $this->requirePrivilege($context, $entityName . ':update')) {
                return $error;
            }
        }

        if ($dryRun) {
            try {
                $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions(
                    $entityName,
                    $entityId,
                    $stateFieldName,
                    $context,
                );

                $available = [];
                foreach ($availableTransitions as $transition) {
                    $available[] = [
                        'actionName' => $transition->getActionName(),
                        'toStateName' => $transition->getToStateMachineState()?->getTechnicalName(),
                    ];
                }

                $actionValid = false;
                foreach ($available as $t) {
                    if ($t['actionName'] === $actionName) {
                        $actionValid = true;
                        break;
                    }
                }

                return $this->success([
                    'actionValid' => $actionValid,
                    'requestedAction' => $actionName,
                    'availableTransitions' => $available,
                ], ['dryRun' => true]);
            } catch (\Throwable $e) {
                return $this->error($e->getMessage());
            }
        }

        $transition = new Transition($entityName, $entityId, $actionName, $stateFieldName);
        $result = $this->stateMachineRegistry->transition($transition, $context);

        $states = [];
        foreach ($result as $state) {
            $states[] = [
                'technicalName' => $state->getTechnicalName(),
                'name' => $state->getName(),
            ];
        }

        return $this->success($states, ['dryRun' => false]);
    }
}
