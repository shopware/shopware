<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-business-events', description: 'List all registered Shopware business events that can be used in flows and event actions.')]
#[Package('framework')]
class BusinessEventsTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventCollector $collector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(): string
    {
        $context = $this->contextProvider->getContext();
        $result = $this->collector->collect($context);

        $events = [];
        foreach ($result as $event) {
            $events[] = [
                'name' => $event->getName(),
                'class' => $event->getClass(),
                'data' => $event->getData(),
            ];
        }

        return $this->success($events, ['total' => \count($events)]);
    }
}
