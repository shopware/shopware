<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-flow-actions', description: 'List all registered Shopware flow actions (core and app-provided) that can be used in Flow Builder automations.')]
#[Package('framework')]
class FlowActionsTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly FlowActionCollector $collector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(): string
    {
        $context = $this->contextProvider->getContext();
        $result = $this->collector->collect($context);

        $actions = [];
        foreach ($result as $action) {
            $actions[] = [
                'name' => $action->getName(),
                'requirements' => $action->getRequirements(),
                'delayable' => $action->getDelayable(),
            ];
        }

        usort($actions, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return $this->success($actions, ['total' => \count($actions)]);
    }
}
