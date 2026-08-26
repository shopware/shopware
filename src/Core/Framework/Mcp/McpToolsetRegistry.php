<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
class McpToolsetRegistry
{
    final public const LIST_TOOLSETS_TOOL = 'shopware-toolsets-list';

    final public const ENABLE_TOOLSET_TOOL = 'shopware-toolset-enable';

    /**
     * The always-advertised discovery interface (tool-search + toolsets-list/-enable). It is the
     * single source of truth for what is visible on a fresh session: tools in this group are the
     * only ones advertised up front, and it is never itself an enable-able toolset. Every other
     * tool is deferred and reachable only after its toolset is enabled.
     */
    final public const DISCOVERY_GROUP = 'discovery';

    /**
     * @internal
     *
     * $allowlistProvider is null in scopes without a per-integration allowlist (e.g. the Store API
     * registry, which advertises all its tools); a null provider leaves toolset generation unscoped.
     */
    public function __construct(
        private readonly McpCapabilityCatalog $catalog,
        private readonly ?McpAllowlistProvider $allowlistProvider = null,
    ) {
    }

    /**
     * @return list<array{name: string, title: string, description: string, tools: list<string>}>
     */
    public function toolsets(): array
    {
        $toolsByGroup = [];

        // Scope discovery to the caller's allowlist: toolsets-list and toolset-enable must never
        // surface tool names outside the integration's allowlist. A null allowlist (unrestricted
        // integration, or a scope without allowlists) passes null through and lists everything.
        $allowlist = $this->allowlistProvider?->toolsForCurrentRequest();

        foreach ($this->catalog->enrichedTools($allowlist) as $tool) {
            $group = $tool['group'];

            // The "discovery" group holds the always-advertised meta-tools and is never an
            // enable-able toolset. Every other group is, including the "other" catch-all: an
            // ungrouped tool (e.g. a bundle tool without #[McpToolGroup]) must still have a
            // guaranteed enable path instead of being reachable through tool-search alone.
            if ($group === self::DISCOVERY_GROUP) {
                continue;
            }

            $toolsByGroup[$group][] = $tool['name'];
        }

        ksort($toolsByGroup);

        $toolsets = [];
        foreach ($toolsByGroup as $group => $tools) {
            sort($tools);

            $toolsets[] = [
                'name' => $group,
                'title' => $this->humanizeToolsetName($group),
                'description' => \sprintf('Tools explicitly assigned to the "%s" MCP tool group.', $group),
                'tools' => $tools,
            ];
        }

        return $toolsets;
    }

    /**
     * @return array{name: string, title: string, description: string, tools: list<string>}|null
     */
    public function find(string $name): ?array
    {
        foreach ($this->toolsets() as $toolset) {
            if ($toolset['name'] === $name) {
                return $toolset;
            }
        }

        return null;
    }

    /**
     * @param list<string> $enabledToolsets
     *
     * @return list<string>
     */
    public function advertisedTools(array $enabledToolsets): array
    {
        $tools = [];

        foreach ($this->toolsets() as $toolset) {
            if (!\in_array($toolset['name'], $enabledToolsets, true)) {
                continue;
            }

            array_push($tools, ...$toolset['tools']);
        }

        $tools = array_values(array_unique($tools));
        sort($tools);

        return $tools;
    }

    private function humanizeToolsetName(string $name): string
    {
        return ucfirst(str_replace('-', ' ', $name)) . ' tools';
    }
}
