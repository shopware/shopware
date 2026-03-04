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
#[McpTool(name: 'shopware-state-machine-transition', description: 'Transition a Shopware entity state (e.g. order, delivery, transaction). Set dryRun=true (default) to validate without executing.')]
#[Package('framework')]
class StateMachineTransitionTool
{
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

        $readPermission = $entityName . ':read';
        if (!$context->isAllowed($readPermission)) {
            return json_encode(['error' => 'Missing privilege: ' . $readPermission], \JSON_THROW_ON_ERROR);
        }

        if (!$dryRun) {
            $updatePermission = $entityName . ':update';
            if (!$context->isAllowed($updatePermission)) {
                return json_encode(['error' => 'Missing privilege: ' . $updatePermission], \JSON_THROW_ON_ERROR);
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

                return json_encode([
                    'dryRun' => true,
                    'actionValid' => $actionValid,
                    'requestedAction' => $actionName,
                    'availableTransitions' => $available,
                ], \JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                return json_encode([
                    'dryRun' => true,
                    'success' => false,
                    'error' => $e->getMessage(),
                ], \JSON_THROW_ON_ERROR);
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

        return json_encode([
            'dryRun' => false,
            'success' => true,
            'states' => $states,
        ], \JSON_THROW_ON_ERROR);
    }
}
