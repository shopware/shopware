<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
class McpToolsetRegistry
{
    final public const LIST_TOOLSETS_TOOL = 'shopware-toolsets-list';

    final public const ENABLE_TOOLSET_TOOL = 'shopware-toolset-enable';

    private const DEFAULT_GROUP = 'default';

    /**
     * @internal
     */
    public function __construct(
        private readonly McpCapabilityCatalog $catalog,
    ) {
    }

    /**
     * @return list<array{name: string, title: string, description: string, tools: list<string>}>
     */
    public function toolsets(): array
    {
        $toolsByGroup = [];

        foreach ($this->catalog->enrichedTools() as $tool) {
            $group = $tool['group'];

            // The "default" group holds the always-advertised meta-tools and is never an
            // enable-able toolset. Every other group is, including the "other" catch-all: an
            // ungrouped tool (e.g. a bundle tool without #[McpToolGroup]) must still have a
            // guaranteed enable path instead of being reachable through tool-search alone.
            if ($group === self::DEFAULT_GROUP) {
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
