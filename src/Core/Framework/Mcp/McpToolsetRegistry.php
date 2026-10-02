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

    final public const TOOL_SEARCH_TOOL = 'shopware-tool-search';

    /**
     * The always-advertised discovery interface (tool-search + toolsets-list/-enable). It is the
     * single source of truth for what is visible on a fresh session: tools in this group are the
     * only ones advertised up front, and it is never itself an enable-able toolset. Every other
     * tool is deferred and reachable only after its toolset is enabled.
     *
     * The group is reserved for {@see self::DISCOVERY_META_TOOLS}. Any other tool that claims it is
     * moved to {@see self::FALLBACK_GROUP} at compile time and reported by `debug:mcp`, so an
     * extension cannot put domain tools on the default surface of every connection. The supported
     * way to make tools visible on the first `tools/list` is connect-time selection (`?toolsets=`).
     */
    final public const DISCOVERY_GROUP = 'discovery';

    /**
     * The only tools allowed in {@see self::DISCOVERY_GROUP}. Both endpoints use the same names.
     */
    final public const DISCOVERY_META_TOOLS = [
        self::TOOL_SEARCH_TOOL,
        self::LIST_TOOLSETS_TOOL,
        self::ENABLE_TOOLSET_TOOL,
    ];

    /**
     * The catch-all toolset for tools without a group, and for tools that claimed the reserved
     * discovery group.
     */
    final public const FALLBACK_GROUP = 'other';

    /**
     * Spelled out rather than "*" so it survives being pasted into clients that escape wildcards.
     */
    final public const ALL_TOOLSETS = 'all';

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
        // surface tool names outside the caller's allowlist. Null passes through and lists
        // everything; only an administrator user, a scope without allowlists, or a call made outside
        // the request cycle yields it.
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
     * The tools of every named toolset, resolving {@see self::ALL_TOOLSETS} on the way. Unknown
     * names are dropped, not rejected: a name can outlive the plugin that contributed its toolset,
     * and a typo must not break an otherwise fine connection.
     *
     * Takes connect-URL and session names together because each call costs one catalogue read.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    public function advertisedToolsForNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        $toolsets = $this->toolsets();
        $known = array_column($toolsets, 'name');

        $enabled = \in_array(self::ALL_TOOLSETS, $names, true)
            ? $known
            : array_intersect($names, $known);

        $tools = [];

        foreach ($toolsets as $toolset) {
            if (!\in_array($toolset['name'], $enabled, true)) {
                continue;
            }

            array_push($tools, ...$toolset['tools']);
        }

        $tools = array_values(array_unique($tools));
        sort($tools);

        return $tools;
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
