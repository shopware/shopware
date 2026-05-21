<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Central dispatcher for UCP MCP tools. Resolves a tool by name from the
 * compile-time registry, validates that the underlying capability is in the
 * active intersection, and delegates the call.
 *
 * @internal
 */
#[Package('framework')]
class UcpMcpToolDispatcher
{
    /**
     * @param array<string, UcpMcpToolInterface> $tools
     * @param array<string, string> $capabilityMap tool name -> required UCP capability
     */
    public function __construct(
        private array $tools = [],
        private array $capabilityMap = [],
    ) {
    }

    public function hasTool(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>, outputSchema?: array<string, mixed>|null}>
     */
    public function listTools(UcpRequestContext $context): array
    {
        $out = [];
        foreach ($this->tools as $name => $tool) {
            $capability = $this->capabilityMap[$name] ?? null;
            if ($capability !== null && !$context->intersection->has($capability)) {
                continue;
            }

            $entry = [
                'name' => $name,
                'description' => $this->describeTool($name, $capability),
                'inputSchema' => $tool->inputSchema(),
            ];
            $output = $tool->outputSchema();
            if ($output !== null) {
                $entry['outputSchema'] = $output;
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments, UcpRequestContext $context): array
    {
        $tool = $this->tools[$name] ?? null;
        if ($tool === null) {
            throw UcpException::capabilityNotEnabled($name);
        }

        $capability = $this->capabilityMap[$name] ?? null;
        if ($capability !== null && !$context->intersection->has($capability)) {
            throw UcpException::capabilityNotEnabled($capability);
        }

        return $tool->invoke($arguments, $context);
    }

    private function describeTool(string $name, ?string $capability): string
    {
        $caps = $capability !== null ? ' (' . $capability . ')' : '';

        return match ($name) {
            'search_catalog' => 'Search the merchant\'s product catalog' . $caps,
            'lookup_catalog' => 'Look up specific products by id' . $caps,
            'get_product' => 'Get one product by id' . $caps,
            'create_cart' => 'Create a UCP cart session' . $caps,
            'get_cart' => 'Read a cart by id' . $caps,
            'update_cart' => 'Update a cart\'s line items' . $caps,
            'cancel_cart' => 'Cancel a cart session' . $caps,
            'create_checkout' => 'Create a UCP checkout session' . $caps,
            'get_checkout' => 'Read a checkout session by id' . $caps,
            'update_checkout' => 'Update a checkout session' . $caps,
            'complete_checkout' => 'Complete a checkout (place the order)' . $caps,
            'cancel_checkout' => 'Cancel a checkout session' . $caps,
            'get_order' => 'Read an order by id' . $caps,
            default => 'UCP MCP tool: ' . $name . $caps,
        };
    }
}
