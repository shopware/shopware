<?php declare(strict_types=1);

namespace Acme\AcmePlugin\Mcp;

use Acme\AcmePlugin\Subscriber\OrderFulfillmentSubscriber;
use Acme\AcmePlugin\Tax\AcmeTaxProvider;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

#[McpTool(
    name: 'acme-plugin-status',
    description: 'Returns the runtime status of the Acme integration plugin: version, registered tax provider, event subscribers, custom fields, and available MCP tools. Use this to verify the plugin is installed and active.',
)]
class AcmeStatusTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly AcmeTaxProvider $taxProvider,
    ) {
    }

    public function __invoke(): string
    {
        return $this->success([
            'plugin' => 'AcmePlugin',
            'version' => '1.0.0',
            'status' => 'active',
            'registered_components' => [
                'tax_provider' => [
                    'class' => AcmeTaxProvider::class,
                    'tag' => 'shopware.tax.provider',
                    'priority' => 200,
                    'description' => 'Pass-through provider — defers to Shopware default tax calculation',
                ],
                'order_subscriber' => [
                    'class' => OrderFulfillmentSubscriber::class,
                    'event' => 'Shopware\Core\System\StateMachine\Event\StateMachineTransitionedEvent',
                    'priority' => 50,
                ],
            ],
            'custom_fields' => [
                [
                    'entity' => 'product',
                    'field' => 'customFields.acme_sku',
                    'type' => 'text',
                    'description' => 'Acme internal SKU — required for ERP sync',
                ],
            ],
            'mcp_tools' => ['acme-plugin-status'],
            'tip' => 'Run bin/console debug:mcp to see this tool alongside all registered MCP capabilities.',
        ]);
    }
}
