<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://flow-actions', name: 'shopware-flow-actions', description: 'All registered Shopware flow actions (core and app-provided) available in Flow Builder automations.')]
#[Package('framework')]
class FlowActionsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FlowActionCollector $collector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
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

        return [
            'uri' => 'shopware://flow-actions',
            'mimeType' => 'application/json',
            'text' => Json::encode($actions),
        ];
    }
}
