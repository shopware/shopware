<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://business-events', name: 'shopware-business-events', description: 'All registered Shopware business events that can trigger flows and event actions.')]
#[Package('framework')]
class BusinessEventsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventCollector $collector,
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

        $events = [];
        foreach ($result as $event) {
            $events[] = [
                'name' => $event->getName(),
                'class' => $event->getClass(),
                'data' => $event->getData(),
            ];
        }

        return [
            'uri' => 'shopware://business-events',
            'mimeType' => 'application/json',
            'text' => Json::encode($events),
        ];
    }
}
