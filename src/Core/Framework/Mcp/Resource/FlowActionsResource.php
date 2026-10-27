<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopware://flow-actions',
    name: 'shopware-flow-actions',
    description: 'All registered Shopware flow actions (core and app-provided) available in Flow Builder automations.'
)]
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

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
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

        return McpResourceCacheHint::read([
            'uri' => 'shopware://flow-actions',
            'mimeType' => 'application/json',
            'text' => Json::encode($actions),
        ], $requestContext, McpResourceCacheHint::DEPLOYMENT_TTL_MS);
    }
}
